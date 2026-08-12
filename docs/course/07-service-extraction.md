# Lesson 07 — Service Extraction: Service vs Action

## 1. Goal

Recognize the exact moment business logic has outgrown a controller, and extract it into a
dedicated class — deciding deliberately between a **Service** and an **Action** rather than
reaching for either out of habit. By the end of this lesson you'll have moved the purchase logic
out of `OrderController::store()` into its own class, reused it from a second entry point that has
no HTTP request at all, and be able to explain *why* that second entry point is what actually
justified the extraction — not "controllers should be thin" as a rule of thumb.

## 2. Current State

`OrderController::store()` (Lesson 06) now does real work: authorization, pulling validated input,
opening a `DB::transaction()`, locking the `TicketType` row, computing availability, rejecting
over-quantity requests, creating the `Order`, and looping to create `Ticket` rows. It's correct and
it's tested, but it's also the single largest method in the application, and every line of business
logic in it is reachable only through an HTTP `POST` request that has already been through
`StoreOrderRequest`.

## 3. New Requirement

> "Organizers want to comp free VIP tickets to specific attendees — sponsors, staff, contest
> winners — without those attendees going through the public checkout form. This needs to be
> runnable from the command line (an Artisan command an organizer or admin can trigger), and it
> must obey the exact same rules as a normal purchase: it still has to respect `TicketType`
> availability and must never oversell, even if a console-issued comp and a real purchase race each
> other."

Read that last sentence again: the rule ("never oversell, ever, under concurrency") isn't specific
to HTTP purchases — it's a property of the `TicketType` inventory itself. Whatever enforces it has
to be reachable from *any* caller, not just `OrderController`.

## 4. Initial Implementation

**Resist the urge to solve this "the fast way" first.** Before extracting anything, try writing a
new Artisan command (`php artisan make:command GiftTicket` is a reasonable name, your call) whose
`handle()` method does the same thing `OrderController::store()` does: load the `TicketType`,
open a transaction, lock the row, check availability, create the `Order` and `Ticket` rows.

You'll immediately run into friction:

- `StoreOrderRequest` is bound to the HTTP request lifecycle — it validates `$request` input and
  authorizes via a policy that expects an authenticated web user. A console command has neither.
- You'll end up either copy-pasting the transaction/lock/availability block into the command, or
  contorting the command into constructing a fake `Request` just to reuse `OrderController::store()`
  — both are worth trying once specifically so the smell is obvious, not hypothetical.

## 5. Problem Appears

Copy-pasting the lock-and-check logic into the command is the actual bug waiting to happen: you
now have **two independent implementations of the same business rule** ("never sell more than
`remaining_quantity`"). The moment someone fixes a bug or changes a rule in one copy and forgets
the other, the invariant Lesson 06 worked to guarantee is silently broken again — and nothing will
catch it, because each copy has its own tests passing in isolation.

This is a different problem than Lesson 06's. That lesson was about a **race condition** — two
requests, one code path, timing. This one is about **duplication of business logic across two code
paths** that both need to obey the same rule. The fix categories are different: locking solves the
first, extracting a single source of truth solves the second.

## 6. Concept Introduction

**A Service** is a class that groups a set of *related* business operations behind one boundary —
for example, a `TicketOrderService` might expose `purchase()`, `cancel()`, and `refund()`, all
operating on the same conceptual area (orders), often sharing constructor dependencies. It's a good
fit when you can already see multiple related operations clustering together, or expect to soon.

**An Action** (sometimes called a Command object, not to be confused with Artisan commands) is a
single class doing exactly one thing, typically invoked via `__invoke()` — e.g. a `PurchaseTickets`
class that does nothing but purchase tickets. It's a good fit when the operation is a single,
nameable business action that doesn't obviously belong to a cluster yet.

Both solve the actual problem here (one class, multiple callers, one source of truth). The
difference is organizational, not functional — which is exactly why this decision needs a deliberate
"why," not a coin flip. Section 7 walks through how to make that call for *this* codebase.

## 7. Why This Solution?

Whichever of the two you pick, extracting *something* is justified here because:

- **The business rule now has two legitimate callers** (HTTP purchase, console comp) that must not
  diverge. A single class is the only way to guarantee that.
- **The extracted class becomes testable without HTTP.** You can unit-test "purchasing 2 tickets
  when 1 remains throws" by instantiating the class directly and calling it — no `actingAs()`, no
  `route()`, no `RefreshDatabase`-backed feature request cycle required for that specific test.
- **It removes an accidental coupling.** Right now, the only way to run this business rule is to
  go through Laravel's HTTP kernel. That was never actually a requirement — it was just where the
  code happened to be written.

Now the Service-vs-Action call, specifically for *this* operation: think about whether "purchasing
tickets" is likely to soon sit next to sibling operations on the same object (cancel an order,
refund an order — both already implied by the `Order`/`Ticket` model design) or whether it's better
to keep it a single, sharply-scoped action for now and extract siblings as their own classes only
when *they* show up. There's a real trade-off either way — form your own answer before reading
Section 10's comparison, and be ready to defend it.

