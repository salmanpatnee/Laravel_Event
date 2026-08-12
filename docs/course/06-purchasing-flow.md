# Lesson 06 — Purchasing Flow: Inventory, Transactions, Row Locking

## 1. Goal

Let an attendee purchase tickets for an event, without ever overselling a `TicketType`'s limited
`quantity` — even when two purchases happen at nearly the same instant. By the end of this lesson
you'll have built the first genuinely new business workflow since Lesson 02 (not a refactor), hit
a real concurrency bug on purpose, and fixed it with a database transaction and row-level locking
— and you'll be able to explain *why* the naive version breaks under load even though it looks
correct and passes every single-request test.

## 2. Current State

`Event` has many `TicketType` rows (Lesson 02), each with a `price` and a `quantity` (total tickets
available in that tier). Nothing consumes that `quantity` yet — there's no `Order`, no `Ticket`,
and no way for an attendee to actually buy anything. `quantity` has been purely descriptive so far.

## 3. New Requirement

> "An attendee should be able to select a ticket type on an event's page, choose a quantity, and
> purchase. The purchase must create an `Order` with the correct number of `Ticket` records and
> must never let more tickets be sold for a `TicketType` than its `quantity` allows — including
> when multiple attendees try to buy the last few tickets at the same time."

That last clause is the whole point of this lesson. A version that works perfectly when tested one
request at a time can still oversell in production the moment two people click "buy" within
milliseconds of each other.

## 4. Initial Implementation

### What to build

**Migrations**

- `orders`: `user_id` (FK to `users`, the purchasing attendee), `event_id` (FK to `events`),
  `status` (string — at minimum `pending`/`paid`/`failed`; you decide the full set), `total_amount`
  (decimal, same precision reasoning as `TicketType.price` from Lesson 02), `id` + `timestamps`.
