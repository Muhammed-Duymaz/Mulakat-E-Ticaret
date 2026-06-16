<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\IyzipayService;
use App\Services\OrderService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentApiController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly IyzipayService $iyzipayService
    ) {}

    /**
     * POST /api/v1/payments/iyzico/initialize
     *
     * Initializes a 3D Secure checkout. Creates a pending order, locks stock,
     * and returns the raw HTML form from Iyzico.
     */
    public function initialize3DS(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipping_address_id' => ['required', 'integer', 'exists:addresses,id'],
            'cardHolderName'      => ['required', 'string', 'max:50'],
            'cardNumber'          => ['required', 'string', 'max:16'],
            'expireMonth'         => ['required', 'string', 'size:2'],
            'expireYear'          => ['required', 'string', 'size:4'],
            'cvc'                 => ['required', 'string', 'min:3', 'max:4'],
            'coupon_code'         => ['nullable', 'string', 'max:60'],
            'notes'               => ['nullable', 'string', 'max:500'],
        ]);

        try {
            // 1. Create a pending order WITHOUT clearing the cart yet
            $order = $this->orderService->createOrderFromCart(
                userId:            $request->user()->id,
                shippingAddressId: $validated['shipping_address_id'],
                paymentMethod:     'iyzipay',
                paymentReference:  '',
                couponCode:        $validated['coupon_code'] ?? null,
                notes:             $validated['notes'] ?? null,
                clearCart:         false // IMPORTANT: Keep cart intact until payment succeeds
            );

            // 2. Extract card data
            $cardData = $request->only(['cardHolderName', 'cardNumber', 'expireMonth', 'expireYear', 'cvc']);

            // 3. Initialize 3D Secure with Iyzico
            $htmlForm = $this->iyzipayService->initialize3DS(
                $order, 
                $cardData, 
                $request->ip() ?? '127.0.0.1'
            );

            // 4. Return the raw HTML string to the frontend
            return response()->json([
                'message'      => '3D Secure payment initialized.',
                'html_content' => $htmlForm,
                'order_number' => $order->order_number,
            ]);

        } catch (Exception $e) {
            Log::error('Payment Initialization Error', [
                'user_id' => $request->user()->id,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to initialize payment. Please check your card details and try again.',
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/v1/payments/iyzico/callback
     *
     * The callback endpoint that the bank redirects to after 3D Secure authentication.
     * This route is public.
     */
    public function callback3DS(Request $request)
    {
        // Iyzico sends paymentId, conversationData (order_number), and status as POST body.
        $status = $request->post('status');
        $paymentId = $request->post('paymentId');
        $conversationData = $request->post('conversationData'); // Our order_number
        
        Log::info('Received Iyzico 3D Secure Callback', $request->all());

        // We must have an order_number to know which order this is
        if (!$conversationData) {
            return response()->json(['message' => 'Invalid callback data.'], 400);
        }

        $order = Order::where('order_number', $conversationData)->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        // If the bank authentication failed directly
        if ($status !== 'success') {
            $reason = $request->post('mdStatus') ?? 'Bank Authentication Failed';
            $this->orderService->failPayment($order, $reason);
            
            // Redirect to frontend failure page
            return redirect(config('app.frontend_url') . '/checkout/failure?order=' . $order->order_number);
        }

        // Authentication succeeded, now we finalize the charge with Iyzico
        $result = $this->iyzipayService->finalize3DS($paymentId, $order->order_number);

        if ($result['success']) {
            // Payment completed successfully! 
            // Mark as paid, which also clears the customer's cart
            $this->orderService->completePayment($order, $result['payment_ref']);
            
            // Redirect to frontend success page
            return redirect(config('app.frontend_url') . '/checkout/success?order=' . $order->order_number);
        } else {
            // Verification failed (e.g. insufficient funds despite passing 3D secure)
            $this->orderService->failPayment($order, $result['error'] ?? 'Final verification failed');
            
            // Redirect to frontend failure page
            return redirect(config('app.frontend_url') . '/checkout/failure?order=' . $order->order_number);
        }
    }
}
