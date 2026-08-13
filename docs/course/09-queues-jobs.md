# Lesson 09 — Queues & Jobs: Generating the Ticket PDF

## 1. Goal

Move slow, non-essential work out of the request/console lifecycle using Laravel's queue system.
By the end of this lesson, generating an attendee's PDF ticket will happen in the background — the
purchase (or gift) still completes and responds immediately, the PDF generation runs on a separate
worker process, and a failure in that background work retries automatically without ever touching
the `Order` that already committed successfully.

## 2. Current State

`OrderPlaced` (Lesson 08) is dispatched synchronously from `TicketOrderService::order()`, after the
transaction commits. `LogOrderAudit` and `LogOrderConfirmationStub` both run **in the same
request/command**, immediately, before `OrderController::store()` can redirect or `GiftTicket` can
exit — they're fast (a couple of log writes), so nobody's noticed yet. This app's queue connection
is already configured for the `database` driver (`QUEUE_CONNECTION=database` in `.env`), with the
`jobs`/`job_batches`/`failed_jobs` tables already migrated — nothing to set up, just not used yet.

## 3. New Requirement

> "Attendees need an actual PDF ticket (their name, the event, a QR-style code for check-in) —
> not just a log line saying a confirmation would be sent. Generating that PDF takes real time
> (rendering a layout, encoding the code) — call it a few seconds per ticket. The purchase or gift
> command must still respond immediately; the PDF should be ready shortly after, not block the
> attendee's browser or the organizer's terminal. If PDF generation fails (a bad font file, a
> transient library error), it should retry a couple of times automatically, and if it still fails,
> the platform needs a record of that — without the failure ever affecting the `Order` that was
> already placed."

## 4. Initial Implementation

Extend `LogOrderConfirmationStub` (or write the PDF generation inline wherever you'd naturally put
it) to actually simulate the slow work — a `sleep(3)` or similar stand-in is fine, you don't need a
real PDF library to feel the problem. Keep it fully synchronous: it should run as part of
`handle()`, in the same process, before the listener returns.

Trigger a purchase through the browser and time how long the redirect takes. Trigger `GiftTicket`
and time how long the command takes to exit.

## 5. Problem Appears

The attendee is now staring at a spinning browser tab for several seconds after clicking "buy" —
for work that has nothing to do with whether their purchase succeeded. The `Order` and `Ticket`
rows were created and committed *before* `OrderPlaced` even dispatched; the attendee is waiting on
something that, from their perspective, already happened.

There's a second, sharper problem: **a synchronous listener that throws stops the response
entirely.** If PDF generation fails partway through, the exception propagates out of `handle()`,
out of the event dispatch, and — depending on where you call it from — can turn a *successful
purchase* into what looks like a failed request. The `Order` is safely committed in the database,
but the attendee sees an error page. That's a direct regression of something Lesson 08 was
supposed to guarantee: side effects shouldn't be able to undermine a fact that already happened.

## 6. Concept Introduction

A **queued Job** (or a listener implementing `ShouldQueue`) is work that gets serialized, written to
a queue (here, the `jobs` database table), and picked up later by a separate **worker** process
(`php artisan queue:work`) — not the request or console process that dispatched it. The dispatching
code returns immediately; the actual work happens asynchronously, whenever a worker is free to pick
it up.

Queued jobs get **automatic retries**: if a job throws, Laravel doesn't just lose it — it re-attempts
according to `$tries`/`backoff` you configure, and only after exhausting retries does it land in the
`failed_jobs` table, where you can inspect and manually retry it later (`php artisan queue:retry`).

## 7. Why This Solution?

- **The attendee's response time should reflect what they're actually waiting on** — the purchase,
  not unrelated background work. Queuing removes work from the critical path that was never part of
  the actual business transaction's success/failure.
- **Failure isolation.** A queued job's failure doesn't propagate back to the code that dispatched
  it — the request already returned. The `Order` stays exactly as valid as it was the moment it
  committed, regardless of what happens to the PDF afterward.
- **Retries handle transient failures for free.** A flaky third-party font/QR library, a temporary
  disk issue — these are exactly the class of failure that "try again in a few seconds" fixes, and
  the queue worker does that without you writing retry loops by hand.

## 8. Implementation

### Task

Make PDF ticket generation run on the queue instead of synchronously, with sensible retry
behavior and a visible trail when it ultimately fails.

### Instructions

