<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * Concurrency-safe purchase flow: the ticket_types row is re-fetched
     * with lockForUpdate() inside the transaction, so a second concurrent
     * request blocks until the first commits (or rolls back) before it can
     * read availability — closing the check-then-act race.
     */
    public function store(StoreOrderRequest $request)
    {
        $this->authorize('create', Order::class);

        $ticketTypeId = $request->validated('ticket_type_id');

        return DB::transaction(function () use ($request, $ticketTypeId) {
            $ticketType = TicketType::lockForUpdate()->findOrFail($ticketTypeId);
            $quantity = (int) $request->validated('quantity');
            $available = $ticketType->remaining_quantity;

            if ($quantity > $available) {
                throw ValidationException::withMessages([
                    'quantity' => "Only {$available} ticket(s) remaining for {$ticketType->name}.",
                ]);
            }

            $order = Auth::user()->orders()->create([
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

            return redirect()->route('events.show', $ticketType->event_id)->with('success', 'Tickets purchased successfully.');
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Order $order)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update($request, Order $order)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        //
    }
}
