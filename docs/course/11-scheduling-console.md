# Lesson 11 — Scheduling & Console Commands: Event Reminders

## 1. Goal

Send attendees a reminder notification some fixed window before their event starts, using Laravel's
Task Scheduler to run a console command periodically. By the end of this lesson, a single scheduled
command finds every order for events starting soon, and reuses last lesson's Notification machinery
to tell each attendee — without a human ever running a command by hand, and without re-litigating
the queue-vs-sync or Notification-vs-Mailable decisions already made.

## 2. Current State

`OrderConfirmation` (Lesson 10) is a `Notification` with `via(): ['mail']` and a `toMail()` that
knows how to render an order's event/ticket/total details, triggered synchronously from
`LogOrderConfirmationStub`'s already-queued `handle()`. `GiftTicket` (Lesson 08) is this app's only
existing Artisan command, invoked manually by an organizer. Nothing in the app currently runs on a
recurring schedule — there is no cron entry, no `Schedule` facade usage, nothing in
`routes/console.php` beyond defaults. `Event` has `start_time`/`end_time` (both cast to `datetime`),
so "starting soon" is a real, queryable fact already in the schema.

## 3. New Requirement

> "Attendees forget about events they bought tickets for weeks ago. Send each attendee a reminder
> email 24 hours before their event starts — once, not every time the check runs. This needs to run
> automatically, without anyone remembering to trigger it by hand."

## 4. Initial Implementation

Write `php artisan make:command SendEventReminders` and, inside `handle()`, query for events
starting in the next 24 hours and log a line per matching order (`Log::info` is fine — you don't
need the real notification wired up yet). Run it manually with `php artisan app:send-event-reminders`
a couple of times in a row.

Notice what happens on the second run: the same events are still "starting in the next 24 hours" (unless you're
right at the boundary), so the same attendees get logged again. Nothing about the command remembers
it already ran for this event.

## 5. Problem Appears

Two separate problems show up here, and it's worth naming both before reaching for the Scheduler.

**Nobody is running the command.** A correct command that only runs when a human remembers to type
it isn't automation — it's a manual step with extra typing. The requirement explicitly says
"without anyone remembering to trigger it by hand," which nothing built so far satisfies.

**Running it repeatedly (which automation implies) risks duplicate sends.** If this command runs
every few minutes via cron, and an event starts in 23 hours, the command will match that same event
on *every single run* between the 24-hour mark and whenever the event actually starts — that's
potentially dozens of runs, each one re-sending the reminder to the same attendees. This is a
different shape of problem than anything in Lessons 08–10: those were about *not blocking* or
*not letting failure reach back*; this is about *idempotency* — the same unit of work must have a
single, trackable "did this already happen" state, independent of how many times the trigger fires.

## 6. Concept Introduction

Laravel's **Task Scheduler** lets you define recurring jobs in code (`routes/console.php`, via the
`Schedule` facade) instead of hand-editing crontab entries per server. A single cron entry
(`* * * * * php artisan schedule:run`) added once to the server runs every minute and asks Laravel
"does anything actually need to run right now?" — the frequency (`->hourly()`, `->dailyAt('09:00')`,
`->everyFiveMinutes()`) is declared in your application code, versioned with everything else, instead
of living only on a server nobody's SSH'd into in months.

Scheduling solves *when this runs*. It does **not** solve *has this already happened for this
specific record* — that's a separate concern you have to design for explicitly (a timestamp column,
a pivot table, a status flag), the same way queuing (Lesson 09) didn't automatically solve
duplicate-listener problems.

## 7. Why This Solution?

- **Declared, versioned schedules beat server crontabs.** The schedule lives in `routes/console.php`
  alongside the rest of the app, reviewable in a PR, identical across every environment that shares
  the codebase — not a manual edit on a box that configuration drift can silently break.
- **One cron entry, arbitrarily many scheduled tasks.** You're not maintaining N crontab lines for N
  periodic jobs; `schedule:run` is the only thing the OS needs to know about.
- **This is a *when*, not a *how*, decision — orthogonal to Lesson 10.** The scheduled command's job
  is to find "who needs reminding right now" and reuse `OrderConfirmation`-style Notification
  machinery (a new `EventReminder` notification) to actually tell them — not to reinvent how
  attendees get contacted.

## 8. Implementation

### Task

Build a scheduled command that reminds each attendee once, 24 hours before their event, without
duplicate sends across repeated scheduler runs.

