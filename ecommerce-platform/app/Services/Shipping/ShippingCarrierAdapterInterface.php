<?php

namespace App\Services\Shipping;

use App\Models\Order;

/**
 * ShippingCarrierAdapterInterface
 *
 * Enforces a standard contract for all shipping carriers (Yurtiçi, Aras, MNG, etc.).
 * Each carrier will have its own implementation that translates their specific
 * webhook payloads into our internal system statuses.
 */
interface ShippingCarrierAdapterInterface
{
    /**
     * Parse the incoming webhook payload and return our internal status.
     * 
     * Expected return values: 'shipped', 'delivered', 'processing', etc.
     * 
     * @param array $payload
     * @return string
     */
    public function mapStatus(array $payload): string;

    /**
     * Extract the tracking code from the carrier's payload.
     * 
     * @param array $payload
     * @return string
     */
    public function extractTrackingCode(array $payload): string;

    /**
     * Validate that the incoming webhook is genuinely from the carrier.
     * Could check an API signature, a token in headers, or an IP whitelist.
     * 
     * @param \Illuminate\Http\Request $request
     * @return bool
     */
    public function validateWebhook(\Illuminate\Http\Request $request): bool;
}
