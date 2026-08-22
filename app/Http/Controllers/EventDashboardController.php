<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Ticket;
use App\OrderStatusEnum;
use Illuminate\Support\Facades\Cache;

class EventDashboardController extends Controller
{
    /**
     * Show the organizer's sales dashboard for a single event.
     */
    public function show(Event $event)
    {
        $this->authorize('update', $event);

        $stats = Cache::remember("event:{$event->id}:dashboard", now()->addHour(), function () use ($event) {
            return $this->calculateStats($event);
        });

        return view('events.dashboard', compact('event', 'stats'));
    }

    /**
     * Aggregate tickets sold and net revenue for the given event.
     *
     * Net revenue sums every order for the event and subtracts every refund
     * against those orders. Cancelled orders are not filtered out because
     * their refund already cancels them out, and leaving them in keeps the
     * figure correct if a partial refund is ever issued on a live order.
     *
     * @return array{tickets_sold: int, net_revenue: float}
     */
    private function calculateStats(Event $event): array
    {
        $ticketsSold = Ticket::whereHas('order', function ($query) use ($event) {
            $query->where('event_id', $event->id)
                ->where('status', '!=', OrderStatusEnum::Cancelled);
        })->count();

        $grossRevenue = Order::where('event_id', $event->id)->sum('total_amount');

        $refundedAmount = Refund::whereHas('order', function ($query) use ($event) {
            $query->where('event_id', $event->id);
        })->sum('amount');

        return [
            'tickets_sold' => $ticketsSold,
            'net_revenue' => (float) $grossRevenue - (float) $refundedAmount,
        ];
    }
}