### Instructions

- Design the "already reminded" tracking *before* writing the command. Options worth weighing: a
  nullable `reminder_sent_at` timestamp on `Order` (or wherever makes sense given this app's actual
  schema), versus a separate table if one order could need multiple distinct reminders later. Pick
  the simplest thing that actually prevents a duplicate send — don't build for a multi-reminder future
  that isn't in the requirement.
- `php artisan make:command SendEventReminders`. In `handle()`, query for the specific window (events
  starting in roughly the next 24 hours) **and** filter out orders already reminded, using whatever
  column/flag you chose above.
- `php artisan make:notification EventReminder`, following the same shape as `OrderConfirmation` —
  constructor-promoted dependency, explicit `via()`, a `toMail()` that needs the same kind of
  eager-loading care (`SerializesModels`, N+1 avoidance) you reasoned through in Lesson 10.
- Decide whether `SendEventReminders` should queue individual reminder sends per-order (many
  attendees, one slow mail send each) or handle them inline within the command. Think about what
  happens if the command itself times out partway through a large attendee list — does a queued
  approach make partial progress safer?
- Mark each order as reminded *only after* the notification successfully sends (or successfully
  queues) — not before. Think about what happens if the command crashes between "mark as reminded"
  and "actually send": which order do these two steps need to happen in to fail safe rather than
  fail silent?
- Register it in `routes/console.php` via the `Schedule` facade with a frequency that makes sense for
  a 24-hours-out reminder (hourly is defensible; daily risks missing the window for events that
  start at an odd time relative to when the daily run fires — reason about the trade-off).
- Test it without waiting a real day: use a factory to create an event starting ~23 hours from now,
  run `php artisan app:send-event-reminders` (or `schedule:run`) manually, confirm the reminder
  sends once, run it again immediately, and confirm no duplicate.

### How This Should Be Approached

`SendEventReminders` orchestrates (find who's due, mark them done) but shouldn't itself know how to
compose a reminder email — that's `EventReminder`'s job, exactly like `OrderConfirmation` owns email
composition and `LogOrderConfirmationStub` just triggers it. Don't add a second reminder window (a
1-hour-before reminder, say) or a second channel just because the scaffolding now exists — the
requirement is one 24-hour reminder, once.

## 9. Refactoring

Nothing outside the new command/notification pair changes. `TicketOrderService`, `OrderController`,
`GiftTicket`, `OrderPlaced`, and `OrderConfirmation` are all unaffected — this is a net-new,
independently-triggered flow, not a modification of the purchase path.

## 10. Alternatives

- **A queued job dispatched at order-creation time with a delay until 24 hours before the event**
  (`->delay($event->start_time->subDay())`): works for a fixed single reminder, but doesn't scale to
  "the reminder window policy might change" (you'd need to re-dispatch or cancel already-queued jobs
  for existing orders) and puts scheduling logic at order-creation time, far from where "who needs a
  reminder right now" is actually decided. The Scheduler-driven sweep is more adaptable and keeps the
  decision in one place, re-evaluated fresh on each run.
- **A scheduled command with no idempotency tracking, relying on a narrow enough time window that
  duplicates are "unlikely"**: this is the classic distributed-systems trap — "unlikely" isn't
  "impossible," and a reminder that occasionally double-sends is a real, visible bug users will
  notice, not a theoretical edge case.
- **Sending reminders synchronously from the command with no queue involvement at all**: acceptable
  at small scale (few attendees), but revisit if this command's runtime starts approaching the
  scheduler's run frequency — a command that takes 45 minutes to run, checked every hour, causes
  overlapping runs, which `withoutOverlapping()` exists specifically to prevent.

## 11. When Not To Use It

If "send this" needs to happen at a precise, event-specific moment relative to something that isn't
a fixed clock time (e.g., "3 minutes after this specific order's payment webhook fires"), a delayed
queued job dispatched at that moment is a better fit than a periodic sweep — scheduling is for
recurring, calendar-driven work ("check every hour"), not for one-off future work tied to a single
record's own timeline.

## 12. Practice

1. Add the idempotency-tracking column/flag and implement `SendEventReminders` + `EventReminder` per
   Section 8.
2. Register the schedule in `routes/console.php` and confirm `php artisan schedule:list` shows it.
3. Using a factory-created event ~23 hours out, run the command twice in a row and confirm exactly
   one reminder is sent.
