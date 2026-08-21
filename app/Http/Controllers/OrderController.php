<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Services\TicketOrderService;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function __construct(public TicketOrderService $ticketOrderService) {}

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

        $attributes = $request->validated();
        $ticketTypeId = $attributes['ticket_type_id'];
        $quantity = $attributes['quantity'];

        $order = $this->ticketOrderService->order($ticketTypeId, $quantity, Auth::id());

        return redirect()->route('events.show', $order->event_id)->with('success', 'Tickets purchased successfully.');

    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        $this->authorize('view', $order);

        $order->load(['event', 'tickets.ticketType']);

        return view('orders.show', compact('order'));
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

    public function cancel(Order $order)
    {
        $this->authorize('update', $order);

        $order = $this->ticketOrderService->cancel($order->id);

        return redirect()->route('orders.show', $order)->with('success', 'Order cancelled successfully.');
    }
}
