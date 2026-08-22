# Memory — Laravel Progressive Architecture Training Course

Last updated: 2026-08-22

## What was built

Lesson 13 (Caching) — Section 8 Instructions points 1–5 implemented across two commits on `main`.

**`9047f8c` — cache the events listing (points 1–2)**

- `EventController::index()` wrapped in `Cache::remember('events.index', now()->addMinutes(5), ...)`,
  caching the Eloquent collection untouched so `resources/views/events/index.blade.php` needed no
  changes.
- `Cache::forget('events.index')` via a private `forgetEventsIndexCache()` helper, called from
  `store()`, `update()`, `destroy()`, `toggleStatus()`.
- `config/cache.php` — `serializable_classes` changed from `false` to an allowlist of
  `Collection`, `Event`, `TicketType`, `User` (see Problems Solved).

**`889b6de` — cached organizer dashboard (points 3–5)**

- `app/Http/Controllers/EventDashboardController.php` — `show()` authorizes via
  `EventPolicy::update`, then `Cache::remember("event:{$event->id}:dashboard", now()->addHour(), ...)`.
  Private `calculateStats()` runs three queries: ticket count through non-cancelled orders, order
  sum, refund sum.
- `resources/views/events/dashboard.blade.php` — two stat cards (tickets sold, net revenue).
- `routes/web.php` — `events.dashboard` (`GET events/{event}/dashboard`) inside the `auth` group.
- `TicketOrderService.php:49` and `:79` — `Cache::forget("event:{$order->event_id}:dashboard")` after
  each transaction commits, before the event dispatch.
- `EventController.php:95` — same forget in `destroy()`, capturing `$eventId` before the delete.
- `resources/views/events/index.blade.php` — Dashboard link behind `@can('update', $event)`.

## Decisions made

- **Cache the Eloquent collection as-is for the listing**, not a flattened array — keeps the Blade
  view unchanged and avoids partial models silently lazy-loading (which would re-create the N+1 the
  eager load prevents). This is what forced the `serializable_classes` change.
- **Allowlist four classes rather than `serializable_classes => true`** — deliberate, narrow trade of
  Laravel 13 hardening for view convenience. Carbon is intentionally absent: datetime casts are
  applied lazily from raw string attributes, so Carbon never appears in the serialized graph.
- **Dashboard payload is scalars only** (`['tickets_sold' => int, 'net_revenue' => float]`) —
  sidesteps the allowlist entirely. General rule going forward: cache aggregates as scalars.
- **Net revenue = SUM(all orders.total_amount) − SUM(all refunds.amount), no status filter.** The
  lesson text says "only for non-cancelled orders", which is self-contradictory in this schema —
  `TicketOrderService::cancel()` is the only thing creating a `Refund` and does so while flipping the
  order to `Cancelled`, so filtering leaves nothing to subtract. Summing everything is identical
  today and stays correct if a partial refund ever lands on a live order.
- **Event edits do NOT invalidate the dashboard key**; `destroy()` does, as cleanup only. Rationale:
  invalidate exactly the write paths touching the data the value was computed from — name/venue/time
  cannot move a revenue figure.
- **No abstraction yet for the dashboard key** — the string is repeated at all four call sites on
  purpose, as the input Section 9's refactoring exercise needs.
- **Reused `EventPolicy::update` for dashboard authorization** rather than adding a `viewDashboard`
  method — the rule is already exactly "owns this event".
- TTLs: listing 5 minutes, dashboard 1 hour (longer because its invalidation covers every write path
  and the recompute is the expensive one).

## Problems solved

- **Laravel 13's `serializable_classes` default broke the listing cache.** `config/cache.php` ships
  `'serializable_classes' => false`, which passes `['allowed_classes' => false]` to `unserialize()`
  in `DatabaseStore::unserialize()` (gadget-chain hardening for a leaked `APP_KEY`). Cached Eloquent
  objects came back as `__PHP_Incomplete_Class`, so `@forelse ($events as $event)` iterated the
  incomplete object's *properties* instead of the events — the first being the string
  `'Illuminate\Database\Eloquent\Collection'`, producing "Attempt to read property 'name' on string"
  at `index.blade.php:29`. Fails only on the cache-hit path, and the error points nowhere near the
  cache. Fixed by the four-class allowlist.
- **Wrong assumption in planning** — "Eloquent collections serialize into and out of cache fine" was
  true through Laravel 12 but is false by default in 13. Verify this before recommending the
  cache-the-model approach on any Laravel 13+ project.
- **Two commit messages were mangled** by using PowerShell heredoc syntax (`@'...'@`) inside the Bash
  tool, which left a stray `@` as the subject line and stripped inner quotes. Use `git commit -F
  <file>` for multi-line messages in this environment.

## Current state

