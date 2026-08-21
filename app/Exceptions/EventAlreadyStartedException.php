<?php

namespace App\Exceptions;

use App\Models\Order;
use Exception;
use Illuminate\Http\Request;

class EventAlreadyStartedException extends Exception
{
    public function __construct(public readonly Order $order)
    {
        parent::__construct("Order {$order->id} cannot be cancelled because its event has already started.");
    }

    public function render(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->withInput()->withErrors(['order' => $this->getMessage()]);
    }
}
