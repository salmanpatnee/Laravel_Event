<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use Illuminate\Support\Facades\Log;

class LogOrderAudit
{
    /**
     * Create the event listener.
     */
    public function __construct(public OrderPlaced $event)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(OrderPlaced $event): void
    {
        Log::info('order.placed', [
            'order_id' => $event->order->id,
            'user_id' => $event->order->user_id,
            'event_id' => $event->order->event_id,
            'ticket_type_id' => $event->order->tickets->first()?->ticket_type_id,
            'quantity' => $event->order->tickets->count(),
        ]);
    }
}
