<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventReminder extends Notification implements ShouldQueue
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
        $this->order->loadMissing(['event', 'user']);

        $event = $this->order->event;

        return (new MailMessage)
            ->subject("Reminder: {$event->name} is coming up")
            ->greeting("Hi {$this->order->user->name},")
            ->line("This is a reminder that {$event->name} starts {$event->start_time->diffForHumans()}.")
            ->line("Date & time: {$event->start_time->format('F j, Y \a\t g:i A')}")
            ->line('We look forward to seeing you there!');
    }
}
