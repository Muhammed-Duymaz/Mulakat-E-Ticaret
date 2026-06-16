<?php

namespace App\Services\Sms;

/**
 * SmsProviderInterface
 *
 * Defines the contract for all SMS gateways (Netgsm, Twilio, Mutlucell, etc.).
 */
interface SmsProviderInterface
{
    /**
     * Send an SMS message to a specific phone number.
     *
     * @param string $phone Must be in a standardized format (e.g., +905551234567)
     * @param string $message The content of the SMS
     * @return bool True if successful, False otherwise.
     */
    public function send(string $phone, string $message): bool;
}
