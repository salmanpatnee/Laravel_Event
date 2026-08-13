# Memory — Laravel Progressive Architecture Training Course

Last updated: 2026-08-13

## What was built

- **Lesson 06 — Purchasing Flow completed** (pushed to `origin/main` as of this session):
  - `OrderController::store()` fixed to wrap the purchase in `DB::transaction()` with
    `TicketType::lockForUpdate()->findOrFail()` re-fetched *inside* the transaction, before the
    availability check — closes the check-then-act oversell race.
  - `docs/course/06-purchasing-flow.md` and roadmap entry committed and pushed.
- **Lesson 07 — Service Extraction completed**, all pushed except the last 4 commits (see Current
  State):
  - `docs/course/07-service-extraction.md` drafted — forcing function is a `GiftTicket` Artisan
    command that must obey the same never-oversell rule as web checkout, exposing duplicated
    business logic across two callers.
  - `app/Console/Commands/GiftTicket.php` created: naive version first (duplicated the
    transaction/lock/availability/order logic verbatim, on purpose, to feel the smell), then
    refactored to delegate to the extracted service.
  - `app/Services/TicketOrderService.php` created (`App\Services\TicketOrderService::order(int
    $ticketTypeId, int $quantity, int $userId): Order`) — the single place the
    transaction/lock/availability/order/ticket-creation logic now lives. Returns the `Order` model
    (not just an id — avoids a redundant re-query the first version had).
  - `app/Exceptions/TicketUnavailableException.php` created — replaces `ValidationException` as
    what the service throws on insufficient availability. Carries `ticketType`/`requested`/
    `available`; has a `render(Request $request)` method so Laravel's HTTP layer auto-converts it
    to a redirect-back-with-`quantity`-error (same as before, zero controller changes needed).
    `GiftTicket::handle()` explicitly catches it, prints via `$this->error()`, returns
    `Command::FAILURE` — fixes a real regression where the console command used to dump an
    uncaught-exception stack trace instead of a clean message.
  - `OrderController` updated to constructor-inject `TicketOrderService` and delegate; `GiftTicket`
    also fixed to `return Command::SUCCESS` (was incorrectly `return 1` on the success path after
    an earlier pass).
- **Lesson 08 — Events & Listeners drafted, not yet implemented or committed**:
  - `docs/course/08-events-listeners.md` created (untracked). Forcing function: after an order is
    placed (paid or gifted), the platform needs an audit log entry and a "confirmation email would
    be sent here" stub — two independent reactions that shouldn't require `TicketOrderService` to
    know about them. Plan: `OrderPlaced` event (carries the `Order`), two listeners
    (`LogOrderAudit`, `LogOrderConfirmationStub`), dispatched *after* `DB::transaction()` returns
    (not inside the closure) so side effects never run against uncommitted work and don't extend
    the row-lock hold time.
  - `docs/course/README.md` has an uncommitted edit marking Lesson 08 "Ready" (linked to the new
    doc) — not yet committed.

## Decisions made

- Course convention confirmed: roadmap status "Ready" = lesson doc drafted, instructions-only, not
  yet implemented; "Implemented" = code done and tests passing. Applied consistently for Lessons
  06–08.
- Domain exceptions (like `TicketUnavailableException`) should describe the business failure in
  caller-agnostic terms, not reuse framework exceptions shaped for one specific transport (e.g.
  `ValidationException` is HTTP-shaped — it rendered fine from the controller but dumped a raw
  stack trace from the console). Each caller (HTTP controller, console command) owns its own
  translation of the failure into a response; Laravel's renderable-exception (`render()` method)
  pattern lets the HTTP side get this for free while console still needs an explicit `try/catch`.
- Authorization inside a console command is a different question than HTTP authorization: there's
  no session/acting user, so `$this->authorize()` doesn't apply. `OrderPolicy::create()` happens to
  be reusable via `Gate::forUser($attendee)->authorize(...)` since it only takes a plain `User` —
  but that only authorizes the *recipient's* eligibility, not "who is allowed to run this command,"
  which is a separate, still-unresolved question (see Open Questions).
