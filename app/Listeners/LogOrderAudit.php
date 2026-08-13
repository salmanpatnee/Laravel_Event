<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogOrderAudit
{
    /**
     * Create the event listener.
     */
    public function __construct(Public OrderPlaced $event)
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
            // 'event_id' => $event->order->ticketType->event_id,
            // 'ticket_type_id' => $event->order->ticketType->id,
            'quantity' => $event->order->quantity,
        ]);
    }
}
