<?php

namespace App\Notifications;

use App\Models\Order;
use App\Broadcasting\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderShippedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Order $order) {}

    public function via($notifiable): array
    {
        return ['mail', SmsChannel::class];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Siparişiniz Kargoya Verildi! #' . $this->order->order_number)
            ->greeting('Merhaba ' . $notifiable->name . ',')
            ->line('Harika haber! Siparişiniz kargoya teslim edildi.')
            ->line('Kargo Firması: ' . strtoupper($this->order->shipping_carrier))
            ->line('Takip Numarası: ' . $this->order->tracking_code)
            ->action('Kargomu Takip Et', url('/orders/' . $this->order->order_number . '/track'))
            ->line('Bizi tercih ettiğiniz için teşekkür ederiz!');
    }

    public function toSms($notifiable): string
    {
        return "Sayin {$notifiable->name}, {$this->order->order_number} numarali siparisiniz {$this->order->shipping_carrier} kargoya teslim edilmistir. Takip No: {$this->order->tracking_code}";
    }
}
