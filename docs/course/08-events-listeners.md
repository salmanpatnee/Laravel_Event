# Lesson 08 — Events & Listeners: `OrderPlaced`

## 1. Goal

Decouple "an order was placed" from "everything that should happen because of it," using Laravel
Events and Listeners. By the end of this lesson, `TicketOrderService` will announce a fact
(`OrderPlaced`) and know nothing about what happens next — while two independent listeners react to
that fact without knowing about each other, or being edited together every time a new reaction is
needed.

## 2. Current State

`TicketOrderService::order()` (Lesson 07) does exactly one job: lock the `TicketType` row, check
availability, create the `Order` and `Ticket` rows, and return the `Order`. Nothing happens after
that — `OrderController::store()` and `GiftTicket` each get the `Order` back and do their own
transport-specific thing (redirect, console output). No audit trail exists, and nothing tells an
organizer that tickets sold or were gifted for their event.

## 3. New Requirement

> "Every time an order is placed — whether paid or gifted — the platform needs to record an audit
> log entry: who received it, for which event and ticket type, how many tickets, and whether it was
> a paid purchase or a comp. Separately, the platform needs to prepare the ground for a confirmation
> email to the attendee — actually sending real email is a later concern, but for now, something
> needs to clearly signal 'a confirmation would be sent here' so the next lesson has an obvious seam
> to build on."

Notice this describes **two independent reactions** to the same fact. Neither has anything to do
with *how* the order was placed (web checkout vs. console gift), and neither should require
`TicketOrderService` to know it exists.

## 4. Initial Implementation

Write it the direct way first: inside `TicketOrderService::order()`, right after the `Ticket`
creation loop (still inside the `DB::transaction()` closure), add the audit log line and the
"would send confirmation" log line. Something like two `Log::info(...)` calls with the relevant
order/ticket-type/user details as structured context.

Run your existing tests to confirm purchasing and gifting still work exactly as before — this
version is functionally correct, and that's exactly what makes the problem easy to miss.

## 5. Problem Appears

Two separate problems, worth naming individually:

**Coupling.** `TicketOrderService` now has to know about auditing *and* notifications — two
concerns that have nothing to do with "safely reserve inventory and create records." When a third
reaction shows up (Lesson 10's real confirmation email, or a future "notify organizer's Slack"
integration), you edit this file again. That's the exact growth pattern Lesson 07 fixed for
`OrderController` — now recurring one layer deeper, inside the class that was supposed to be the
fix.

**Side effects inside the lock.** Both log calls run *while the `TicketType` row lock from Lesson 06
is still held* — they're inside the same `DB::transaction()` closure. Logging is cheap today, but
the pattern isn't: anything added later inside that closure (a slow API call, a real mail send)
extends exactly the contention window Lesson 06 worked to minimize. And if the transaction ever
rolled back *after* a listener's side effect already ran, you'd have recorded something that never
actually happened.

## 6. Concept Introduction

A Laravel **Event** is a plain class representing a fact that occurred — here, `OrderPlaced`,
carrying the `Order` that was placed. Dispatching it (`OrderPlaced::dispatch($order)`) doesn't say
*what* should happen next; it just announces that something did.

A **Listener** is a class with a `handle()` method type-hinted to the event it cares about. Laravel
13 auto-discovers listeners by scanning `app/Listeners` — any `handle()` (or `__invoke()`) method
type-hinted with an event class is wired up automatically. No manual registration file, no
`EventServiceProvider` array to maintain.

Multiple listeners can react to the same event without knowing about each other. `TicketOrderService`
ends up depending on nothing except "an event class exists" — it has no idea how many listeners
respond to it, or what they do.

## 7. Why This Solution?

- **The service goes back to doing one job.** It persists an order safely; it does not orchestrate
  everything that should follow. That's the same separation-of-concerns argument from Lesson 07,
  applied to a new place it was starting to leak back in.
- **New reactions don't require editing existing code.** Lesson 10's real confirmation email becomes
  a new (or upgraded) listener, not a new line inside `TicketOrderService`.
- **Dispatching after the transaction commits — not inside the closure — means side effects only
  ever run against work that's actually persisted**, and the row lock is released before any of them
  run, not held open for their duration.

