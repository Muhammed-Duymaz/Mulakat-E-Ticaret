<?php

namespace App\Services;

use App\Models\Order;
use App\Services\Shipping\ShippingCarrierAdapterInterface;
use App\Services\Shipping\YurticiAdapter;
// use App\Services\Shipping\ArasAdapter;
// use App\Services\Shipping\MngAdapter;
use App\Notifications\OrderDeliveredNotification;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * ShippingService
 *
 * Handles the logic of identifying the correct carrier adapter
 * and processing webhook payloads to update order statuses.
 */
class ShippingService
{
    public function __construct(private readonly OrderService $orderService) {}

    /**
     * Get the correct adapter for a given carrier string.
     */
    public function getAdapter(string $carrier): ShippingCarrierAdapterInterface
    {
        return match (strtolower($carrier)) {
            'yurtici' => new YurticiAdapter(),
            // 'aras' => new ArasAdapter(),
            // 'mng'  => new MngAdapter(),
            default   => throw new Exception("Unsupported shipping carrier: {$carrier}"),
        };
    }

    /**
     * Process an incoming webhook request.
     *
     * @param string $carrier  e.g. 'yurtici'
     * @param Request $request
     * @return bool True if processed successfully.
     * @throws Exception
     */
    public function processWebhook(string $carrier, Request $request): bool
    {
        $adapter = $this->getAdapter($carrier);

        // 1. Security validation
        if (!$adapter->validateWebhook($request)) {
            Log::warning("Invalid webhook signature for carrier: {$carrier}", [
                'ip' => $request->ip(),
                'payload' => $request->all(),
            ]);
            throw new Exception("Webhook validation failed.");
        }

        $payload = $request->all();

        // 2. Extract Tracking Code
        $trackingCode = $adapter->extractTrackingCode($payload);
        if (!$trackingCode) {
            throw new Exception("Tracking code missing from payload.");
        }

        // 3. Find Order
        $order = Order::where('tracking_code', $trackingCode)->first();
        if (!$order) {
            Log::warning("Shipping webhook received for unknown tracking code.", [
                'carrier' => $carrier,
                'tracking_code' => $trackingCode,
            ]);
            return false; // Not throwing error so carrier doesn't keep retrying
        }

        // 4. Map Status
        $internalStatus = $adapter->mapStatus($payload);

        // 5. Update Order
        if ($internalStatus === 'delivered' && !$order->isDelivered()) {
            $order->transitionTo('delivered');
            $order->items()->update(['status' => 'delivered']);
            
            // Dispatch Delivered Notification
            $order->user->notify(new OrderDeliveredNotification($order));
            
            Log::info("Order #{$order->order_number} marked as delivered via webhook.");
        } elseif ($internalStatus === 'shipped' && !$order->isShipped()) {
            // Usually we mark shipped manually, but just in case
            $this->orderService->markAsShipped($order, $carrier, $trackingCode);
            Log::info("Order #{$order->order_number} marked as shipped via webhook.");
        }

        return true;
    }
}
