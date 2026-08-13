<?php

namespace App\Services;

use App\Exceptions\TicketUnavailableException;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketOrderService
{
    public function order(int $ticketTypeId, int $quantity, int $userId): int
    {
        return DB::transaction(function () use ($ticketTypeId, $quantity, $userId) {
            $ticketType = TicketType::lockForUpdate()->findOrFail($ticketTypeId);

            $available = $ticketType->remaining_quantity;

            if ($quantity > $available) {
                throw new TicketUnavailableException($ticketType, $quantity, $available);
            }

            $order = Order::create([
                'user_id' => $userId,
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

            return $order->id;
        });
    }
}