- `main` is clean and **2 commits ahead of `origin/main`** — `9047f8c` and `889b6de` are NOT pushed.
- Lesson 13 Section 8: points 1–5 done. **Point 6 (the test proving invalidation) is outstanding** —
  invalidation was verified only by a one-off tinker run, so nothing in the suite would catch a
  regression.
- Verified this session (not automated): cold read caches; placing a 2-ticket order invalidates and
  moves the numbers +2 / +98.00; cancelling invalidates and returns both figures exactly to baseline;
  over HTTP owner 200, non-owner 403, guest 302 to login.
- `php artisan test --compact` — 4 passed, **1 failed**: `tests/Feature/OrderPurchaseTest.php:112`
  ("it oversells a ticket type when two purchases race past the naive availability check") fails with
  "Failed asserting that 2 is identical to 1". **Pre-existing and unrelated** — confirmed by stashing
  and re-running on a clean tree. The test asserts overselling happens; Lesson 07's row locking made
  it stop, so the expectation is stale.
- `docs/course/13-caching.md` is unchanged and now has two known gaps (see Next session).
- Carried over untouched: 27 failed jobs in `failed_jobs` from a prior SMTP-unavailable run (needs
  Mailpit start + `queue:retry all`); `OrderAlreadyCancelledException::render()` still attaches its
  error to the `quantity` field key instead of something accurate like `order`.

## Next session starts with

Mid-discussion on **Section 9 (Refactoring)** — no code written yet. All four dashboard-key call
sites are now visible:

```
EventDashboardController.php:21   remember  "event:{$event->id}:dashboard"
TicketOrderService.php:49         forget    "event:{$order->event_id}:dashboard"
TicketOrderService.php:79         forget    "event:{$order->event_id}:dashboard"
EventController.php:95            forget    "event:{$eventId}:dashboard"
```

The stated defect is not verbosity — it's that the same string is built three different ways from
three differently-named variables, so a typo yields a `forget()` on a key nobody reads: no error, no
failing test, just a number that quietly stops updating. Options laid out: (A) leave it, (B) an
`EventDashboardCache` class owning key/read/forget, (C) an `Order` observer, (D) a listener on the
existing `OrderPlaced`/`OrderCancelled` events. Recommended B now, D later if a fourth order-write
path appears, not C.

**The user's last question, unanswered: would a private method inside `TicketOrderService` be
enough?** Answer given: it collapses 4 sites to 3 but can't cross class boundaries, so the read
(`EventDashboardController`) and the delete forget (`EventController`) still build the key
independently — and it makes the code look refactored while the cross-class disagreement remains.
The user had not yet chosen a direction. Offer to show B and the private-method version side by side.

Then, in rough priority:

1. Section 8 point 6 — the Pest test proving invalidation (place order → assert dashboard reflects it
   → cancel → assert the *next* read reflects the cancellation).
2. Update `docs/course/13-caching.md` with two findings from this session: the `serializable_classes`
   trap (as a second driver constraint in §6, alongside cache tags) and the revenue-formula
   contradiction in §8 point 3.
3. Fix or retire the stale `OrderPurchaseTest.php:112` oversell test.
4. Push `main` to `origin` (2 commits behind remote).
5. Mark Lesson 13 "Implemented" in `docs/course/README.md`, then decide on Lesson 14
   (Repository/DTO Evaluation).

## Open questions

- **Section 9's direction is undecided** — private method vs `EventDashboardCache` class. The
  tiebreaker offered: if the dashboard read is ever likely to move (Livewire component, API resource,
  scheduled report), the cross-class problem worsens and the class is clearly right; if the read stays
  in one controller forever, the private method is defensible.
- **The lesson doc's `Event` observer suggestion in §9 is wrong for this key** and needs correcting —
  the dashboard is invalidated by `Order`/`Refund` writes, not `Event` writes, so an `Event` observer
  watches a model whose writes we explicitly decided are irrelevant. If an observer is ever used here
  it must be on `Order` and must implement `ShouldHandleEventsAfterCommit`, or it fires inside
  `DB::transaction()` and opens a race where a concurrent read repopulates the cache with pre-commit
  data and it stays stale until TTL.
- **A queued listener must never own cache invalidation** — noted because `LogOrderConfirmationStub`
  is `ShouldQueue`; async invalidation leaves the dashboard stale until a worker runs.
- **The `serializable_classes` allowlist is now a maintenance burden** — every new cached payload
  containing objects must add its classes, or it silently returns incomplete objects rather than
  erroring at write time.
- Standing, carried across several lessons: whether manual CLI/browser verification is sufficient to
  mark a lesson "Implemented," or whether an automated Pest test should be required.
- Carried from Lesson 12: whether an organizer should ever be allowed to cancel orders for their own
  events.