4. Confirm an event that already started, and an event more than 24 hours out, are both correctly
   excluded.
5. Stretch goal: add `withoutOverlapping()` to the schedule entry and explain, in your own words, what
   failure mode it prevents that testing alone wouldn't have caught.

## 13. Review Questions

1. What problem does the Task Scheduler solve, and what problem does it explicitly *not* solve on its
   own?
2. Why is "run this every few minutes" not automatically safe for work that shouldn't happen twice —
   what has to be added to make it safe?
3. In what order should "send the notification" and "mark as reminded" happen, and why does getting
   that order backwards create a worse failure mode than getting it right?
4. Why does `routes/console.php` scheduling beat a hand-edited crontab entry, concretely?
5. When would a delayed queued job dispatched at creation time be a better fit than a periodic
   scheduled sweep?

## 14. Takeaways

- Scheduling answers *when does this run* (periodically, on a declared cadence) — it's a distinct
  decision from *how is this delivered* (Lesson 10's Notification) and *should this block* (Lesson
  09's queuing), and all three compose without any one of them owning the others.
- A scheduled task that can run more than once against the same data needs explicit idempotency
  tracking — "it probably won't run twice in that window" is not a design.
- One versioned, code-reviewed cron entry (`schedule:run`) replacing N manually-maintained crontab
  lines is a real operational win, not just a syntax preference.

---

## Interview Preparation

### What Interviewers May Ask

- "How does Laravel's Task Scheduler work under the hood — what does the server's crontab actually
  need to contain?"
- "How would you prevent a scheduled job from processing the same record twice?"
- "What's `withoutOverlapping()` for, and what happens without it?"
- "When would you use scheduling versus a delayed queued job?"
- "How do you test scheduled commands without waiting for real time to pass?"

### What the Interviewer Is Testing

Whether you understand the Scheduler as a thin, code-versioned wrapper around a single cron entry —
not magic — and whether you instinctively reach for idempotency safeguards on any periodic job that
touches state, rather than assuming "it runs on a schedule" implies "it's safe to run repeatedly."

### How I Should Answer

Explain the mechanism honestly: one crontab line (`* * * * * php artisan schedule:run`) runs every
minute and Laravel decides, in code, what's actually due — nothing else touches the server's cron
config. For duplicate-prevention, be concrete: name the specific tracking mechanism (a timestamp or
status column checked in the query and set after a successful send) rather than gesturing vaguely at
"idempotency." For overlapping runs, explain that a long-running command plus a short schedule
interval means a second invocation can start before the first finishes, working over the same rows —
`withoutOverlapping()` uses a cache lock to skip a run if the previous one hasn't finished, and a
senior answer notes this needs a `expiresAt()` tuned longer than the command could plausibly take, or
a truly-stuck run blocks all future runs forever.

### Real Interview Scenario

> "You need to auto-cancel orders that have been stuck in a 'pending payment' state for more than 30
> minutes, running as a background sweep. How would you build this, and what could go wrong?"

A strong candidate reaches for a scheduled command querying `where status = 'pending' and
created_at < now()->subMinutes(30)`, updating status inline. A senior candidate asks what happens if
that update-in-a-loop is slow enough to overlap with the next scheduled run (`withoutOverlapping()`),
whether the status change itself needs a lock to avoid a race with a payment webhook arriving at the
exact same moment as the sweep (row locking, same instinct as Lesson 06's purchase flow), and whether
"cancel" should dispatch its own event/notification rather than being a silent status flip nobody
downstream reacts to.

### Interview Difficulty

**Mid–Senior.** Registering a scheduled command is junior-accessible. Reasoning about idempotency,
overlap protection, and choosing scheduling versus delayed dispatch is where the conversation becomes
senior — it's the same "obvious-looking automation hides a race condition" instinct tested elsewhere
in this course (Lesson 06's inventory locking, Lesson 10's stacked-queue trap).

---

## Laravel Interview Checklist

- Can you explain what a single `schedule:run` crontab entry actually does, versus what used to
  require N crontab lines?
- Can you name the specific mechanism you'd use to stop a periodic job from double-processing the
  same record?
- Do you know what `withoutOverlapping()` protects against, and what tuning it needs to be safe?
- Can you articulate when a delayed queued job beats a periodic scheduled sweep, and vice versa?
- Can you explain how you'd test a scheduled command's due-window logic without waiting real time?
