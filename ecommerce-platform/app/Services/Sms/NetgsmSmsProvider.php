<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * NetgsmSmsProvider
 *
 * Concrete implementation for the Netgsm SMS API.
 */
class NetgsmSmsProvider implements SmsProviderInterface
{
    public function send(string $phone, string $message): bool
    {
        // For local development, just log it.
        if (config('app.env') === 'local') {
            Log::info("Netgsm SMS Simulated to {$phone}: {$message}");
            return true;
        }

        try {
            $response = Http::asForm()->post('https://api.netgsm.com.tr/sms/send/get', [
                'usercode' => config('services.netgsm.usercode'),
                'password' => config('services.netgsm.password'),
                'gsmno'    => $phone,
                'message'  => $message,
                'msgheader'=> config('services.netgsm.header'),
            ]);

            // Netgsm usually returns a plain string like "00 12345678" on success
            if (str_starts_with($response->body(), '00')) {
                return true;
            }

            Log::error("Netgsm SMS Failed: " . $response->body());
            return false;

        } catch (\Exception $e) {
            Log::error("Netgsm API Error: " . $e->getMessage());
            return false;
        }
    }
}
