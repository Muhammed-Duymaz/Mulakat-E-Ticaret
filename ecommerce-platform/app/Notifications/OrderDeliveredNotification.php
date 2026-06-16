<?php

namespace App\Notifications;

use App\Models\Order;
use App\Broadcasting\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderDeliveredNotification extends Notification implements ShouldQueue
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
            ->subject('Siparişiniz Teslim Edildi! #' . $this->order->order_number)
            ->greeting('Merhaba ' . $notifiable->name . ',')
            ->line('Siparişinizin başarıyla teslim edildiğini bildirmek isteriz.')
            ->line('Ürünlerinizi güzel günlerde kullanmanızı dileriz.')
            ->action('Siparişi Değerlendir', url('/orders/' . $this->order->order_number . '/review'))
            ->line('Bizi tercih ettiğiniz için teşekkür ederiz!');
    }

    public function toSms($notifiable): string
    {
        return "Sayin {$notifiable->name}, {$this->order->order_number} numarali siparisiniz teslim edilmistir. Guzel gunlerde kullanmanizi dileriz.";
    }
}
