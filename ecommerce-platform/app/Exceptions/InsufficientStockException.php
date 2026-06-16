<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * InsufficientStockException
 *
 * Thrown by CartService and OrderService when the requested quantity
 * exceeds the available stock for a product or variant.
 *
 * Usage:
 *   throw new InsufficientStockException($product->name, $requested, $available);
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly string $productName,
        public readonly int    $requested,
        public readonly int    $available,
        string $message = '',
    ) {
        parent::__construct(
            $message ?: "Insufficient stock for \"{$productName}\". "
                      . "Requested: {$requested}, Available: {$available}."
        );
    }

    /**
     * Convert to an API-friendly array for JSON error responses.
     */
    public function toArray(): array
    {
        return [
            'error'        => 'insufficient_stock',
            'product_name' => $this->productName,
            'requested'    => $this->requested,
            'available'    => $this->available,
            'message'      => $this->getMessage(),
        ];
    }
}
