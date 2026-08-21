<?php

namespace App\Exceptions;

use App\Models\Order;
use Exception;
use Illuminate\Http\Request;

class OrderAlreadyCancelledException extends Exception
{
    public function __construct(public readonly Order $order)
    {
        parent::__construct("Order {$order->id} is already cancelled.");
    }

    public function render(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->withInput()->withErrors(['quantity' => $this->getMessage()]);
    }
}
