# Lesson 10 — Notifications & Mail: A Real Order Confirmation

## 1. Goal

Replace the "confirmation would be sent" log stub with an actual email, using Laravel's
Notification system instead of hand-building a `Mailable`. By the end of this lesson, placing or
gifting an order results in a real, queued confirmation email landing in the attendee's inbox (or
`storage/logs/laravel.log`, since `MAIL_MAILER=log` locally) — with the queuing decision from
Lesson 09 already applied correctly, not re-litigated from scratch.

## 2. Current State

`LogOrderConfirmationStub` (Lesson 09) is a queued listener (`ShouldQueue`, `Tries(3)`,
`Backoff([5, 15])`, a `failed()` handler) that does nothing but `sleep(3)` and write a log line
saying a confirmation *would* be sent. `User` already uses the `Notifiable` trait (Laravel's
default), and `MAIL_MAILER=log` means any mail sent locally writes to `storage/logs/laravel.log`
instead of actually leaving the machine — nothing to configure, just not used yet.

## 3. New Requirement

> "Attendees need a real confirmation email after their order is placed or gifted — not a log
> line. It should tell them what they bought (event name, ticket type, quantity, order total) and
> arrive without blocking anything, same as the PDF work from last lesson. It also needs to survive
> the same kind of transient failure a real mail provider can throw — a timed-out SMTP connection,
> a rate limit — without ever affecting the `Order` it's confirming."

## 4. Initial Implementation

