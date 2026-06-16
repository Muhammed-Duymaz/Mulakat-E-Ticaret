<?php

namespace App\Broadcasting;

use App\Services\Sms\SmsProviderInterface;
use App\Services\Sms\NetgsmSmsProvider;
use Illuminate\Notifications\Notification;
use Exception;

/**
 * SmsChannel
 *
 * A custom Laravel Notification Channel that routes messages
 * to our abstract SmsProviderInterface.
 */
class SmsChannel
{
    private SmsProviderInterface $smsProvider;

    public function __construct()
    {
        // In a real app, this should be resolved via Laravel's Service Container
        // e.g. return app(SmsProviderInterface::class);
        // For this architecture demo, we instantiate the default:
        $this->smsProvider = new NetgsmSmsProvider();
    }

    /**
     * Send the given notification.
     *
     * @param mixed $notifiable
     * @param Notification $notification
     * @throws Exception
     */
    public function send($notifiable, Notification $notification): void
    {
        // Check if the notification class actually defines a toSms method
        if (!method_exists($notification, 'toSms')) {
            throw new Exception('Notification is missing toSms method.');
        }

        $message = $notification->toSms($notifiable);
        
        // Get the phone number from the notifiable entity (e.g., User model)
        $phone = $notifiable->routeNotificationFor('sms') ?? $notifiable->phone;

        if (!$phone || !$message) {
            return;
        }

        $this->smsProvider->send($phone, $message);
    }
}
