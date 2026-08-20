# Lesson 12 — Refunds & Cancellation: Observer vs Explicit Logic

## 1. Goal

Let an attendee cancel an order before the event happens, record that the order was refunded, and
free up the ticket inventory it was holding — once, correctly, no matter who or what triggers the
cancellation. By the end of this lesson you'll have made a deliberate call on *where* "what happens
when an order is cancelled" should live: reacting automatically to a model's state changing (an
Eloquent Observer), or as one explicit action every caller has to go through (a service method) —
and you'll know which one this app's current shape actually justifies.

## 2. Current State

`TicketOrderService::order()` (Lesson 06/07) is the only way an order gets created — it locks the
`TicketType` row, checks `remaining_quantity`, creates the `Order`, creates one `Ticket` per unit,
and dispatches `OrderPlaced` (Lesson 08) inside a transaction. `OrderStatusEnum` already has three
cases — `Pending`, `Confirmed`, `Cancelled` — but nothing in the app ever sets an order to
`Cancelled`; the enum case exists without a code path that reaches it. `TicketType::remainingQuantity()`
computes availability as `quantity - tickets()->count()` — it counts *every* ticket ever created for
that type, regardless of what order it belongs to or that order's status. There is no `Refund` model,
no refunds table, and no controller action, route, or policy for cancelling an order.

## 3. New Requirement

> "Attendees should be able to cancel an order for an event that hasn't happened yet. Cancelling
> refunds them and puts those tickets back into the available pool for other attendees to buy."

## 4. Initial Implementation

Add a `cancel` action to `OrderController` that loads the order and does the simplest thing that
could possibly work: set `status` to `Cancelled` and save. Wire up a route, hit it for a real order,
then try to buy the *same ticket type* back up to its original `quantity`.

Notice what happens: you can't. `remaining_quantity` is still counting the tickets that belong to
the order you just cancelled, so the inventory never actually came back. Now cancel the same order a
second time — nothing stops you, and if you'd also fired off a refund notification in this naive
version, the attendee would get a second "you've been refunded" email for an order that was already
cancelled.

## 5. Problem Appears

Three separate problems are visible here, and they're worth naming separately before reaching for a
fix.

**Inventory doesn't actually get released.** `remaining_quantity` was written assuming every ticket
row represents a live, held seat — that assumption breaks the moment a ticket's order can be
cancelled without the ticket itself disappearing.

**Cancelling isn't idempotent.** Nothing prevents cancelling an already-cancelled order, which is the
same shape of bug Lesson 11 solved for reminders — a status transition needs a guard, not just a
write.

**There's no single obvious place this logic should live.** Cancellation isn't only going to be
triggered by an attendee clicking "cancel" — an organizer might force-cancel an order, a future
admin panel might do it, a cleanup command might auto-cancel stale pending orders. Every one of those
call sites needs the *same* guarantees (idempotent, releases inventory, records a refund, notifies
the attendee) — which raises the real question this lesson is about: do you enforce that by making
every caller go through one explicit method, or by having the `Order` model itself react whenever its
`status` becomes `Cancelled`, no matter who set it?

## 6. Concept Introduction

Laravel **Model Observers** (`php artisan make:observer`) let a model react to its own Eloquent
lifecycle events — `creating`, `updating`, `deleted`, and so on — from one class registered against
the model, without every place that touches the model needing to know what should happen next. An
Observer on `Order` could watch for `status` becoming `Cancelled` and, from that one place, release
inventory, create a `Refund`, and dispatch a notification — automatically, everywhere the model gets
saved that way.

The alternative is what `TicketOrderService::order()` already does for order *creation*: one explicit
method that is the single sanctioned entry point for the action, called deliberately by whoever wants
to perform it. Nothing happens "automatically" — if you don't call the method, cancellation didn't
happen.

These aren't just style preferences; they fail differently. An Observer reacts to a **model event**,
not to a **business action** — and Eloquent's model events only fire when you actually load and save
a model instance. A bulk update like `Order::where(...)->update(['status' => 'cancelled'])` — exactly
the kind of thing a cleanup command is tempted to write — skips model events entirely, so an Observer
silently never runs, and nothing about that failure is visible at the call site. An explicit service
method has the opposite failure mode: it only does its job if every caller remembers to call it
instead of touching `status` directly — which is a discipline problem, not a framework gotcha, but a
real one once there are several call sites.

## 7. Why This Solution?

- **This app has exactly one intended way to reach `Cancelled` right now: an attendee cancelling
  their own order.** There's no evidence yet of the multi-call-site pressure that would justify an
  Observer's "no matter who touches it" guarantee — that's a real future need (Section 11), not a
  current one.
- **Cancellation composes the same guarantees creation does** — a locked, transactional unit of work
  that changes state, touches inventory, and fires a domain event other things react to. Modeling it
  as another explicit service method keeps that symmetry with `TicketOrderService::order()` instead of
  introducing a second, different mechanism for the model's other lifecycle phase.
