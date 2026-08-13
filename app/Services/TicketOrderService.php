<?php

namespace App\Services;

use App\Exceptions\TicketUnavailableException;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TicketOrderService
{
    public function order(int $ticketTypeId, int $quantity, int $userId): Order
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

            Log::info('order.placed', [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'event_id' => $ticketType->event_id,
                'ticket_type_id' => $ticketType->id,
                'quantity' => $quantity,
            ]);

            Log::info('order.confirmation_stub', [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'message' => "Confirmation email would be sent to user {$order->user_id} for order {$order->id}.",
            ]);

            return $order;
        });
    }
}
