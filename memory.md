# Memory — Laravel Progressive Architecture Training Course

Last updated: 2026-08-18

## What was built

- **Lesson 11 — Scheduling & Console Commands: implemented and committed** (`ddf04b8`, `cf8d6a0`),
  in two passes:
  - **Pass 1 (`ddf04b8`)**: naive Section 4 version. `php artisan make:command SendEventReminders`
    (`app/Console/Commands/SendEventReminders.php`) queries `Order` (not `Event`) — `whereIn('status',
    [Confirmed, Pending])`, `whereHas('event', fn($q) => $q->whereBetween('start_time', [now(),
    now()->addDay()]))`, eager-loads `event`/`user`. Logged one line per order via `Log::info` plus
    `$this->info("... N orders found")`. No idempotency yet — running twice re-logged the same orders
    (demonstrated deliberately, per lesson Section 4/5).
  - **Pass 2 (`cf8d6a0`)**: idempotent + real notification, via `/architect` design session.
    - Migration: `orders.reminder_sent_at` nullable `timestamp`. `Order` model: added to `$fillable`
      and cast `'reminder_sent_at' => 'datetime'`.
    - `app/Notifications/EventReminder.php` created: constructor-promoted `Order $order`, **does**
      `implements ShouldQueue` (deliberate divergence from `OrderConfirmation`, which only uses the
      `Queueable` trait and stays synchronous — see Decisions). `toMail()` does
      `loadMissing(['event', 'user'])`, composes subject/greeting/relative time
      (`diffForHumans()`)/formatted date/thank-you line.
    - `SendEventReminders::handle()` updated: added `whereNull('reminder_sent_at')` to the query;
      replaced `Log::info` loop body with `$order->user->notify(new EventReminder($order))`
      immediately followed by `$order->update(['reminder_sent_at' => now()])` — per-order, not a
      batch update after the loop (fail-safe ordering, see Decisions).
    - `routes/console.php`: `Schedule::command('app:send-event-reminders')->hourly();` — confirmed
      registered via `php artisan schedule:list`.
  - Verified manually: first run matched 26 test orders (from 2 events whose `start_time` was moved
    into the 24h window via tinker), queued 26 `EventReminder` jobs, marked all 26 `reminder_sent_at`.
    Second immediate run matched 0 orders — idempotency confirmed. `toMail()` content verified
    directly via tinker (renders correct subject/greeting/lines) since local SMTP (Mailpit) wasn't
    running at test time — see Problems Solved.

## Decisions made

- **Order statuses eligible for reminders**: kept `[Confirmed, Pending]` (both) — explicit user
  choice during the `/architect` session, not narrowed to `Confirmed`-only despite that being
  proposed as the "safer" default.
- **`EventReminder implements ShouldQueue`**, unlike `OrderConfirmation` (Lesson 10) which
  deliberately does not. This is intentional, not an inconsistency: Lesson 11's requirement text
  explicitly says "after successfully sends (**or successfully queues**)," which only has a real code
  path if the notification actually queues. Lesson 10's one-job-one-retry-lifecycle reasoning doesn't
  apply here since `SendEventReminders` (a command, not a queued listener) isn't itself wrapped in a
  retry lifecycle the way `LogOrderConfirmationStub` is.
- **Mark-as-reminded timing**: per-order, immediately after that order's `->notify()` call returns
  without throwing — inside the `foreach`, not batched after. Reasoning: a mid-loop crash leaves only
  *unprocessed* orders unmarked, so they're correctly retried next run; avoids the "mark before
  send"/"batch after" failure modes that could double-send or silently skip.
- **Query direction**: starts from `Order::query()`, not `Event`, since the log/notify granularity is
  "one per order" — avoids a nested loop and avoids needing an `Event::orders()` inverse relationship
  that doesn't exist on the model (`Order::event()` already does).
- **Schedule frequency**: `->hourly()` — reasoned as: the query window `[now(), now()->addDay()]`
  slides forward each run, so hourly means no order goes unreminded more than ~1 hour past becoming
  eligible; daily risks missing the window entirely for events starting at odd times relative to the
  daily fire time.