- **Explicit methods are traceable in a way Observers aren't.** Grepping for a call to
  `cancel()` finds every place cancellation can happen. Grepping for `status = Cancelled` does not
  reliably find "and then this Observer ran," especially months later when nobody remembers the
  Observer exists — a real cost when you're the one debugging why a refund didn't fire.

## 8. Implementation

### Task

Build an idempotent cancel-and-refund action that an attendee can trigger for their own order, that
frees the ticket inventory it held and notifies them once.

### Instructions

- Design a minimal `Refund` model/migration: `order_id`, `amount`, and enough to say *when* it
  happened (`refunded_at` or rely on `created_at` — decide which, and be able to explain why). This
  course hasn't introduced a real payment gateway, so treat a refund as internal bookkeeping only —
  don't build out a payment-provider integration that isn't part of this requirement.
- Add a `Policy` method (recall Lesson 04) so only the order's owner can cancel it — decide whether an
  organizer should also be allowed to cancel orders for their own events, and if so, express that in
  the policy rather than in the controller.
- Add the route + controller action. Keep the controller thin — its job is authorization + calling
  one service method, not deciding what cancellation *means*.
- Add a `cancel()` method to `TicketOrderService` (or a sibling class if you decide the responsibility
  doesn't belong on the same class — see Section 9) that, inside a transaction: locks the order,
  **guards against cancelling an order that isn't `Pending`/`Confirmed`** (idempotency — cancelling an
  already-`Cancelled` order should be a no-op or a rejected request, your call, but it must not
  refund/notify twice), sets `status` to `Cancelled`, creates the `Refund` record, and dispatches a
  new `OrderCancelled` event mirroring `OrderPlaced`'s shape from Lesson 08.
