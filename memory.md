# Memory — Laravel Progressive Architecture Training Course

Last updated: 2026-08-21

## What was built

- **Lesson 12 (Refunds & Cancellation) fully implemented** and committed (`4c6d6d7`, `4100d00`,
  `83948f9`), roadmap marked "Implemented" in `docs/course/README.md`:
  - `resources/views/orders/show.blade.php` + `OrderController::show()`/`cancel()` + routes
    (`orders.show`, `orders.cancel`) — order detail page with a cancel button.
  - `app/Policies/OrderPolicy.php` — `view`/`update` restricted to `$user->id === $order->user_id`
    (attendee-only; organizer cancel-on-behalf was explicitly left out of scope).
  - `app/Models/Refund.php` + `database/migrations/..._create_refunds_table.php` (`order_id`,
    `amount`, relies on `created_at` for "when" — no separate `refunded_at`).
  - `TicketOrderService::cancel()` (`app/Services/TicketOrderService.php`) — locked, transactional,
    idempotent (throws `OrderAlreadyCancelledException`), guards against cancelling an order whose
    event has already started (throws new `EventAlreadyStartedException`), creates the `Refund`,
    dispatches `OrderCancelled` outside the transaction (mirrors `order()`/`OrderPlaced`).
  - `TicketType::activeTickets()` — new relation excluding tickets whose order is `Cancelled`; used by
    both `remainingQuantity()`'s live-query fallback and `EventController::show()`'s
    `withCount('activeTickets')` eager-load path, so inventory actually frees up on cancellation.
  - `app/Listeners/SendRefundNotification.php` + `app/Notifications/RefundProcessed.php` (implements
    `ShouldQueue`, same shape as `EventReminder`/`OrderConfirmation`) — sends refund email on
    `OrderCancelled`.
  - `Order::isOrderCancelled` attribute and `Event::iPastEvent` attribute (fixed — see Problems
    Solved) — used in the view to show/hide the cancel button.
- **Lesson 13 (Caching) drafted**: `docs/course/13-caching.md` — caching the public events listing
  (`EventController::index()`) and a new (not-yet-built) per-event organizer sales dashboard
  (tickets sold, net revenue). Core teaching point: explicit invalidation across multiple write paths
  vs relying on TTL alone; this app's `database` cache driver doesn't support cache tags, so
  invalidation must be explicit per-key. Roadmap updated to "Ready". Committed `6102d30`.

## Decisions made

- **Cancellation resolved as explicit `TicketOrderService::cancel()`**, not an Observer — per Lesson
  12's reasoning (only one real call site right now; bulk `update()` would silently skip an Observer
  anyway).
- **Cancelled tickets keep their rows**; `code`s are never reissued — true by construction, since every
  new order calls `Ticket::create()` with a fresh `Str::uuid()`, not by any explicit "don't reuse"
  check.
- **Attendees only can cancel their own orders** — organizer force-cancel was explicitly scoped out for
  now (Policy only checks order ownership).
- **Cancellation blocked once the event has started** — added as `EventAlreadyStartedException`,
  checked in `TicketOrderService::cancel()` (the user's call — chose service over policy).
- **Lesson 13 will not use cache tags** — this app's cache driver is `database`, which (like `file`)
  doesn't support tags; only `redis`/`memcached` do. Explicit `Cache::forget()` per key is the
  only viable approach here, not a stylistic choice.

## Problems solved

- **Order review found a real gap**: Section 3's business rule ("cancel an order for an event that
  hasn't happened yet") wasn't enforced anywhere in the user's first pass — fixed by adding
  `EventAlreadyStartedException` + a `start_time->isPast()` guard in `TicketOrderService::cancel()`.
- **`Event::iPastEvent()` self-reference bug**: originally written as `$this->event->start_time`
  inside the `Event` model itself (no `event` relation on `Event`, so `$this->event` was `null`,
  throwing on `->start_time`). Fixed to `$this->start_time->isPast()`.
- **Inverted boolean logic in the cancel-button `@if`**: was
  `!$order->isOrderCancelled || $order->event->iPastEvent` (wrong in 2 of 4 cases — showed the button
  for cancelled-but-past orders and hid it correctly only by accident). Fixed to
  `!$order->isOrderCancelled && !$order->event->iPastEvent`. Also explains why the `iPastEvent` bug
  above didn't surface immediately — `||` short-circuited past it whenever the order wasn't cancelled.
- Minor smell flagged but not yet fixed: `OrderAlreadyCancelledException::render()` still attaches its
  error to the `quantity` field key (copy-pasted from `TicketUnavailableException`, where it made
  sense) — cosmetically works since the layout loops `$errors->all()`, but the key is misleading.

## Current state

- `main` is clean, all Lesson 12 work + Lesson 13 draft committed and pushed-ready (not yet confirmed
  pushed to origin this session — verify before assuming remote is current).
- Lesson 12: fully implemented, reviewed, and two review-found bugs fixed. No automated Pest test
  written for cancellation yet (same open question carried since Lesson 10/11 — never settled as a
  standing rule on whether manual verification is sufficient to mark a lesson "Implemented").
- Lesson 13: drafted only, no implementation started. No organizer dashboard exists yet in the app —
  Lesson 13's Instructions (Section 8) call for building a minimal one as part of the caching work.
- Still-open from earlier lessons, untouched this session: 27 failed jobs in `failed_jobs` from a
  prior SMTP-unavailable test run (harmless, needs Mailpit start + `queue:retry all`).

## Next session starts with

1. User implements Lesson 13 (cache `EventController::index()`, build the per-event organizer
   dashboard, cache its aggregate, wire explicit `Cache::forget()` invalidation into
   `TicketOrderService::order()`, `TicketOrderService::cancel()`, and relevant `EventController`
   actions) — review against Section 8/9's instructions once shared.
2. Optional cleanup carried over (low priority): fix `OrderAlreadyCancelledException`'s error key from
   `quantity` to something accurate like `order`; clear the 27 failed test jobs.
3. After Lesson 13 lands: mark it "Implemented" and decide on Lesson 14 (Repository/DTO Evaluation).

## Open questions

- Whether an organizer should ever be allowed to cancel orders for their own events — left open in
  Lesson 12, still unresolved, may resurface if Lesson 13's dashboard work or a later lesson needs it.
- Whether the per-event dashboard cache invalidation should live inline in each write path (current
  plan) or get centralized (an `Event` observer or a dedicated cache-owning class) once all the call
  sites are visible — Lesson 13 Section 9 explicitly defers this decision until the duplication is
  real, not before.
- Standing question, carried across several lessons now: whether manual CLI/browser verification is
  sufficient to mark a lesson "Implemented," or whether an automated Pest test should be required
  going forward.