- Decide: should this be `LogOrderConfirmationStub` upgraded with `implements ShouldQueue`, or a
  dedicated `Job` class (`php artisan make:job GenerateTicketPdf`) that the listener dispatches? Think
  about it the same way Lesson 07 asked you to think about Service vs Action — does "generate a PDF"
  deserve its own identity, with its own retry/backoff configuration and its own `failed()` handling,
  separate from "react to an order being placed"? What would you do differently if a second listener
  *also* needed queued behavior with different retry settings?
- Whichever you choose, it needs `public $tries` (how many attempts before giving up) and either
  `public $backoff` or a `backoff()` method (how long to wait between attempts) — pick values you can
  justify, not defaults copied without thought.
- Implement a `failed(\Throwable $exception)` method — this runs once, after all retries are
  exhausted. For now, logging the failure with enough context to find the `Order` again is enough
  (a real system might notify an admin or the attendee here — out of scope for this lesson).
- Since this class now depends on `OrderPlaced $event` for its data but runs on a *different
  process* than the one that dispatched it, its dependency should be **serializable** — this is
  exactly what `SerializesModels` (already on `OrderPlaced` from Lesson 08) is for: it serializes
  the `Order`'s primary key, not the whole in-memory object, and re-fetches it fresh from the
  database when the job actually runs on the worker. Think about why that matters — what could be
  stale about a fully-serialized object hours later, versus a freshly re-fetched one?
- Run `php artisan queue:work` in a separate terminal (or use `composer run dev` if this project's
  `Procfile`/dev script already starts one — check `composer.json`). Trigger a purchase or gift and
  confirm the HTTP/console response returns immediately, while the job appears in the `jobs` table
  and then processes shortly after (watch the worker's output, or check Telescope's Jobs tab — this
  project already has Telescope installed).
- Force a failure (throw deliberately, or use bad input) and confirm: the retries happen (watch the
  attempt count), the `Order` is untouched, and the job eventually lands in `failed_jobs` with
  `php artisan queue:failed` showing it.

### How This Should Be Approached

Don't queue *everything* by default now that you have the tool — `LogOrderAudit` is fast and cheap;
queuing it adds latency (the audit entry won't exist until a worker picks it up) for no real
benefit. Be deliberate about which specific piece of work actually needs to be asynchronous, and
say why, the same way Lesson 07 asked you to justify Service/Action extraction rather than doing it
reflexively.

## 9. Refactoring

`TicketOrderService`, `OrderController`, and `GiftTicket` all stay exactly as they are — this is the
payoff of Lesson 08's decoupling. The only things that change are inside the listener/job layer:
one class gains `ShouldQueue` (or a new dedicated Job class appears), with its own tries/backoff/
failure handling. Nothing that dispatches `OrderPlaced` needs to know or care that one of its
listeners now runs on a different process, seconds or minutes later.

## 10. Alternatives

- **Keep it synchronous, but make it fast** (cache a pre-rendered template, use a faster PDF
  library): doesn't apply here — the work is inherently slow (real rendering, real I/O), not slow
  because of an implementation mistake. Queuing solves "this genuinely takes time," not "this is
  accidentally slow."
- **A scheduled command that batches PDF generation every minute** (`php artisan schedule`): adds
  unnecessary latency (up to a minute of delay even when a worker is idle) and complexity, for no
  benefit over a queue that processes as soon as a worker is free. Worth naming as the wrong tool
  for "run soon" work, which is what queues are for — scheduling is for "run periodically" work.
- **Synchronous but wrapped in a try/catch that swallows failures**: technically stops the attendee
  from seeing an error, but silently loses the failure — no retry, no `failed_jobs` record, nothing
  to investigate later. Worse than the problem it claims to solve.

## 11. When Not To Use It

If the user's next action genuinely depends on the work completing — e.g., they can't view their
ticket until the PDF exists, and there's no page they could land on in the meantime — queuing it
just relocates the wait, it doesn't remove it (unless you build a "check back for your ticket" UI,
which is its own scope). Also don't queue work that must be able to affect the calling code's
outcome — e.g., a payment charge has to happen synchronously enough that a failure can stop the
order from being marked paid; you wouldn't want "charge the card" to silently retry in the
background while the attendee sees a success page.

## 12. Practice

1. Implement queued PDF generation per Section 8, choosing listener-with-`ShouldQueue` vs. a
   dedicated `Job` deliberately.
