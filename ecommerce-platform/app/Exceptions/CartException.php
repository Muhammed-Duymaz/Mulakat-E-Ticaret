<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * CartException
 *
 * General-purpose cart error (item not found, cart empty, etc.).
 */
class CartException extends RuntimeException
{
    public function __construct(
        string $message = 'An error occurred with the shopping cart.',
        public readonly string $errorCode = 'cart_error',
    ) {
        parent::__construct($message);
    }

    public function toArray(): array
    {
        return [
            'error'   => $this->errorCode,
            'message' => $this->getMessage(),
        ];
    }
}