- Lesson 08 deliberately keeps both new listeners synchronous (no `ShouldQueue`) — Queues are
  Lesson 09's concept and the course avoids conflating two new ideas in one lesson.
- Lesson 08 deliberately stubs the confirmation email as a log line rather than building a real
  Mailable — actual Mail/Notifications is Lesson 10's stated scope in the roadmap; building it now
  would preempt that lesson.

## Problems solved

- `GiftTicket` returning exit code `1` on success (should be `0`/`Command::SUCCESS`) — a real bug
  introduced mid-refactor, caught in review and fixed.
- `GiftTicket` dumping an uncaught-exception stack trace on insufficient availability instead of a
  clean CLI error — fixed via `TicketUnavailableException` + explicit `try/catch` in the command.
- Redundant DB round-trip in `OrderController::store()` (`Order::findOrfail($orderId)` right after
  the service already had the `Order` in hand) — fixed by having the service return the `Order`
  model instead of just its id. Also fixed a `findOrfail` casing typo in the process.
- `TicketOrderService` was initially placed at `app/TicketOrderService.php` (namespace `App`),
  breaking the project's convention of namespacing everything under a subdirectory — moved to
  `app/Services/TicketOrderService.php` (`App\Services\TicketOrderService`).

## Current state

- `main` is 4 commits ahead of `origin/main` (`ecfee31`, `0734fda`, `ee08aa5`, plus whatever Lesson
  08 draft commit follows) — **not yet pushed**. Confirm before assuming `origin/main` is current.
- Working tree has uncommitted changes: `docs/course/README.md` (Lesson 08 marked "Ready", linked)
  and untracked `docs/course/08-events-listeners.md` — not yet committed.
- `php artisan test --compact`: 4 pass, 1 **intentionally** fails (the hand-simulated race test in
  `OrderPurchaseTest` — it bypasses the controller entirely by design per its own docblock, so it
  will always show the oversell regardless of the real fix; this is expected, not a regression).
- Lesson 07's `GiftTicket` still has **no input validation and no "who's allowed to run this"
  authorization** — discussed at length (in-console `Validator::make()` + `exists`/`Rule::exists`
  for existence+role checks) but not yet implemented. Explicitly flagged as an open gap, not
  overlooked.
- Carried over, still unresolved: `EventPolicy::delete()` duplicates `update()`'s expression rather
  than deferring to it; older Lesson 01 findings (index ordering, leftover `canceled` status
  option) — never revisited.

## Next session starts with

1. **Commit and push Lesson 08's draft**: stage `docs/course/README.md` +
   `docs/course/08-events-listeners.md`, commit, then push all pending commits to `origin/main`
   (last confirmed push left it at `183a38c`; 4+ commits are now ahead).
2. **Implement Lesson 08**: `php artisan make:event OrderPlaced`, two listeners
   (`LogOrderAudit`, `LogOrderConfirmationStub`), move the dispatch call into
   `TicketOrderService::order()` *after* the `DB::transaction()` closure returns, verify with
   `php artisan event:list` that both are auto-discovered, and confirm both fire for both
   `OrderController::store()` and `GiftTicket`.
3. Independently, decide whether to close out Lesson 07's open gap (`GiftTicket` validation +
   authorization) before or after Lesson 08 — it was deliberately left for later, not forgotten.

## Open questions

- Push of the 4+ unpushed commits still unconfirmed for this session — don't assume `origin/main`
  is current without checking `git status` first.
- `GiftTicket` still needs: argument validation (`exists:events,id` / `exists:ticket_types,id` /
  `Rule::exists('users','id')->where('role', RoleEnum::Organizer)` style checks) and a decision on
  the command's own authorization boundary (infra-level SSH/deploy trust vs. an explicit
  "acting-as-organizer" argument checked against a policy) — discussed conceptually, not
  implemented.
- Should `EventPolicy::delete()`'s duplication of `update()`'s logic be cleaned up, or left as a
  deliberate teaching artifact?
- Lesson 08's two listener class names (`LogOrderAudit`, `LogOrderConfirmationStub`) were suggested
  in the lesson doc but are explicitly "your call" per the doc's own instructions — confirm actual
  names once implemented, since this memory names them provisionally.