2. Run a worker, trigger a purchase and a gift, and confirm both respond immediately while the job
   completes shortly after (check `storage/logs` or Telescope for confirmation the job actually ran).
3. Deliberately make the job fail (throw an exception unconditionally, temporarily) and observe the
   retry attempts in the worker output, then confirm it lands in `failed_jobs`.
4. Confirm the `Order`/`Ticket` rows are completely unaffected by the job's failure — the purchase
   already succeeded before the job ever ran.
5. Stretch goal: use `php artisan queue:retry` to manually re-run the failed job after fixing
   whatever you broke, and confirm it completes.

## 13. Review Questions

1. Why does queuing the PDF work change the attendee's actual experience, given that the `Order`
   was already committed before `OrderPlaced` even dispatched?
2. What does `SerializesModels` actually do, and why does it matter more for a *queued* listener
   than a synchronous one?
3. If a queued job fails all its retries, what happens to the `Order` it was generating a PDF for —
   and why is that the correct behavior?
4. When would you choose a dedicated `Job` class over just adding `ShouldQueue` to an existing
   listener?
5. Why shouldn't you queue everything now that the tool is available — what's the actual cost of
   queuing `LogOrderAudit` too?

## 14. Takeaways

- Queues solve a *timing* problem (this shouldn't block the response) that's distinct from the
  *coupling* problem Events/Listeners solved in Lesson 08 — the two combine, but they're separate
  decisions with separate justifications.
- A queued job runs on a different process, later — anything it depends on needs to survive that
  gap (serialization), and its failure must not be able to reach back and affect work that already
  committed.
- Retries and `failed_jobs` aren't just error handling — they're what makes "this specific failure
  mode is expected and recoverable" a first-class, observable thing instead of a silent loss.

---

## Interview Preparation

### What Interviewers May Ask

- "When would you move work onto a queue, and what's the trade-off?"
- "What happens to a queued job if it fails? How do retries work?"
- "What's the difference between a queued listener and a dedicated Job class?"
- "Why does model serialization matter for queued jobs specifically?"
- "When would you NOT want to queue something, even if it's slow?"

### What the Interviewer Is Testing

Whether you understand queues as solving a specific problem (blocking latency, failure isolation)
rather than "the fast way to make things async," whether you understand what actually happens to
data across the request/worker boundary, and whether you can reason about when synchronous
execution is the *correct* choice, not just the naive one.

### How I Should Answer

Explain the two things queuing buys you concretely: the response returns without waiting on
unrelated work, and a failure in that work can't reach back and undo something that already
succeeded. Explain serialization honestly: a queued job doesn't keep a live PHP object in memory
across the gap — it stores enough to re-fetch the real data when a worker actually runs it, which is
also *why* you don't want to queue something whose data might already be stale or gone by the time a
worker gets to it. For "when not to queue," name a case where the caller genuinely needs to know the
outcome before proceeding (payment authorization is the classic one) — a senior answer distinguishes
"slow so it should be async" from "must complete before the user can be told it worked."

### Real Interview Scenario

> "Uploading a large video file triggers thumbnail generation, transcoding to three resolutions, and
> a webhook notification to a partner API. All three currently run inline in the upload endpoint,
> and uploads are timing out. How do you fix it, and what would you watch out for?"

A strong candidate identifies all three as queue candidates (none of them need to complete before
the endpoint can respond "upload received"), and separates them into distinct jobs rather than one
monolithic job, since they can fail and retry independently. A senior candidate flags the webhook
call specifically as needing its own retry/backoff tuned differently than the encoding jobs (network
calls fail differently than CPU-bound work), and asks whether the partner API needs idempotency
protection if the webhook job retries and the first attempt actually succeeded but the response was
lost.

### Interview Difficulty

**Mid–Senior.** Dispatching a job and running a worker is junior-accessible. Reasoning about
serialization boundaries, retry/backoff design, and specifically *when a queue is the wrong choice*
is where mid-level candidates start to separate from senior ones.

---

## Laravel Interview Checklist

- Can you explain what problem queuing actually solves, distinct from what Events/Listeners solved?
- Can you explain what happens to a job's data across the request-to-worker boundary, and why that
  matters?
- Do you know what happens by default when a queued job exhausts its retries?
- Can you articulate a real case where queuing work would be the wrong choice?
- Can you explain the difference between a queued listener and a dedicated Job class, and when
  you'd reach for each?
