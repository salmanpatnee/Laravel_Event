<?php

namespace App\Console\Commands;

use App\Exceptions\TicketUnavailableException;
use App\Services\TicketOrderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;

#[Signature('app:gift-ticket {event_id : The ID of the event} {ticket_type_id : The ID of the ticket type} {quantity : The number of tickets to gift} {user_id : The ID of the user to gift the tickets to}')]
#[Description('Comp free VIP tickets to specific attendees — sponsors, staff, contest winners.')]
class GiftTicket extends Command implements PromptsForMissingInput
{
    /**
     * Execute the console command.
     */
    public function handle(TicketOrderService $ticketOrderService): int
    {
        $this->info("Gifting {$this->argument('quantity')} ticket(s) of type {$this->argument('ticket_type_id')} for event {$this->argument('event_id')}.");

        $ticketTypeId = $this->argument('ticket_type_id');
        $quantity = $this->argument('quantity');
        $userId = $this->argument('user_id');

        try {
            $ticketOrderService->order($ticketTypeId, (int) $quantity, (int) $userId);
        } catch (TicketUnavailableException $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->info("Successfully gifted {$quantity} ticket(s) to user {$userId}.");

        return Command::SUCCESS;
    }
}
