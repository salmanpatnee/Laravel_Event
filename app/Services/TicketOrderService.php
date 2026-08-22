<?php

namespace App\Services;

use App\Events\OrderCancelled;
use App\Events\OrderPlaced;
use App\Exceptions\EventAlreadyStartedException;
use App\Exceptions\OrderAlreadyCancelledException;
use App\Exceptions\TicketUnavailableException;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\OrderStatusEnum;
use Illuminate\Support\Facades\Cache;
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
                'status' => OrderStatusEnum::Confirmed,
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

        Cache::forget("event:{$order->event_id}:dashboard");

        OrderPlaced::dispatch($order);

        return $order;
    }

    public function cancel(int $orderId): Order
    {
        $order = DB::transaction(function () use ($orderId) {
            $order = Order::lockForUpdate()->findOrFail($orderId);

            if ($order->status === OrderStatusEnum::Cancelled) {
                throw new OrderAlreadyCancelledException($order);
            }

            if ($order->event->start_time->isPast()) {
                throw new EventAlreadyStartedException($order);
            }

            $order->status = OrderStatusEnum::Cancelled;
            $order->save();

            $order->refund()->create([
                'amount' => $order->total_amount,
            ]);

            return $order;
        });

        Cache::forget("event:{$order->event_id}:dashboard");

        OrderCancelled::dispatch($order);

        return $order;

    }
}
