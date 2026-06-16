<?php

namespace App\Notifications;

use App\Models\Order;
use App\Broadcasting\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlacedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Order $order) {}

    /**
     * Get the notification's delivery channels.
     * We deliver via both Mail and our custom SMS Channel.
     */
    public function via($notifiable): array
    {
        // For production, you might want to check user preferences
        // e.g., if ($notifiable->wants_sms) { ... }
        return ['mail', SmsChannel::class];
    }

    /**
     * Build the Mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Siparişiniz Alındı! #' . $this->order->order_number)
            ->greeting('Merhaba ' . $notifiable->name . ',')
            ->line('Siparişinizi başarıyla aldık ve hazırlamaya başlıyoruz.')
            ->line('Sipariş Tutarı: ' . number_format($this->order->grand_total, 2) . ' TL')
            ->action('Siparişi Görüntüle', url('/orders/' . $this->order->order_number))
            ->line('Bizi tercih ettiğiniz için teşekkür ederiz!');
    }

    /**
     * Build the SMS representation of the notification.
     */
    public function toSms($notifiable): string
    {
        return "Sayin {$notifiable->name}, {$this->order->order_number} numarali siparisiniz basariyla alinmistir. Bizi tercih ettiginiz icin tesekkur ederiz.";
    }
}
