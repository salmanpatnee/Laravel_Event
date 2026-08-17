<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmation extends Notification
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
        $this->order->loadMissing(['event', 'tickets.ticketType']);

        $message = (new MailMessage)
            ->subject("Your order for {$this->order->event->name} is confirmed")
            ->greeting('Order confirmed!')
            ->line("You're all set for {$this->order->event->name}.");

        $ticketsByType = $this->order->tickets->groupBy('ticket_type_id');

        foreach ($ticketsByType as $tickets) {
            $ticketType = $tickets->first()->ticketType;

            $message->line("{$tickets->count()} x {$ticketType->name}");
        }

        return $message
            ->line("Order total: \${$this->order->total_amount}")
            ->line('Thank you for your purchase!');
    }
}
