<?php

namespace App\Listeners;

use App\Events\OrderCancelled;
use App\Notifications\RefundProcessed;

class SendRefundNotification
{
    /**
     * Handle the event.
     */
    public function handle(OrderCancelled $event): void
    {
        $event->order->user->notify(new RefundProcessed($event->order));
    }
}
