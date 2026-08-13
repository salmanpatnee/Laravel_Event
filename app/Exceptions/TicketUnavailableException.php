<?php

namespace App\Exceptions;

use App\Models\TicketType;
use Exception;
use Illuminate\Http\Request;

class TicketUnavailableException extends Exception
{
    public function __construct(public readonly TicketType $ticketType, public readonly int $requested, public readonly int $available)
    {
        parent::__construct("Only {$available} ticket(s) remaining for {$ticketType->name}, {$requested} requested.");
    }

    public function render(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->withInput()->withErrors(['quantity' => $this->getMessage()]);
    }
}
