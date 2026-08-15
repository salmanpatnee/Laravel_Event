<?php

namespace App\Services;

use App\Events\OrderPlaced;
use App\Exceptions\TicketUnavailableException;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketOrderService
{
    public function order(int $ticketTypeId, int $quantity, int $userId): Order
    {
        $order = DB::transaction(function () use ($ticketTypeId, $quantity, $userId) {
            $ticketType = TicketType::lockForUpdate()->findOrFail($ticketTypeId);
            $attendee = User::findOrFail($userId);
            $available = $ticketType->remaining_quantity;

            if ($quantity > $available) {
                throw new TicketUnavailableException($ticketType, $quantity, $available);
            }

            $order = $attendee->orders()->create([
                'event_id' => $ticketType->event_id,
                'total_amount' => $ticketType->price * $quantity,
            ]);

            

            for ($i = 0; $i < $quantity; $i++) {
                Ticket::create([
                    'order_id' => $order->id,
                    'ticket_type_id' => $ticketType->id,
                    'code' => Str::uuid(),
                ]);
            }

            return $order;
        });

        OrderPlaced::dispatch($order);

        return $order;
    }
}