## 8. Implementation

### Task

Extract the purchase logic out of `OrderController::store()` into a standalone class, then reuse
that class from a new `GiftTicket` Artisan command.

### Instructions

- Create the class (`php artisan make:class` for a plain PHP class — Laravel has no `make:action`,
  and `make:service` doesn't exist either; both patterns are just conventions, not built-in
  Artisan resources). Put it under a directory name that matches whichever pattern you chose
  (`app/Services/` or `app/Actions/`).
- Its public entry point should accept **plain, typed parameters** — a `User` (or attendee id), a
  `TicketType` (or id), and an `int $quantity` — not a `Request` or `StoreOrderRequest`. This is the
  detail that actually makes it reusable: validation and authorization stay in the HTTP layer
  (Form Requests, Policies), and the extracted class only ever deals with values it can trust have
  already been checked.
- Move the `DB::transaction()` + `lockForUpdate()` + availability check + `Order`/`Ticket` creation
  into that class's method, unchanged in behavior. Have it return the created `Order` (or throw
  `ValidationException` on insufficient availability, same as before).
- `OrderController::store()` keeps `$this->authorize(...)` and `StoreOrderRequest` validation, then
  calls the extracted class with the validated values and handles the redirect/response. It should
  shrink to a handful of lines.
- Build `GiftTicket` (or your chosen name) as an Artisan command taking an event/ticket-type
  identifier, an attendee identifier, and a quantity as arguments. It resolves the models itself
  (no Form Request, no HTTP auth — think about what *should* replace authorization here: should
  any user be able to run this command, or does it need its own guard?), then calls the same
  extracted class.
- Decide how Laravel should give you an instance of the extracted class: constructor-injected into
  the controller and the command (letting the service container resolve it), or instantiated
  directly with `new`. If it has no constructor dependencies of its own, both work — which one is
  more consistent with how the rest of this app already gets its dependencies?

### How This Should Be Approached

The extracted class should have **no knowledge that HTTP or a console command exist** — it takes
values, enforces the business rule, returns/throws. That's what makes it callable from anywhere.
Authorization and input validation are concerns of the *caller* (controller, command), not the
extracted class — don't duplicate `$this->authorize()` checks inside it. Keep the transaction
boundary exactly where Lesson 06 put it (wrapping the read-check-write, nothing more).

Common mistakes to avoid: passing the raw `$request` into the extracted class "for convenience"
(recreates the HTTP coupling you're trying to remove); putting validation logic inside the
extracted class instead of the Form Request (now you have two places quantity gets validated);
making the class's method `static` (harder to mock in tests, and forecloses constructor DI if the
class ever needs a dependency later, e.g. an event dispatcher in Lesson 08).

## 9. Refactoring

`OrderController::store()` goes from doing everything to doing three things: authorize, validate,
delegate. The extracted class becomes the *only* place `TicketType::lockForUpdate()` and the
availability rule appear in the codebase. The console command becomes a second thin caller with the
same shape as the controller — authorize/resolve input, delegate, no business logic of its own.
Nothing about the *behavior* changes; Lesson 06's transaction/locking guarantee moves house intact.

## 10. Alternatives

- **A shared trait** mixed into both the controller and the command: still duplicates the fact that
  two classes now "own" the logic, doesn't give you a single mockable unit for testing, and traits
  can't have constructor-injected dependencies cleanly. Rejected for the same underlying reason
  duplication was rejected.
- **A static method on `Order`** (e.g. `Order::purchase($attendee, $ticketType, $quantity)`): keeps
  everything in one place, technically, but couples the `Order` Eloquent model itself to
  orchestration logic that touches a *different* model (`TicketType`) and enforces a business rule
  that isn't really about persistence — this is usually called a "fat model" smell. It also makes
  the operation hard to substitute/mock in tests of code that calls it, since static calls bypass
  the container.
- **An Action, if you chose Service (or vice versa)**: revisit Section 6 — could you defend the
  opposite choice just as well? If related operations (`cancel`, `refund`) show up in the next
  lesson or two, does that retroactively change which was right?

## 11. When Not To Use It

Don't extract a class the moment any controller method exceeds some line count, and don't extract
"because that's what the last lesson did." If `OrderController::index()` or `show()` ever need
non-trivial logic, ask the same question this lesson asked: is there a second caller, a reuse need,
or a genuine testability problem — or is it just a controller method doing controller-shaped work
(fetching a model, passing it to a view)? Extraction that isn't paying for a real problem adds an
indirection layer someone has to read through for no benefit.

