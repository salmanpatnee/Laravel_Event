# Memory — Laravel Progressive Architecture Training Course

Last updated: 2026-08-20

## What was built

- **Lesson 11 marked "Implemented"** in `docs/course/README.md` roadmap table (was stuck at "Ready"
  despite code being done and committed in a prior session — `ddf04b8`, `cf8d6a0`).
- **Lesson 12 drafted**: `docs/course/12-refunds-cancellation.md` — "Refunds & Cancellation: Observer
  vs Explicit Logic." Full lesson structure (Goal → Takeaways + Interview Prep), no implementation
  code included, per course rules. Linked into `docs/course/README.md` roadmap as "Ready" (was
  "Planned").
- Committed both files as `d54008d` ("Mark Lesson 11 implemented, draft Lesson 12: Refunds &
  Cancellation") and pushed to `origin/main`. `origin/main` was already current through Lesson 11's
  code commits before this session — this push only added the README status change and the new
  lesson doc.

## Decisions made

- **Lesson 12's core requirement**: attendee can cancel an order before the event starts; cancelling
  refunds them and releases the ticket inventory back to the pool.
- **Lesson 12's chosen concept resolution (to be validated against the user's actual implementation)**:
  explicit method (`cancel()` on `TicketOrderService`, mirroring `order()`) over an Eloquent Observer
  on `Order`, reasoned as: this app currently has exactly one real call site for cancellation (the
  attendee), so there's no multi-caller pressure that would justify an Observer's guarantee — and
  Observers silently don't fire against bulk `Order::where(...)->update(...)` calls, which the lesson
  treats as the concrete gotcha to teach. Revisit this call if the user's implementation surfaces a
  second real call site (organizer force-cancel, a stale-order cleanup command).
- **Refund modeled as internal bookkeeping only** — no real payment-gateway integration, since none
  has been introduced in this course yet.
- **Cancelled tickets keep their rows** (audit trail) — only `TicketType::remainingQuantity()`'s
  query should stop counting tickets whose order is cancelled; ticket `code`s should be treated as
  permanently retired, not reissuable, once cancelled.
- **`SoftDeletes` on `Order` explicitly rejected** as the cancellation mechanism — cancellation is a
  permanent, queryable business state, not a "this row shouldn't be visible" state.

## Problems solved

- Identified (not yet fixed — this is Lesson 12's actual implementation task, not something coded
  this session) that `TicketType::remainingQuantity()` (`app/Models/TicketType.php:36-41`) currently
  counts *every* `Ticket` row for a type regardless of its order's status — so an order being
  cancelled would not currently free up inventory. `OrderStatusEnum` (`app/OrderStatusEnum.php`)
  already has a `Cancelled` case, but nothing in the app sets it yet.
- No new code was written this session — this was a docs-only session (lesson planning + roadmap
  bookkeeping), consistent with the course's "no implementation code up front" rule.

## Current state

- `main` is pushed and clean, at `d54008d`.
- `docs/course/README.md`: Lesson 11 = Implemented, Lesson 12 = Ready (linked to the new doc), 13+
  still Planned.
- Lesson 12 is fully drafted and ready for the user to implement themselves (per course rules — no
  code was given, only requirement + instructions + reasoning).
- Carried over, still unresolved from Lesson 11 (not touched this session): 27 failed jobs in
  `failed_jobs` from a prior SMTP-unavailable test run (harmless, needs Mailpit start +
  `queue:retry all` to clear); no automated Pest test yet for `SendEventReminders`/`EventReminder`;
  two test events' `start_time` still manually offset from a prior tinker session.

## Next session starts with

1. User implements Lesson 12 (`refunds` migration/model, cancellation policy/route/controller,
   `TicketOrderService::cancel()` or a decision to split into `RefundService`, the
   `remainingQuantity()` fix, `OrderCancelled` event + listener + refund notification) — review
   against the lesson's Section 8 instructions and the Observer-vs-explicit reasoning in Section 6/7
   once shared.
2. Still-open Lesson 11 cleanup (lower priority, carried over twice now): clear the 27 failed test
   jobs, decide on an automated idempotency test for `SendEventReminders`.
3. After Lesson 12 lands: mark it "Implemented" in the roadmap and decide on Lesson 13 (Caching —
   listings, dashboard aggregates).

## Open questions

- Whether `cancel()` should live on `TicketOrderService` or a new `RefundService` — Lesson 12
  Section 9 leaves this as the user's call during implementation, to be reasoned about once refund
  logic's actual shape (partial refunds? time-based policies?) is known.
- Whether an organizer should also be allowed to cancel orders for their own events (affects the
  Policy design) — left open in Lesson 12 Section 8 for the user to decide.
- Same standing question carried from Lessons 10/11: whether manual CLI/tinker verification is
  sufficient to mark a lesson "Implemented," or whether an automated Pest test should be required
  going forward — never settled as a standing rule.
