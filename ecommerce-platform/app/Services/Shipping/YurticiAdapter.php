<?php

namespace App\Services\Shipping;

use Illuminate\Http\Request;

/**
 * YurticiAdapter
 *
 * Example implementation of the ShippingCarrierAdapterInterface for Yurtiçi Kargo.
 */
class YurticiAdapter implements ShippingCarrierAdapterInterface
{
    public function mapStatus(array $payload): string
    {
        // Example Yurtici Status Codes:
        // 1: Package Received
        // 2: In Transit
        // 3: Out for Delivery
        // 4: Delivered
        // 5: Delivery Failed / Returned

        $statusCode = $payload['CargoStatus'] ?? null;

        return match ((int) $statusCode) {
            1, 2, 3 => 'shipped',
            4       => 'delivered',
            5       => 'returned', // Or handled manually depending on business logic
            default => 'processing',
        };
    }

    public function extractTrackingCode(array $payload): string
    {
        return $payload['TrackingNumber'] ?? '';
    }

    public function validateWebhook(Request $request): bool
    {
        // Yurtici might send a specific header or require IP whitelisting
        $token = $request->header('X-Yurtici-Signature');
        $expectedToken = config('services.shipping.yurtici.webhook_secret');

        // Simple token comparison
        return hash_equals($expectedToken, $token ?? '');
    }
}