## 12. Practice

1. Extract the purchase logic per Section 8, choosing Service or Action deliberately, and update
   `OrderController::store()` to delegate to it.
2. Build the `GiftTicket` Artisan command and confirm it can successfully create an `Order` and
   `Ticket` rows for an attendee without touching any HTTP route.
3. Write a unit test that instantiates the extracted class directly (no `actingAs()`, no `route()`)
   and asserts it throws when quantity exceeds availability — confirm this test is meaningfully
   faster/simpler to write than the equivalent feature test.
4. Re-run the full suite (`php artisan test --compact`) and confirm `OrderPurchaseTest` still
   passes unchanged — the refactor should not have altered observable behavior.
5. Stretch goal: make the console command and the HTTP controller race against each other for the
   last ticket (similar spirit to Lesson 06's race test) and confirm the lock still holds across
   both callers, since they now share the exact same locking code.

## 13. Review Questions

1. What was the concrete signal that it was time to extract this logic — not "controllers should
   be thin" in the abstract, but the specific thing that happened in this lesson?
2. What's the actual difference between a Service and an Action, and which did you choose for
   `purchase`? What would make you choose differently if `cancel`/`refund` were added next?
3. Why does the extracted class accept a `TicketType`/`User`/`int`, and not a `Request` or
   `StoreOrderRequest`? What would reusability look like if it *did* depend on the request?
4. Why does authorization stay in the controller/command instead of moving into the extracted
   class?
5. If this operation later needed to run inside a queued job (Lesson 09), what about the current
   design makes that easy or hard?

## 14. Takeaways

- Extraction is justified by a concrete forcing function — a second caller, a duplication risk, a
  testability gap — not by a class getting "big" in the abstract.
- Service vs Action is an organizational decision about *what else will live near this code*, not
  a correctness decision — both fully solve the reuse problem this lesson raises.
- Moving business logic out of the transport layer (HTTP controller, console command) into a plain
  class is what makes that logic callable from *anywhere* — including places that don't exist yet.

---

## Interview Preparation

### What Interviewers May Ask

- "When would you extract a Service or Action out of a controller, and how do you decide which?"
- "This controller has grown business logic used by multiple entry points — how would you
  refactor it?"
- "What's the difference between a Service class and an Action class in Laravel?"
- "Why not just call the controller method from a console command?"
- "How do you decide whether an abstraction is actually justified, versus premature?"

### What the Interviewer Is Testing

Whether you extract based on a real, articulable problem rather than reflexive "best practice,"
whether you understand that Services and Actions solve the same underlying problem differently
(organization, not capability), and whether you know why business logic shouldn't be coupled to
the HTTP request lifecycle.

### How I Should Answer

Name the forcing function first: a second caller needing the exact same rule, with the real risk
being that duplicated logic drifts apart over time. Explain that the extracted class should depend
only on plain values, not a `Request`, because that's specifically what makes it reachable from
non-HTTP contexts (console commands, queued jobs, other controllers). For Service vs Action,
explain it's about whether you're grouping several related operations under one roof (Service) or
isolating one sharply-scoped operation (Action) — and that the "right" choice can change as the
codebase grows siblings. Be ready to say when you'd refuse to extract anything at all.

### Real Interview Scenario

> "The same 'apply a discount and update the order total' logic is duplicated in a controller and
> a scheduled job that processes back-orders nightly. How do you fix it, and how do you decide what
> kind of class to extract it into?"

A strong candidate identifies the duplication as the actual risk (not the code length), proposes a
single extracted class with a plain method signature callable from both contexts, and explains the
Service-vs-Action trade-off in terms of what else already lives near that logic. A senior candidate
also flags that authorization/validation should stay with each caller, not move into the extracted
class, since the job and the controller may have entirely different rules for who's allowed to
trigger the operation.

### Interview Difficulty

**Mid–Senior.** Recognizing *when* to extract is more senior judgment than the extraction itself,
which is mechanical once decided. Junior candidates often either never extract (controllers keep
growing) or extract reflexively for every method (needless indirection) — neither shows the
judgment being tested here.

---

## Laravel Interview Checklist

- Can you name the concrete signal that justifies extracting a Service/Action, rather than doing it
  by convention?
- Can you explain the practical difference between a Service and an Action, and argue either side
  for a given piece of logic?
- Do you know why an extracted class should depend on plain values instead of a `Request` object?
- Can you explain why authorization/validation stay at the transport layer instead of moving into
  the extracted business logic?
- Can you identify when extraction is *not* worth it, and explain the cost of doing it anyway?
