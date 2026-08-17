# Memory — Laravel Progressive Architecture Training Course

Last updated: 2026-08-17

## What was built

- **Lesson 09 — Queues & Jobs**: marked Implemented (per `docs/course/README.md`). Retries/backoff/
  `failed()` added to `LogOrderConfirmationStub` per commit history (`9414f96`); PDF/confirmation
  work queued via `ShouldQueue` on the listener.
- **Lesson 10 — Notifications & Mail completed and pushed** (`0b7bc1a`, `1c010e9`):
  - `app/Notifications/OrderConfirmation.php` created: constructor-promoted `Order $order`, `via()`
    returns `['mail']`, `toMail()` uses `loadMissing(['event', 'tickets.ticketType'])` (handles the
    `SerializesModels` re-fetch-on-worker gap, avoids N+1), groups tickets by `ticket_type_id` to
    report quantity per type, builds subject/greeting/lines with event name, ticket type+qty, and
    order total.
  - `app/Listeners/LogOrderConfirmationStub.php` updated: dropped the `sleep(3)`/`Log::info` stub,
    replaced with `$event->order->user->notify(new OrderConfirmation($event->order))` called
    synchronously inside the listener's own `handle()`. Listener keeps `ShouldQueue`, `Tries(3)`,
    `Backoff([5,15])`, `failed()`. `OrderConfirmation` uses `Queueable` trait but deliberately does
    **not** implement `ShouldQueue` — avoids the two-independent-retry-lifecycles trap (Section 8's
    hint question), keeping exactly one job per order in the `jobs` table.
  - `docs/course/README.md` updated: Lesson 10 → Implemented.
  - `.env` updated (untracked, not committed): `MAIL_MAILER=smtp`, `MAIL_PORT=1025` — switched from
    `log` driver to Herd's bundled Mailpit (verified via `Test-NetConnection` that ports 1025/8025
    are listening). Config cache cleared after the change.
- **Lesson 11 — Scheduling & Console Commands drafted, not yet implemented** (`a24bcb4`, pushed):
  - `docs/course/11-scheduling-console.md` created. Forcing function: attendees need a one-time
    reminder email 24h before their event starts, sent automatically (Task Scheduler), without
    duplicate sends across repeated scheduler runs (idempotency tracking required — e.g. a
    `reminder_sent_at` column). Plan reuses the Notification pattern from Lesson 10 (`EventReminder`
    notification) triggered by a new `SendEventReminders` Artisan command registered in
    `routes/console.php` via the `Schedule` facade.
  - `docs/course/README.md` updated: Lesson 11 → Ready (was Planned).

## Decisions made

- Course convention (confirmed, carried forward): roadmap status "Ready" = lesson doc drafted,
  instructions-only, not yet implemented; "Implemented" = code done. Lesson 10 promoted to
  Implemented despite Practice items 3–5 (failure/retry test, full test suite run,
  `Notification::fake()` stretch test) being explicitly skipped by user choice — **not fully
  verified**, just deliberately deferred.
- Confirmed resolution to Lesson 10 Section 8's "stack two ShouldQueue things?" question: the
  listener owns queuing/retry, the Notification does not — one job, one retry lifecycle. This was
  reasoned through explicitly with the user (traced Job A/Job B behavior) before they implemented it
  this way.
- `toMail()`'s eager-loading must happen *inside* the method itself (`loadMissing`), not before
  construction — `SerializesModels` strips relations loaded pre-dispatch since the queued job only
  serializes the model's ID and re-fetches fresh on the worker.
- Lesson 11 alternatives considered and rejected: delayed queued job dispatched at order-creation
  time (doesn't adapt if reminder policy changes, scatters scheduling logic); no-idempotency
  "unlikely window" approach (classic distributed-systems trap, explicitly called out as not
  acceptable).

## Problems solved

- User reported no email received after switching to Mailpit. Diagnosed: Mailpit itself was up
  (ports 1025/8025 both responding), but `jobs`/`failed_jobs` tables were both empty — meaning no
  queue worker had processed anything since the switch, not a Mailpit/config problem. Resolution:
  confirmed user needs `composer run dev` or `php artisan queue:listen` running, then place a fresh
  test order and check `http://localhost:8025`.
- Pint auto-fixed `OrderConfirmation.php` after edits (removed unused `ShouldQueue` import — that
  import was actually never used since the class deliberately doesn't implement `ShouldQueue`).

## Current state

- `main` is up to date with `origin/main` as of `a24bcb4` (Lesson 11 draft + README roadmap update).
  All Lesson 10 code changes are committed and pushed (`1c010e9`).
- `.env` has local Mailpit config (`MAIL_MAILER=smtp`, `MAIL_PORT=1025`) — untracked/gitignored,
  won't show in `git status`, but worth knowing if debugging mail delivery in a future session.
- **Lesson 10 outstanding (explicitly deferred by user, not forgotten)**: Practice items 3–5 —
  force a failure and confirm retry/backoff/`failed_jobs` still work post-refactor; run
  `php artisan test --compact` to confirm no regressions since Lesson 9; write the
  `Notification::fake()` stretch-goal test asserting `OrderConfirmation` was sent to the correct
  `User`. No test file exists yet referencing `OrderConfirmation` or `LogOrderConfirmationStub`
  (confirmed via search — `tests/` has zero matches).
- **Lesson 11 not yet implemented** — only the lesson doc exists. No `SendEventReminders` command,
  no `EventReminder` notification, no idempotency column, no `routes/console.php` schedule entry.

## Next session starts with

1. Decide whether to close Lesson 10's deferred Practice items (3–5) before starting Lesson 11
   implementation, or continue deferring them.
2. **Implement Lesson 11** per `docs/course/11-scheduling-console.md` Section 8:
   - Add idempotency tracking (likely a nullable `reminder_sent_at` on `Order`, via migration).
   - `php artisan make:command SendEventReminders` — query events starting ~24h out, filtered to
     orders not yet reminded.
   - `php artisan make:notification EventReminder`, mirroring `OrderConfirmation`'s structure
     (`via()`, `toMail()` with careful eager-loading).
   - Register the schedule in `routes/console.php`, test with a factory-created event ~23h out,
     confirm no duplicate send on a second immediate run.
3. Mark Lesson 11 "Implemented" in `docs/course/README.md` once done, per course convention.

## Open questions

- Should Lesson 10's Practice items 3–5 be done retroactively, or intentionally skipped for the
  whole course (i.e., is "Implemented" status meant to require full verification going forward, or
  is the user comfortable with lighter-weight completion)? Not yet clarified as a standing policy —
  only resolved ad hoc for Lesson 10.
- Lesson 11's idempotency column name/design (`reminder_sent_at` on `Order` vs. a separate table) is
  only a suggestion in the lesson doc — actual implementation choice is the user's, per course rules.
- Whether `SendEventReminders` should queue individual reminder sends per-order or send inline is
  posed as an open design question in the lesson doc, not yet decided.
