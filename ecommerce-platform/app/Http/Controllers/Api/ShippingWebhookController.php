<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ShippingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShippingWebhookController extends Controller
{
    public function __construct(private readonly ShippingService $shippingService) {}

    /**
     * POST /api/v1/webhooks/shipping/{carrier}
     *
     * Public endpoint for shipping carriers to push status updates.
     */
    public function handle(Request $request, string $carrier): JsonResponse
    {
        try {
            $this->shippingService->processWebhook($carrier, $request);

            return response()->json(['message' => 'Webhook processed successfully.']);

        } catch (Exception $e) {
            Log::error("Webhook processing failed for {$carrier}", [
                'error' => $e->getMessage()
            ]);

            // Return 200 or 400 depending on if we want the carrier to retry.
            // If it's a validation error, 400 is appropriate.
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
