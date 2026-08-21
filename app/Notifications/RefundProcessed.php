<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundProcessed extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Order $order) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $this->order->loadMissing(['event', 'refund']);

        $event = $this->order->event;
        $refund = $this->order->refund;

        return (new MailMessage)
            ->subject("Your order for {$event->name} has been cancelled")
            ->greeting('Order cancelled')
            ->line("Your order for {$event->name} has been cancelled and refunded.")
            ->line("Refund amount: \${$refund->amount}")
            ->line('If you did not request this cancellation, please contact us.');
    }
}