- Fix `TicketType::remainingQuantity()` to exclude tickets whose order has been cancelled — the
  ticket rows themselves should stay (they're the audit trail of what was issued), only the
  *availability calculation* needs to stop counting them.
- Decide: should a cancelled ticket's `code` ever be reissued to a new buyer? Reason about door
  check-in and fraud — a code that already went out (even if refunded before the event) probably
  shouldn't be resurrected under the same identifier.
- Add a listener on `OrderCancelled` that sends a notification (`make:notification RefundProcessed`
  or similar), following the same shape as `EventReminder`/`OrderConfirmation` — reuse the pattern,
  don't invent a new one.

### How This Should Be Approached

Treat `cancel()` the same way you treat `order()`: one method that is the entire truth of what
"cancelling an order" means in this app. Nothing about status, inventory, refunds, or notification
should be decided anywhere else. If you find yourself wanting to set `$order->status =
Cancelled` directly from the controller "just this once," that's the signal this lesson is about —
resist it, and route through the method instead.

## 9. Refactoring

Decide whether `cancel()` belongs on `TicketOrderService` alongside `order()`, or on a new
`RefundService`. Both methods operate on the same `Order`/`TicketType` pair and share the
lock-then-transact shape, which argues for keeping them together. But they're different lifecycle
phases with different future growth directions — creation might grow discount codes and
multi-ticket-type carts; cancellation might grow partial refunds and time-based refund policies ("no
refund within 24 hours of the event"). If cancellation logic starts pulling in concerns creation
never needed, that's the signal to split — the same "wait for the class to actually justify it" rule
from Lesson 07, not a decision to make preemptively today.

## 10. Alternatives

- **An `Order` Observer reacting to `status` becoming `Cancelled`**: as covered in Section 6, this
  silently doesn't run against bulk `update()` queries, and hides the trigger of a real side effect
  (refunding money, notifying a user) behind a model lifecycle hook instead of an explicit call. Worth
  revisiting once there are multiple genuine call sites (organizer force-cancel, an automated
  stale-order sweep) all needing the identical guarantee — at that point an Observer (or a single
  shared service method all of them call, which solves the same problem without the bulk-update trap)
  becomes the right conversation to have again.
- **Soft-deleting the order (`SoftDeletes`) instead of a `Cancelled` status**: rejected. A cancelled
  order is a real, permanent business record that dashboards and reporting need to query directly —
  `SoftDeletes` communicates "this row shouldn't normally be visible," which is the wrong signal for
  a terminal, still-relevant state.
- **Deleting the `Ticket` rows on cancellation instead of filtering them out of
  `remaining_quantity`**: rejected — it destroys the audit trail of what was originally issued
  (useful for support disputes, fraud review, and understanding sales history), for no real benefit
  over filtering by the parent order's status in the query.

## 11. When Not To Use It

Don't reach for an Observer just because "this model changes state and something should happen" —
that description fits almost any status transition in this app. An Observer earns its place only when
multiple independent call sites genuinely need the same guarantee and can't all be trusted to route
through one shared method — not as a default way to react to model changes. Until this app actually
has more than one place that cancels an order, an explicit method is simpler, more traceable, and
just as correct.

## 12. Practice

1. Add the `refunds` migration/model and the `Order`/`Refund` relationship.
2. Add the cancellation policy, route, and controller action.
3. Implement `cancel()` (idempotent: cancelling twice must not double-refund or double-notify).
4. Fix `remainingQuantity()` to exclude cancelled orders' tickets, and confirm a cancelled order's
   inventory becomes purchasable again.
5. Add `OrderCancelled`, a listener, and a refund notification, following Lesson 08/10's shape.
6. Test: cancel an order, confirm the ticket type's `remaining_quantity` goes back up by the right
   amount, confirm a second cancel attempt on the same order is rejected or a no-op, and confirm only
   one notification was sent.

## 13. Review Questions

1. What's the concrete difference between reacting to a model's state changing (Observer) and
   requiring an explicit method call — and which failure mode does each one risk?
2. Why does `Order::where(...)->update(...)` not trigger an Observer, and why does that matter for a
   cleanup command?
3. Why does `remainingQuantity()` need to change, when nothing about `Ticket` itself changed?
4. Why keep cancelled tickets' rows instead of deleting them?
5. What would have to be true about this app before an Observer became the better choice for
   cancellation?

## 14. Takeaways

- Observers and explicit method calls both enforce "this side effect always happens" — they differ in
  *what* triggers them: a model's saved state (Observer) versus a deliberate call (explicit method) —
  and only the latter is guaranteed to run against bulk query-builder updates.
- An inventory or availability calculation that was written assuming one meaning of "exists" (every
  ticket row is live) breaks the moment that assumption changes (a ticket's order can be cancelled) —
  changing the write path always means re-checking every read path that assumed the old invariant.
- Reach for an Observer when multiple real call sites need an identical guarantee you can't trust
  every caller to remember — not as a default reaction to "this model's state changes."

---

## Interview Preparation

### What Interviewers May Ask

- "When would you use an Eloquent Observer versus putting logic in a service method?"
- "Why doesn't a mass `update()` call trigger model events — and why does that matter?"
- "How would you make a status transition like 'cancel this order' safe to call twice?"
- "If cancelling an order needs to release inventory, refund money, and notify someone, where does
  that logic live?"

### What the Interviewer Is Testing

Whether you reach for Observers as a reflex ("this model changes, so use an Observer") or as a
deliberate choice justified by multiple untrusted call sites — and whether you know the specific,
concrete gotcha (model events not firing on bulk updates) rather than a vague sense that "Observers
can be tricky."

### How I Should Answer

Name the real trade-off: Observers centralize a reaction to *any* way a model's state changes, which
is powerful exactly when you can't guarantee every caller goes through the same code path — but they
only fire on Eloquent model events, so a raw query-builder `update()` bypasses them entirely, which is
a genuine production bug people hit. Explicit service methods are more traceable (grep finds every
caller) but only work if you enforce that nothing bypasses them — which is a code-review discipline,
not something the framework guarantees for you. For idempotency, name the actual mechanism: a status
guard checked before performing the transition, inside the same transaction that performs it, not
"check first, act later" as two separate steps.

### Real Interview Scenario

> "Marketing wants to bulk-refund every attendee of an event that got cancelled by the venue. How
> would you build that, and what's the trap?"

A weaker answer writes `Order::where('event_id', $id)->update(['status' => 'cancelled'])` and assumes
refunds and notifications "just happen" because there's an Observer on `Order`. The trap is exactly
that: the bulk update never touches individual model instances, so the Observer never fires, no
refunds get created, and no attendee gets notified — a silent, hard-to-detect failure. A senior answer
either loops over the affected orders and calls the same explicit `cancel()` method used everywhere
else (paying the N-query cost deliberately, in a queued job if the event is large), or is honest that
an Observer-based design needs `updated()`-based bulk-safe alternatives (`each()`/chunked model
saves) — either way, the answer demonstrates knowing *why* the naive bulk query silently does nothing.

### Interview Difficulty

**Mid–Senior.** Registering an Observer is junior-accessible. Knowing that bulk `update()` skips model
events — and reasoning about which trigger mechanism survives that trap — is where this becomes a
senior conversation, the same "the framework has a sharp edge here that only shows up under a specific
call pattern" instinct as Lesson 09's queue-worker-crash discussion.

---

## Laravel Interview Checklist

- Can you state, precisely, when Eloquent model events do and don't fire?
- Can you name a concrete guarantee an Observer gives you that an explicit method doesn't, and vice
  versa?
- Can you design an idempotency guard for a status transition, not just a happy path?
- Can you explain why an availability/inventory calculation needs to be revisited when a new terminal
  state (cancellation) is introduced?
- Can you justify, for this specific app, why explicit-over-Observer is the right call *right now* —
  and name the condition that would flip that answer?