- `tickets`: `order_id` (FK to `orders`, cascade on delete), `ticket_type_id` (FK to
  `ticket_types`), a unique-per-ticket identifier (e.g. a UUID `code` column — think about why an
  auto-increment `id` alone isn't a good thing to hand an attendee as their ticket reference),
  `id` + `timestamps`.

Think about `onDelete` behavior for each foreign key — should deleting an `Event` cascade to
`Order`s the way it cascades to `TicketType`s? Should deleting a `TicketType` be allowed at all
once tickets have been sold against it?

**Models**

- `Order`: `belongsTo(User::class)`, `belongsTo(Event::class)`, `hasMany(Ticket::class)`.
- `Ticket`: `belongsTo(Order::class)`, `belongsTo(TicketType::class)`.

Give `Order` a way to compute its own `total_amount` from the ticket types/quantities being
purchased rather than trusting a client-supplied total — think about why accepting a total amount
from the request body would be a problem.

**A way to know "how many are left"**

`TicketType.quantity` is the *total* capacity, not what's currently available. Decide how you'll
compute remaining inventory: a query counting existing `Ticket` rows for that `TicketType`
(`quantity - tickets_sold`), or a denormalized counter column you increment/decrement. Either is
defensible — pick one and be ready to explain the trade-off (a later section revisits this).

**Purchase route + controller**

Add a form on `events/show.blade.php` (attendee-only — reuse `@can`/role checks from Lessons 03–04
as appropriate) letting an attendee pick a `TicketType` and a quantity, submitting to a new
`store` action — an `OrderController` is a reasonable place for this, but the exact naming is your
call. Validate quantity is a positive integer (Form Request, per Lesson 05).

**Naive purchase logic — build this first, on purpose**

In the controller action:

1. Load the `TicketType`.
2. Compute how many are currently available.
3. If the requested quantity exceeds what's available, reject with an error.
4. Otherwise, create the `Order` and the corresponding `Ticket` rows.

Write this the straightforward way, with no transaction and no locking. It will look correct, and
it will pass any test that only ever does one purchase at a time.

## 5. Problem Appears

Step 4 above is a classic **check-then-act** race condition: "check if enough tickets remain" and
"act by creating the tickets" are two separate database operations with a gap between them. Under
a single request, that gap is invisible. Under concurrent requests, it's exploitable:

- `TicketType` has 1 ticket remaining.
- Attendee A's request reads "1 available" at the same moment Attendee B's request also reads "1
  available" — neither has committed anything yet, so neither read sees the other's in-flight
  purchase.
- Both requests independently conclude "yes, 1 is available," and both proceed to create a
  `Ticket`. The event is now oversold by one.

This isn't a hypothetical edge case — it's the single most common real-world bug in any system
that sells limited inventory (concert tickets, flash sales, seat bookings), and it's exactly the
kind of bug that a synchronous, single-user manual test will never catch, because you can't
trigger it by clicking "buy" once.

**Try to observe it yourself** before reading further: write a Pest test that opens two separate
database connections (or two processes) and deliberately interleaves them — start one purchase's
read, force a delay, let the second purchase's read happen before the first commits, then let both
proceed. With `TicketType.quantity = 1`, confirm two `Ticket` rows get created. That failing test
is your proof the bug is real, not theoretical.

## 6. Concept Introduction

A **database transaction** (`DB::transaction()`) groups multiple queries into a single atomic
unit — either all of them commit, or none do. On its own, a transaction guarantees *consistency*
if something fails partway through, but it does **not**, by itself, prevent two concurrent
transactions from both reading the same "1 available" before either commits — that requires
locking.

**Row-level locking** (`lockForUpdate()` in Eloquent/Query Builder, which issues `SELECT ... FOR
UPDATE`) tells the database to lock the specific row(s) a query reads until the enclosing
transaction commits or rolls back. If Attendee A's transaction locks the `TicketType` row while
computing availability, Attendee B's transaction — also trying to read/lock that same row — has to
*wait* until A's transaction finishes. By the time B's read actually executes, it sees the
inventory *after* A's purchase, not before. This is called **pessimistic locking**: assume
contention will happen and serialize access to the contested resource.

## 7. Why This Solution?

- **Correctness under concurrency is the actual requirement.** "Never oversell" was stated as a
  hard constraint, not a nice-to-have — a solution that only holds under low traffic doesn't
  satisfy it.
- **`lockForUpdate()` is scoped precisely to the contested resource** (the specific `TicketType`
  row), not the whole table — other purchases for *different* ticket types proceed without
  waiting on each other.
- **The transaction boundary matches the business operation.** "Check availability and create the
  tickets" is one indivisible business action; wrapping exactly that in `DB::transaction()` makes
  the code's atomicity guarantee match its actual meaning, rather than being an arbitrary chunk of
  queries.

## 8. Implementation

**Wrap the purchase in a transaction, and lock the row you're checking**

Inside `DB::transaction(function () { ... })`, re-fetch the `TicketType` with `lockForUpdate()`
(e.g. `TicketType::where('id', $id)->lockForUpdate()->first()`) *before* computing availability.
Do the availability check and the `Ticket` creation inside that same locked transaction, so no
other transaction can read the row until this one finishes.

Think about *where* the lock needs to start — locking after you've already read the quantity
elsewhere defeats the purpose. The lock has to cover the exact read your availability decision
depends on.

**Re-run your race-condition test**

With the fix in place, your Pest test from Section 5 should now show the second transaction
blocking until the first commits, and correctly see reduced availability — confirm it can no
longer create more `Ticket` rows than `quantity` allows, even under the forced interleaving.

**Handle the "not enough left" case cleanly**

Decide what happens when the lock resolves and the attendee's requested quantity no longer fits
(someone else bought the remaining tickets while this request was waiting on the lock). This
should be a normal validation-style failure back to the attendee, not an unhandled exception.

## 9. Refactoring

The controller action gains a `DB::transaction()` wrapper and a `lockForUpdate()` call around the
one query whose result the whole operation's correctness depends on — everything else (creating
`Order`/`Ticket` rows, computing the total) stays the same shape it had in the naive version. The
fix is small and localized precisely because the *design* (one clear place where availability is
decided) was already right; only the concurrency guarantee was missing.

## 10. Alternatives

- **Optimistic locking** (a `version`/`updated_at` column checked on write, retry on conflict):
  works well when contention is *rare* and retrying is cheap — but ticket drops for popular events
  are exactly the high-contention case where optimistic locking causes a storm of retries. Not the
  right fit here.
- **A denormalized `quantity_remaining` counter with an atomic `UPDATE ... WHERE quantity_remaining
  >= :n`**: also correct (the `WHERE` clause makes the check-and-decrement atomic at the SQL level
  without an explicit lock), and arguably simpler. Worth comparing directly against
  `lockForUpdate()` — what does each approach make easier or harder later (e.g. auditing exactly
  which tickets were sold, handling partial refunds)?
- **Queueing all purchases through a single serial worker**: guarantees no race by removing
  concurrency entirely, but adds latency and infrastructure complexity that isn't justified yet —
  revisit if `lockForUpdate()` contention itself becomes a measured bottleneck.
- **Application-level cache lock (e.g. Redis `Cache::lock()`)**: useful when the contested resource
  isn't naturally a database row, or spans multiple data stores — not needed here since the
  resource *is* a database row and the database's own locking primitive fits directly.

## 11. When Not To Use It

