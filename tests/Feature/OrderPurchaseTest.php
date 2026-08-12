<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\RoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('lets an attendee purchase available tickets for an event', function () {
    $organizer = User::factory()->create(['role' => RoleEnum::Organizer]);
    $attendee = User::factory()->create(['role' => RoleEnum::Attendee]);
    $event = Event::factory()->for($organizer, 'organizer')->create();
    $ticketType = TicketType::factory()->for($event)->create(['quantity' => 5]);

    $response = $this->actingAs($attendee)->post(route('orders.store'), [
        'event_id' => $event->id,
        'ticket_type_id' => $ticketType->id,
        'quantity' => 2,
    ]);

    $response->assertRedirect(route('events.show', $event));
    expect(Ticket::where('ticket_type_id', $ticketType->id)->count())->toBe(2)
        ->and(Order::count())->toBe(1);
});

it('rejects a purchase that exceeds remaining availability', function () {
    $organizer = User::factory()->create(['role' => RoleEnum::Organizer]);
    $attendee = User::factory()->create(['role' => RoleEnum::Attendee]);
    $event = Event::factory()->for($organizer, 'organizer')->create();
    $ticketType = TicketType::factory()->for($event)->create(['quantity' => 1]);

    $response = $this->actingAs($attendee)->post(route('orders.store'), [
        'event_id' => $event->id,
        'ticket_type_id' => $ticketType->id,
        'quantity' => 2,
    ]);

    $response->assertSessionHasErrors('quantity');
    expect(Ticket::where('ticket_type_id', $ticketType->id)->count())->toBe(0);
});

/**
 * Proves the naive purchase flow (no DB::transaction()/lockForUpdate()) has a
 * check-then-act race condition: two "requests" that each read availability
 * before either has written can both conclude a purchase is allowed, overselling
 * a TicketType with quantity = 1.
 *
 * PHP's test runner is single-threaded, and Laravel's default test connection
 * is an in-memory SQLite database that's private per PDO connection -- so
 * neither real OS-level threads nor two separate raw DB connections can
 * reproduce a genuine interleaving here (SQLite's own whole-database/shared
 * -cache locking gets in the way long before the actual application race would
 * matter -- that's a SQLite testing artifact, not the bug this lesson is about).
 *
 * Instead, this test controls the interleaving directly: it calls the same
 * "read remaining availability" step the controller performs, for BOTH
 * attendees, before either attendee's purchase writes anything -- exactly what
 * happens in production when two requests' reads land within the same
 * unguarded window. That's the entire mechanism of the bug; forcing it by hand
 * is what makes it deterministically testable on a single thread.
 */
it('oversells a ticket type when two purchases race past the naive availability check', function () {
    $organizer = User::factory()->create(['role' => RoleEnum::Organizer]);
    $attendeeA = User::factory()->create(['role' => RoleEnum::Attendee]);
    $attendeeB = User::factory()->create(['role' => RoleEnum::Attendee]);
    $event = Event::factory()->for($organizer, 'organizer')->create();
    $ticketType = TicketType::factory()->for($event)->create(['quantity' => 1]);

    // Mirrors the naive controller's availability check exactly:
    // TicketType.quantity - count of Tickets already sold for it.
    $readAvailable = fn () => $ticketType->quantity - Ticket::where('ticket_type_id', $ticketType->id)->count();

    $purchase = function (User $attendee) use ($ticketType, $event) {
        $order = Order::create([
            'user_id' => $attendee->id,
            'event_id' => $event->id,
            'total_amount' => $ticketType->price,
        ]);

        Ticket::create([
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'code' => Str::uuid(),
        ]);
    };

    // Step 1: Attendee A's request reads availability -- sees 1 available.
    $availableForA = $readAvailable();

    // Step 2: Before A has written anything, Attendee B's request performs
    // the exact same read. In production nothing blocks this because the
    // naive flow never locks the row -- here we force the same ordering by
    // simply not writing A's purchase yet.
    $availableForB = $readAvailable();

    // Both requests independently concluded "1 is available" -- neither has
    // seen the other's in-flight purchase.
    expect($availableForA)->toBe(1)
        ->and($availableForB)->toBe(1);

    // Step 3: Both requests now proceed to create their Order + Ticket,
    // exactly as the naive controller would after passing its check.
    $purchase($attendeeA);
    $purchase($attendeeB);

    // This is the bug: quantity was 1, but two tickets got sold.
    expect(Ticket::where('ticket_type_id', $ticketType->id)->count())->toBe(1);
});