Before building the "real" version, it's worth seeing why a `Mailable` alone doesn't obviously
solve this. Run `php artisan make:mail OrderConfirmationMail` and look at what you get: a class
built around *composing an email* (view, subject, attachments) with no built-in concept of *who*
it's going to beyond an address you pass in manually, and no queuing/retry scaffolding of its own
beyond what any queued job gets. You'd call `Mail::to($order->user)->send(new
OrderConfirmationMail($order))` from somewhere — and you're back to deciding where that "somewhere"
is.

Don't fully build this version. Look at it, then move to Section 6 — the point is to notice the
question a `Mailable` alone doesn't answer: *if you later need to also notify the attendee in-app,
or by SMS, do you duplicate this logic in a second class, or was there a better seam to design
around from the start?*

## 5. Problem Appears

A `Mailable` describes **the content of one email**. It has no opinion about *who receives it, on
what channel(s), or how that decision might change later*. Right now the requirement is "email
only" — but the attendee is a `User`, and Laravel already gives `User` a `Notifiable` trait for
exactly this shape of problem: "this model can be notified, possibly through more than one
channel, and the notification itself should own that decision," not the code that triggers it.

There's a second, more concrete problem: last lesson's queuing decision (`Tries`, `Backoff`,
`failed()`) was hard-won. If you just swap the `Log::info()` call inside
`LogOrderConfirmationStub` for a `Mail::send()` call, you get to keep that listener's queue
configuration for free — but that's a coincidence of where you happened to put the code, not a
structural guarantee. Worth asking: should "send the confirmation" be Notification logic that the
listener merely triggers, or should the listener itself become the thing that composes the email?

## 6. Concept Introduction

A **Notification** is a class representing "something the user should be told" — decoupled from
*how* they're told it. A single notification class can define a `toMail()` method (returning a
`Mailable`-like message built with `Illuminate\Notifications\Messages\MailMessage`), a
`toDatabase()` method, a `toArray()` method for broadcast, etc. — and a `via()` method that decides
which channel(s) apply, per-notifiable, at send time.

You send it with `$user->notify(new OrderConfirmation($order))` — using the `Notifiable` trait
already on `User` — rather than `Mail::to($user)->send(...)`. The notification can implement
`ShouldQueue` itself, which queues *the whole send* (rendering the message and dispatching it)
independently of whatever dispatched it.

## 7. Why This Solution?

- **The notification owns the "how" decision, not the caller.** `LogOrderConfirmationStub` (or
  whatever triggers it) doesn't need to know it's specifically email — today it is, but that
  decision now lives in one place (`via()`), not scattered across every call site that wants to
  notify an attendee.
- **This is the same shape of decision as Service vs. Action (Lesson 07) and Queue vs. sync
  (Lesson 09).** A `Mailable` is the right tool when you're sending an email to an arbitrary
  address that isn't necessarily a `User` (a receipt to a guest checkout email, a report to an
  admin distribution list) — a Notification is the right tool when the recipient is a notifiable
  model and the channel is a decision, not a given.
- **Queuing the Notification keeps Lesson 09's guarantee intact**: a failure composing or sending
  the email still can't reach back and affect the `Order`.

## 8. Implementation

### Task

Replace `LogOrderConfirmationStub`'s stub log line with a real, queued `OrderConfirmation`
notification sent to the attendee.

### Instructions

- `php artisan make:notification OrderConfirmation`. Give it a constructor-promoted `Order $order`
  property, matching this project's convention.
- Implement `via(object $notifiable): array` returning `['mail']` — that's the whole "decision,"
  and it's now explicit and in one place rather than assumed.
- Implement `toMail(object $notifiable): MailMessage`, building a message with the event name,
  ticket type, quantity, and order total. Pull this data off `$this->order` — think about what
  needs to be eager-loaded (or re-fetched, given `SerializesModels`) for `$this->order->event` and
  the ticket details to be available without triggering N+1 queries per notification.
- Decide: should `OrderConfirmation` implement `ShouldQueue` itself, or should
  `LogOrderConfirmationStub` (already `ShouldQueue`) simply call `$event->order->user->notify(...)`
  synchronously *within* its own already-queued `handle()`? Both end up async overall — reason
  about whether they behave identically for retries/backoff/`failed()`, or whether stacking two
  independently-queued things creates a subtlety worth avoiding. (Hint: what job actually appears
  in the `jobs` table, and does it retry as one unit or two?)
- Whichever you choose, drop the `sleep(3)`/`Log::info('order.confirmation_stub', ...)` body from
  `LogOrderConfirmationStub` and replace it with the real notify call — keep the class's `Tries`,
  `Backoff`, and `failed()` from Lesson 09 if you decided the listener itself should own queuing;
  adjust if you decided the notification should own it instead.
- Run a purchase and a gift with `composer run dev` (or `queue:listen`) running, and check
  `storage/logs/laravel.log` for the rendered email (that's what `MAIL_MAILER=log` produces instead
  of an actual SMTP send).
- Force a failure (bad view, deliberately throw) and confirm retry/backoff/`failed_jobs` behavior
  still holds, same as Lesson 09's verification.

### How This Should Be Approached

`OrderConfirmation` should depend only on the `Order` it's confirming (and whatever the `MailMessage`
builder needs from it) — not on `TicketOrderService`, not on the listener that triggers it. The
listener's job shrinks to "trigger the notification," the same way `TicketOrderService` shrank to
"dispatch the event" in Lesson 08. Don't add a second channel (database/SMS) just because the
system now supports it — `via()` returning `['mail']` is the correct, honest scope for what's
actually required right now.

## 9. Refactoring

`LogOrderConfirmationStub` no longer contains any mail-composition logic — it becomes a thin
trigger (`$event->order->user->notify(new OrderConfirmation($event->order))` or equivalent),
keeping (or handing off, per your Section 8 decision) the retry/backoff/failure handling from
Lesson 09. `TicketOrderService`, `OrderController`, `GiftTicket`, and `OrderPlaced` are unaffected
— same pattern as Lesson 09's refactor: the change is fully contained behind the listener layer.

## 10. Alternatives

- **A plain `Mailable` sent directly from the listener**: works, and is the right call if the
  recipient were *not* a `Notifiable` model (e.g., a one-off address with no `User` record) — but
  here the recipient is always a `User`, so a Notification is the better-fitting abstraction for
  "this specific user should be told this specific thing."
- **Sending the email inline from `TicketOrderService`**: reintroduces exactly the coupling problem
  Lesson 08 solved — don't.
- **A generic `Notify` listener that fans out to email/SMS/database from one class**: premature.
  Nothing today requires more than one channel; add channels to `via()` when a real requirement
  asks for them, not preemptively.

## 11. When Not To Use It

If the "notification" isn't really about a `Notifiable` model at all — a nightly report emailed to
an ops distribution list, a receipt sent to a guest checkout's email with no `User` record — a
plain `Mailable` is more honest than forcing a fake `Notifiable` wrapper around something that
isn't a user. Notifications earn their keep specifically when the recipient is a model in your
system and the channel is a genuine decision, not a given.

## 12. Practice

1. Implement `OrderConfirmation` and wire it into `LogOrderConfirmationStub` per Section 8.
2. Trigger a purchase and a gift, and confirm the rendered email appears in
   `storage/logs/laravel.log` with correct event/ticket/total details.
3. Force a failure and confirm retries + `failed_jobs` still work exactly as they did in Lesson 09.
4. Re-run `php artisan test --compact` and confirm nothing regresses.
5. Stretch goal: write a test using `Notification::fake()` that asserts `OrderConfirmation` was
   sent to the correct `User` after a purchase, without asserting on log/email content directly.

## 13. Review Questions

1. What does a Notification's `via()` method let you do that a plain `Mailable` doesn't?
2. If both `LogOrderConfirmationStub` and `OrderConfirmation` implement `ShouldQueue`, what
   actually happens — how many jobs land in the `jobs` table, and does that change how retries
   behave?
3. Why is `User` already able to receive a Notification without any new setup — what does the
   `Notifiable` trait actually provide?
4. When would a plain `Mailable` still be the better choice over a Notification, even in an app
   that already has `Notifiable` models?
5. What data does `toMail()` need eager-loaded (or freshly fetched) to avoid N+1 queries, given
   that `$this->order` arrives via `SerializesModels` on a separate worker process?

## 14. Takeaways

- A Notification separates *what the user should be told* from *how they're told it* — the same
  decoupling instinct as Events/Listeners, applied to the recipient-and-channel decision instead of
  the "what happens next" decision.
- Queuing a Notification composes with, but is a separate decision from, queuing the listener that
  triggers it — stacking both without reasoning about it can produce two independently-retrying
  jobs instead of one coherent unit of work.
- Reach for `Mailable` when the recipient isn't a `Notifiable` model; reach for Notifications when
  it is and the channel might not always be email.

---

## Interview Preparation

### What Interviewers May Ask

- "What's the difference between a Mailable and a Notification in Laravel?"
- "How does Laravel decide which channel(s) a notification goes through?"
- "Can a Notification be queued? What happens if it fails?"
- "When would you use `Mail::to()->send()` instead of `$user->notify()`?"
- "How do you test that a notification was sent, without actually sending it?"

### What the Interviewer Is Testing

Whether you understand Notifications as a distinct abstraction from Mail (not just "the newer way
to send email"), whether you can reason about what happens when two queueable things are
stacked, and whether you know Laravel's testing tools well enough to avoid asserting on
side-effect output (log lines, rendered HTML) instead of the actual dispatched intent.

### How I Should Answer

Lead with the actual distinction: a `Mailable` is *content*, a `Notification` is *intent plus a
channel decision*, resolvable per-recipient at send time via `via()`. Explain that `Notifiable` is
what makes `$model->notify()` possible — it's a trait, not magic tied to `User` specifically; any
model can use it. For the "two queued things stacked" question, explain concretely: if both the
listener and the notification implement `ShouldQueue`, the listener's queued job runs, and *inside*
it, sending the notification enqueues a *second* job — two separate retry lifecycles, which can
mean a listener retry re-sends a notification that already succeeded, or a notification retry
happens under settings the listener's own `Tries`/`Backoff` never intended. State the fix plainly:
pick one layer to own the async/retry behavior, not both.

### Real Interview Scenario

> "A user completes checkout and needs to receive an email receipt, an in-app notification bell
> update, and (for enterprise accounts only) a Slack message to their account's connected
> workspace. How would you structure this in Laravel?"

A strong candidate reaches for a single Notification with a `via()` method that conditionally
includes `'mail'`, `'database'`, and a custom Slack channel based on the notifiable's account tier
— rather than three separate triggers scattered across the checkout flow. A senior candidate flags
that the Slack channel is an external API call and should be considered for its own retry/backoff
tuning independent of mail delivery, and asks whether a failure on one channel (Slack down) should
be allowed to block or retry the other channels (mail, database) that already succeeded — surfacing
that Laravel sends channels independently, so a senior answer confirms that's actually true rather
than assuming it.

### Interview Difficulty

**Mid–Senior.** Creating a Notification and implementing `via()`/`toMail()` is junior-accessible.
Reasoning about queue-stacking behavior, multi-channel failure isolation, and Notification vs.
Mailable trade-offs is where the conversation gets senior.

---

## Laravel Interview Checklist

- Can you explain the actual difference between a Notification and a Mailable, not just "one is
  newer"?
- Can you explain what happens when a queued listener triggers a queued notification — how many
  jobs, how many retry lifecycles?
- Do you know what `Notifiable` provides, and that it isn't specific to a `User` model?
- Can you name a case where a plain `Mailable` is still the right choice over a Notification?
- Can you explain how to test that a notification was sent without relying on log or rendered
  output?