Don't wrap every write in `DB::transaction()` + `lockForUpdate()` reflexively — locking has a real
cost (other transactions wait) and is only justified when a genuine check-then-act race exists on
a *shared, contended* resource. A single-row update with no prior read-then-decide step (e.g.
updating an event's `description`) has nothing to race against and needs neither.

## 12. Practice

1. Implement the migrations, models, route, controller, and the naive purchase flow first —
   confirm it works for a single request before introducing any locking.
2. Write the race-condition test described in Section 5 and confirm it demonstrates overselling
   *before* you add the fix.
3. Add `DB::transaction()` + `lockForUpdate()` and confirm the same test now passes.
4. Stretch goal: implement the denormalized-counter alternative from Section 10 as well, run the
   same race-condition test against it, and write a short comparison of the two approaches.

## 13. Review Questions

1. Why does wrapping the purchase in `DB::transaction()` alone — without `lockForUpdate()` — fail
   to prevent overselling?
2. What SQL does `lockForUpdate()` actually generate, and what does the database do differently
   when a second transaction tries to read that same locked row?
3. What's the practical difference between pessimistic locking (`lockForUpdate()`) and optimistic
   locking (version-checked writes), and what property of *this* problem makes pessimistic the
   better fit?
4. If two different attendees try to buy tickets for two *different* `TicketType`s on the same
   `Event` at the same time, does `lockForUpdate()` as implemented here block one on the other? Why
   or why not — and is that the right behavior?
5. How would you write an automated test that reliably reproduces a race condition, given that
   PHP request handling in tests is normally single-threaded/sequential?

## 14. Takeaways

- A check-then-act sequence ("read a value, then decide based on it, then write") is a race
  condition waiting to happen the moment more than one process can run it concurrently — and it
  will look completely correct in every test that doesn't specifically force concurrency.
- `DB::transaction()` guarantees atomicity (all-or-nothing), not exclusivity (nobody else reads the
  data meanwhile) — those are different guarantees, and inventory-safety needs the second one.
- `lockForUpdate()` scopes contention to exactly the row(s) actually contested — precision here is
  what keeps unrelated purchases (different ticket types, different events) from blocking on each
  other unnecessarily.

---

## Interview Preparation

### What Interviewers May Ask

- "How would you prevent overselling limited inventory under concurrent requests?"
- "What's the difference between a database transaction and row locking?"
- "What does `lockForUpdate()` do, and what SQL does it generate?"
- "Pessimistic vs. optimistic locking — when would you choose each?"
- "How do you test for a race condition in an application that's normally request-per-thread?"

### What the Interviewer Is Testing

Whether you can recognize a check-then-act race condition by pattern (not just recite
"lockForUpdate prevents overselling"), whether you understand that transactions and locks solve
different problems, and whether you can reason about the trade-off between correctness under
contention and the cost locking imposes on throughput.

### How I Should Answer

Name the failure mode precisely: two concurrent reads of the same "available" value, both
completing before either write commits, so both proceed as if the other doesn't exist. Explain that
a transaction alone doesn't stop this — it only guarantees the queries *inside* one transaction
succeed or fail together, not that no other transaction can read the same row meanwhile. Then
explain `lockForUpdate()` concretely: it issues `SELECT ... FOR UPDATE`, which holds a row-level
lock until the transaction ends, forcing a second transaction requesting the same lock to wait —
so by the time it reads, it sees the first transaction's committed result. Mention the
optimistic-locking alternative and why high-contention scenarios (a popular event selling out)
favor pessimistic locking's guaranteed correctness over optimistic locking's retry storms.

### Real Interview Scenario

> "Your ticketing system sold 105 tickets for an event with 100 seats. How do you find the bug, and
> how do you fix it?"

A strong candidate identifies this as a classic overselling race condition, looks for a
check-then-act pattern around the inventory check (not a validation bug — the individual requests
were each "valid" in isolation), and proposes wrapping the availability check and ticket creation
in a transaction with `lockForUpdate()` on the ticket-type row. A senior candidate also mentions
*how they'd prove it*: writing a test that deliberately interleaves two transactions rather than
trusting a manual retest, since the bug is invisible under sequential testing by construction.

### Interview Difficulty

**Mid–Senior.** Recognizing that a bug exists at all requires understanding concurrency, not just
Laravel syntax — junior candidates often propose "just add validation," which doesn't address the
actual race. Correctly explaining *why* a transaction alone is insufficient, and reasoning about
lock granularity and contention trade-offs, is senior-level judgment.

---

## Laravel Interview Checklist

- Can you explain the difference between what `DB::transaction()` guarantees and what
  `lockForUpdate()` guarantees?
- Can you identify a check-then-act race condition in code that has no obvious bug on a single
  read-through?
- Can you explain pessimistic vs. optimistic locking and pick the right one for a given contention
  profile?
- Do you know what SQL `lockForUpdate()` generates, and what happens to a second transaction that
  requests the same lock?
- Can you describe how you'd write a test that actually proves a race condition is fixed, not just
  that the happy path still works?