- `Order::query()` vs `Order::with(...)`: functionally identical (`with()` is a static forward to
  `newQuery()->with()`), chose `::query()` for readability when several conditions follow — no
  functional difference, purely a style call the user asked about and I should stay consistent with
  if the wider codebase already leans a certain way (not yet checked project-wide).

## Problems solved

- **Local SMTP not running** — `MAIL_MAILER=smtp` points at `127.0.0.1:1025` (Mailpit's default),
  which wasn't listening during testing. All mail-sending jobs (26 new `EventReminder` jobs, plus one
  pre-existing `LogOrderConfirmationStub` job) failed with
  `Symfony\...\TransportException: Connection could not be established with host "127.0.0.1:1025"`.
  Confirmed via `failed_jobs` exception text — not a code bug. `EventReminder::toMail()` was verified
  correct by calling it directly in tinker instead (bypasses the transport layer entirely).
  **Not yet resolved**: Mailpit needs starting (`herd services:start mailpit`) and
  `php artisan queue:retry all` + `php artisan queue:work` re-run to actually clear the 27 failed
  jobs sitting in `failed_jobs` right now.
- Explained scheduler verification commands since Windows/Herd has no real cron ticking
  `schedule:run` every minute locally: `schedule:list` (registration/next-due), `schedule:run`
  (one manual tick), `schedule:test` (force one entry now, bypass cron-expression timing),
  `schedule:work` (foreground loop simulating cron for local dev).

## Current state

- `main` is up to date with local commits `ddf04b8` and `cf8d6a0` (Lesson 11 code) — **not yet
  pushed to `origin/main`**, and `docs/course/README.md` roadmap still shows Lesson 11 as **"Ready"**,
  not "Implemented" (README wasn't updated this session — code is done, doc status is stale).
- Two test events (`Event` id 1 "Dolor deleniti quia quis." and id 2 "Quasi ad at.") have their
  `start_time` manually moved to ~10h/~20h from now (as of 2026-08-18) via tinker, purely for local
  testing — not real seed/factory data, will drift out of the 24h window over real time and is safe
  to leave or reset.
- `failed_jobs` table currently holds 27 failed jobs (26 `EventReminder` + 1 pre-existing
  `LogOrderConfirmationStub`) from the SMTP-unavailable test run — harmless test artifacts, not
  representing a real user-facing failure.
- No test file exists yet for `SendEventReminders` or `EventReminder` (Lesson 11's Practice item 3
  — "run the command twice, confirm exactly one reminder" — was verified manually via tinker/CLI,
  not via an automated Pest test).
- Lesson 10's previously-deferred Practice items (3–5: failure/retry test, full `test --compact` run,
  `Notification::fake()` stretch test) remain deferred — not touched this session.

## Next session starts with

1. Start Mailpit (`herd services:start mailpit`), then `php artisan queue:retry all` +
   `php artisan queue:work` to clear/verify the 27 failed jobs and confirm `EventReminder` actually
   delivers end-to-end (subject/body) via `http://localhost:8025`.
2. Update `docs/course/README.md` roadmap: Lesson 11 → "Implemented".
3. Decide whether to write an automated Pest test for `SendEventReminders`/`EventReminder`
   (idempotency assertion: run twice, assert notification sent exactly once) — currently only
   manually verified, matching Lesson 11's Practice item 3 but not committed as a regression-proof
   test.
4. Push `ddf04b8` and `cf8d6a0` to `origin/main` (currently local-only).
5. Decide next lesson topic per `docs/course/README.md` roadmap (whatever follows Lesson 11).

## Open questions

- Whether Lesson 11 needs an automated test before being marked "Implemented," or whether manual
  CLI/tinker verification is sufficient going forward — same unresolved policy question carried over
  from Lesson 10's memory (never settled as a standing rule).
- Whether the two test events' manually-edited `start_time` values should be reset/reverted before
  other lesson work touches those events, or left as-is since they're harmless test data.
- `withoutOverlapping()` (Lesson 11's Practice item 5 stretch goal) was explicitly scoped out of this
  implementation pass — not yet decided whether to add it later.
