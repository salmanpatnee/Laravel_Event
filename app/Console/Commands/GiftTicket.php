<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('app:gift-ticket {event_id : The ID of the event} {ticket_type_id : The ID of the ticket type} {quantity : The number of tickets to gift} {user_id : The ID of the user to gift the tickets to}')]
#[Description('Comp free VIP tickets to specific attendees — sponsors, staff, contest winners.')]
class GiftTicket extends Command implements PromptsForMissingInput
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Gifting {$this->argument('quantity')} ticket(s) of type {$this->argument('ticket_type_id')} for event {$this->argument('event_id')}.");
        
         $ticketTypeId = $this->argument('ticket_type_id');

        return DB::transaction(function () use ($ticketTypeId) {
            $ticketType = TicketType::lockForUpdate()->findOrFail($ticketTypeId);
            $quantity = (int) $this->argument('quantity');
            $available = $ticketType->remaining_quantity;

            if ($quantity > $available) {
                $this->error("Only {$available} ticket(s) remaining for {$ticketType->name}.");
                return 1;
            }

            $order = Order::create([
                'user_id' => $this->argument('user_id'),
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
            
            $this->info("Successfully gifted {$quantity} ticket(s) of type {$ticketType->name} to user ID {$this->argument('user_id')}.");
            return 0;
        });
    }
}