## 8. Implementation

### Task

Create an `OrderPlaced` event, two listeners, and move the audit/confirmation-stub logic out of
`TicketOrderService` into them.

### Instructions

- `php artisan make:event OrderPlaced`. Give it a single public property carrying the `Order` that
  was placed (constructor-promoted, matching this project's PHP conventions).
- In `TicketOrderService::order()`, remove the two `Log::info()` calls you added in Section 4.
  Dispatch `OrderPlaced::dispatch($order)` **after** `DB::transaction()` returns — not inside the
  closure. Think about why the closure's return value makes this straightforward: the transaction
  has already committed successfully by the time execution reaches the line after it.
- `php artisan make:listener LogOrderAudit --event=OrderPlaced` (or your own naming — the point is
  one listener, one responsibility). Its `handle(OrderPlaced $event)` writes a structured log entry
  (use `Log::info('order.placed', [...])` with an array of context, not string concatenation —
  structured logging is what makes log entries queryable/filterable later).
- `php artisan make:listener LogOrderConfirmationStub --event=OrderPlaced` (or your own naming).
  Its `handle()` logs a clear line stating a confirmation email *would* be sent here, including
  which attendee/order — this is intentionally a stand-in, not real mail (that's Lesson 10).
- Confirm with `php artisan event:list` that both listeners are discovered against `OrderPlaced`
  without you registering anything manually.
- Re-run the full suite, and manually trigger both `OrderController::store()` (via the browser or a
  feature test) and `GiftTicket` — confirm both log lines appear for *both* entry points, proving
  the listeners don't care how the order was created.

### How This Should Be Approached

Each listener should depend only on the `OrderPlaced` event and whatever it needs to do its own job
(e.g. `Log` facade) — not on `TicketOrderService`, not on `OrderController`, not on each other.
`TicketOrderService` should end up with exactly one new line (the `dispatch()` call) and two fewer
(the `Log::info()` calls that moved out). Don't reach for `ShouldQueue` yet — keep both listeners
synchronous for this lesson; Lesson 09 is specifically about deciding which of them, if either,
deserves to run asynchronously.

## 9. Refactoring

`TicketOrderService::order()` shrinks back down to persistence + locking + one `dispatch()` call.
The two new listener classes are small, single-purpose, and live entirely outside the transaction
boundary. Nothing about `OrderController` or `GiftTicket` changes — proof that this refactor is
fully contained behind `TicketOrderService`'s existing public contract.

## 10. Alternatives

- **Eloquent Observers** (`Order::created()` lifecycle hook): would work for the audit log
  specifically, since it's tied 1:1 to `Order` creation — but it fires on *any* `Order::create()`
  call anywhere in the codebase (a future admin backfill script, a factory in an unrelated test,
  a seeder), which isn't always what you want. Events give you an explicit, intentional dispatch
  point instead of an implicit hook on persistence — worth naming the trade-off even if you don't
  choose it.
- **Direct method calls from the service** (`$this->logAudit($order)`, `$this->stubConfirmation($order)`
  as private methods): marginally better organized than Section 4's version, but doesn't solve the
  actual scaling problem — every new reaction still means editing `TicketOrderService`.
- **Queued listeners** (`ShouldQueue`): the natural next step, deliberately deferred to Lesson 09 so
  the Event/Listener concept isn't tangled up with the Queue concept in the same lesson.

## 11. When Not To Use It

If there's exactly one thing that must happen after an action, it's simple, and it needs to be able
to *veto* or affect the calling code's outcome (e.g., it must run inside the same transaction, or
its failure should roll back the order), don't reach for an event — events are fire-and-forget by
default, and a listener throwing doesn't undo an already-committed transaction. A direct method call
communicates that tight coupling honestly; an event would hide it.

## 12. Practice

1. Implement `OrderPlaced` and both listeners per Section 8.
2. Confirm `php artisan event:list` shows both listeners wired to `OrderPlaced` with no manual
   registration.
3. Trigger a purchase through the browser and a gift through `GiftTicket`, and confirm both listeners
   fire for both — check your log output (`storage/logs/laravel.log`, since `MAIL_MAILER=log` and the
   default log driver both write there).
4. Re-run `php artisan test --compact` and confirm nothing regresses.
5. Stretch goal: write a test using `Event::fake()` that asserts `OrderPlaced` was dispatched with
   the correct `Order` after a purchase, without asserting on log output directly — this is the
   standard way to test that an event fired without coupling the test to what listeners currently do.

## 13. Review Questions

1. Why does dispatching `OrderPlaced` *after* `DB::transaction()` returns matter, rather than
   dispatching it as the last line inside the closure?
2. What would happen if `LogOrderConfirmationStub`'s listener threw an exception — does it stop
   `LogOrderAudit` from running, and does it affect the `Order` that was already committed?
3. What's the practical difference between an Event/Listener pair and an Eloquent Observer, and
   what made Events the better fit for the audit-log requirement specifically?
4. How does Laravel know to call `LogOrderAudit::handle()` when `OrderPlaced` is dispatched, given
   that nothing was manually registered?
5. If `LogOrderConfirmationStub` becomes a real mail-sending listener next lesson and the mail
   server is slow, what breaks right now with it running synchronously — and what does that tell
   you about what Lesson 09 needs to solve?

## 14. Takeaways

- Events let a class announce a fact without knowing or caring what happens as a result — that's
  the actual mechanism of decoupling, not just "fewer lines in one file."
- Side effects (logging, notifications, anything not required for the core operation to succeed)
  belong outside the transaction/lock boundary that guarantees the core operation's correctness.
- Multiple listeners reacting to one event can be added or removed independently — none of them,
  and none of the code that dispatches the event, needs to change when a sibling listener is added.

---

## Interview Preparation

### What Interviewers May Ask

- "When would you use an Event/Listener instead of just calling a method directly?"
- "How does Laravel know which listener handles which event?"
- "What's the difference between an Event and an Observer?"
- "Why shouldn't you perform side effects like sending email inside a database transaction?"
- "What happens if a listener throws an exception — does it affect other listeners, or the code
  that dispatched the event?"

### What the Interviewer Is Testing

Whether you understand events as a decoupling mechanism (not just "a Laravel feature to know
about"), whether you reason correctly about transaction boundaries and side effects, and whether
you can distinguish similar-looking tools (Events vs. Observers vs. direct calls) by the actual
trade-off, not just definition recall.

### How I Should Answer

Lead with the coupling problem: without events, the code that performs an action has to know about
everything that should happen afterward, and that list only grows. Events invert that — the action
announces a fact, and anything interested can react without the original code changing. Explain the
transaction-boundary point concretely: dispatching after commit means listeners only ever act on
data that's actually persisted, and don't hold locks open longer than necessary. For Observers,
explain they're implicit (tied to model lifecycle events) versus Events being an explicit, deliberate
signal — useful when you don't want every possible code path that creates a model to trigger the
same reaction.

### Real Interview Scenario

> "Every time a user signs up, we need to send a welcome email, provision a default workspace, and
> notify the sales team. Right now all three happen inline in the registration controller. How would
> you refactor this, and what would you watch out for?"

A strong candidate identifies this as the same shape as `TicketOrderService`'s problem: three
unrelated reactions to one fact, currently coupled to the action that produces it. They'd propose a
`UserRegistered` event with three listeners, dispatched after the user record is actually committed.
A senior candidate flags that "send a welcome email" and "notify sales" are good queued-listener
candidates (external I/O, shouldn't block the response) while "provision a default workspace" might
need to stay synchronous if the next page load depends on the workspace already existing — showing
they know Events and Queues are related but separate decisions.

### Interview Difficulty

**Mid–Senior.** The mechanics (`make:event`, `make:listener`, auto-discovery) are junior-accessible.
Reasoning about transaction boundaries, when *not* to use events, and the Event-vs-Observer trade-off
is what separates a mid-level answer from a senior one.

---

## Laravel Interview Checklist

- Can you explain why dispatching an event after a transaction commits matters, not just as a style
  choice?
- Can you explain how Laravel resolves which listeners run for a given event without manual
  registration?
- Can you articulate when an Observer is a better fit than an Event, and vice versa?
- Do you know what happens (by default) when a synchronous listener throws an exception?
- Can you explain why sending email or calling external services shouldn't happen inside a database
  transaction?
