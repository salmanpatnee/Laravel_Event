# Lesson 05 — Validation: Form Requests

## 1. Goal

Replace the inline `$request->validate([...])` calls in `EventController::store()` and
`EventController::update()` with dedicated Form Request classes. By the end of this lesson,
validation rules for an `Event` live in one place per operation, the controller no longer knows
what "valid" means for the data it receives, and you can explain *why* that separation matters —
not just how to call `php artisan make:request`.

## 2. Current State

`EventController::store()` and `EventController::update()` each contain their own
`$request->validate([...])` block:

```
'name' => 'required|string|max:255',
'description' => 'nullable|string',
'venue' => 'required|string|max:255',
'status' => 'required|in:draft,published',
'start_time' => 'required|date',
'end_time' => 'required|date|after:start_time',
```

The two blocks are **identical**, copy-pasted between the two methods. Authorization already
moved out of the controller in Lesson 04 (`EventPolicy`); validation is the next thing still
sitting inline that has the same shape of problem.

## 3. New Requirement

> "The rules for what makes a valid `Event` should be defined once, not duplicated between create
> and update. It should be obvious from the controller that validation is happening, without the
> rule list cluttering the method body. And when validation fails, the user should be sent back to
> the form with the old input and the specific error messages — which `validate()` already gives
> you for free, so don't lose that."

## 4. Initial Implementation

Nothing new to build from scratch — same as Lesson 04, this is a **refactor** of existing,
already-working logic. Resist adding new validation rules beyond what's already there unless you
spot something Lesson 01–04 clearly missed (call it out if you do, but don't silently expand
scope).

## 5. Problem Appears

Look at the duplicated rule list and ask:

- If `TicketType` gets a `capacity` field next lesson and `Event` needs a new `venue_capacity`
  rule, do you remember to add it to *both* `store()` and `update()`? What happens if you only
  update one?
- The rule list is fairly long (six fields). Scrolling past it to find the actual persistence
  logic (`$request->user()->events()->create($validated)`) already makes `store()` harder to read
  than it needs to be — imagine that list doubling in size as the app grows.
- `update()` really has a slightly different concern than `store()` in general (e.g. "can this
  field be changed after creation" is sometimes a different question from "is this valid on
  create") — even though today the rules happen to be identical, the controller has no natural
  place to express that difference if it ever arises.

This is the same category of problem Lesson 04 solved for authorization: a concern that isn't
really about handling an HTTP request is embedded directly inside the method that handles the
HTTP request.

## 6. Concept Introduction

A **Form Request** is a class that extends `Illuminate\Foundation\Http\FormRequest` and
represents "a validated HTTP request for a specific action." You type-hint it in the controller
method instead of `Illuminate\Http\Request`, and Laravel runs its `rules()` (and, if defined,
`authorize()`) automatically *before* the controller method body executes — if validation fails,
the user is redirected back with errors and old input, exactly like `$request->validate()` does,
because it's the same underlying validator.

The difference is *where the rules live*: not inline in the controller, but in a class dedicated
to describing what a valid request for that action looks like. That class can be reused, unit
tested independently of any controller, and — because it's a distinct type — type-hinted, which
makes the controller signature itself documentation of what input it expects.

## 7. Why This Solution?

- **Single source of truth per action**: `StoreEventRequest` and `UpdateEventRequest` each define
  their own rules once, instead of the same array appearing wherever `store`/`update` logic lives.
- **Thin controllers**: the controller method body shrinks to the actual work (persist, redirect)
  — validation becomes something that already happened by the time the method runs, not something
  the method has to spell out.
- **Testability**: `rules()` is a plain method you can assert against directly, and Form Request
  authorization/validation can be tested with Laravel's `assertValid()`/`assertInvalid()` test
  helpers without touching the controller at all.
- **Room to grow independently**: `store` and `update` get separate classes even though their
  rules are identical today — if they diverge later (e.g. `update` needs to allow keeping the
  existing `status` but `store` should default it), each class can evolve without affecting the
  other.

## 8. Implementation

Don't copy the arrays verbatim without thinking — decide, for each piece, whether `store` and
`update` actually need different behavior.

**Create the Form Requests**

Use `php artisan make:request StoreEventRequest` and `php artisan make:request UpdateEventRequest`.
Note where Laravel puts them — this is your first time touching `app/Http/Requests/`, so notice
the directory convention.

**Move the rules**

Each generated class has an `authorize()` method (defaults to `return false;`) and a `rules()`
method (defaults to `return [];`). Move the relevant validation array from the controller into
`rules()`.

Think about `authorize()` specifically: this class already has access to `$this->user()` — should
it duplicate what `EventPolicy` already decided in Lesson 04, or should authorization stay
exclusively the Policy's job and this method just `return true;`? Consider what happens if the two
mechanisms ever disagree.

**Wire them into the controller**

Replace `Request $request` with `StoreEventRequest $request` (and `UpdateEventRequest $request`)
in the relevant method signatures, and replace `$request->validate([...])` with
`$request->validated()` — which returns the already-validated data instead of running validation
again.

**Confirm the redirect-back-with-errors behavior still works**

Trigger a validation failure (e.g. submit the create form with an empty `name`) and confirm you
land back on the form with the error message and previous input still populated — the same
behavior `$request->validate()` gave you, now coming from the Form Request instead.

## 9. Refactoring

`store()` and `update()` shrink further: no rule array, no `$request->validate()` call — just
`$request->validated()` feeding straight into `create()`/`update()`. Combined with Lesson 04's
`$this->authorize()` extraction, both methods should now read as almost entirely "the actual
business step," with authorization and validation both handled before the method body starts.

## 10. Alternatives

- **Keep `$request->validate()` inline**: reasonable for a single one-off form with no reuse and
  no growth expected — not this app, where the same rules already appear in two places.
- **A single shared `EventRequest` for both `store` and `update`**: possible via a conditional
  inside `rules()` (checking `$this->isMethod('PUT')` or similar), but it hides the fact that two
  different actions can have different rules behind a branch inside one class. Two classes keep
  each action's rules readable on their own, at the cost of duplication *if* they truly never
  diverge — judge for yourself once you've written both whether that trade-off holds here.
- **A dedicated validation Service/Rule object**: overkill until validation rules involve logic
  that doesn't fit Laravel's rule string/array syntax (e.g. a rule that queries multiple tables) —
  not the case for `Event` today.

## 11. When Not To Use It

Don't create a Form Request for a route with no validated input (e.g. `toggleStatus`, which
already takes no body). Also don't split every trivial single-field validation into its own class
reflexively — a one-off internal admin tool with a single text field might reasonably keep
`$request->validate()` inline if it will never be reused or grow.

## 12. Practice

1. Implement `StoreEventRequest` and `UpdateEventRequest` per the instructions above and wire them
   into `EventController`.
2. Write down (a sentence or two) what you decided for each class's `authorize()` method, and why
   — should it duplicate `EventPolicy`, defer to it, or ignore it entirely?
3. Stretch goal: trigger a validation failure through the UI and check what request the browser
   actually makes on retry — confirm old input round-trips into the form fields via `old()`.

## 13. Review Questions

1. What actually calls `rules()` and `authorize()` on a Form Request, and at what point in the
   request lifecycle does that happen relative to the controller method running?
2. Why might `authorize()` on a Form Request be redundant — or even risky — once a Policy already
   governs the same action? What happens if they disagree?
3. `validated()` vs `validate()` — what's the difference, and why does the controller only need
   one of them once a Form Request is in play?
4. If `store` and `update` need genuinely different rules for the same field in the future, is
   that evidence for keeping two separate classes, or for merging them with conditional logic?
   Justify either answer.
5. Where should Form Request rules be tested, and how is that different from testing the Policy
   from Lesson 04?

## 14. Takeaways

- Validation and authorization are two different "is this request allowed to happen" questions —
  Lesson 04 extracted the authorization one into a Policy; this lesson extracts the "is this data
  well-formed" one into a Form Request. Keeping them in separate, purpose-built classes keeps the
  distinction visible instead of blurring both into generic `abort`-shaped checks.
- A Form Request doesn't change *what* gets validated — it relocates the rules to a place where
  they're defined once, typed, and testable independently of the controller.
- Duplication across two near-identical inline blocks is worth extracting even when, today, the
  two blocks are byte-for-byte identical — the risk isn't today's duplication, it's tomorrow's
  silent divergence.

---

## Interview Preparation

### What Interviewers May Ask

- "What's the difference between `$request->validate()` and a Form Request?"
- "What does `authorize()` on a Form Request do, and how does it relate to Policies?"
- "Walk me through what happens when a Form Request's validation fails."
- "Why would you use `validated()` instead of `all()` or `input()` in a controller?"
- "When would you NOT use a Form Request?"

### What the Interviewer Is Testing

Whether you understand Laravel's request lifecycle well enough to know *when* Form Request
validation runs relative to the controller, whether you understand the (sometimes confusing)
overlap between Form Request `authorize()` and Policies, and whether you can justify extracting a
class instead of reflexively doing it for every route.

### How I Should Answer

Explain that a Form Request is a specialized `FormRequest` subclass that Laravel resolves via the
service container before the controller method runs — the container sees the type-hint, so it
instantiates the request, runs `authorize()` then `rules()`, and either proceeds into the
controller with validated data available or redirects back with errors, all before a single line
of the controller method executes. Emphasize that `validated()` returns only the fields that
passed validation (a safer subset than `all()`), and that `authorize()` on a Form Request is a
*request-level* gate that's easy to leave as `true` once a Policy already owns the "is this user
allowed" question — using both is only worth it when they're deliberately checking different
things.

### Real Interview Scenario

> "Two different endpoints in a Laravel app both validate an `Event`, and the rules just drifted
> out of sync — one accepts a `status` that the other rejects. How do you prevent this?"

A strong candidate identifies that inline `$request->validate()` calls duplicated across
controller methods are the root cause, proposes extracting the rules into a Form Request per
action (or a shared base if the rules are genuinely identical and meant to stay that way), and
notes that a test asserting `rules()` directly — rather than only feature tests hitting the HTTP
endpoint — is the fastest way to catch drift going forward.

### Interview Difficulty

**Junior–Mid.** Knowing `make:request` exists and swapping `Request` for a custom class is
junior-level. Reasoning about the `authorize()`-vs-Policy overlap, and judging when duplication is
worth extracting versus when two Form Requests should legitimately diverge, is where it becomes a
mid-level question.

---

## Laravel Interview Checklist

- Can you explain when a Form Request's `authorize()` and `rules()` run relative to the
  controller?
- Can you explain the difference between `validated()`, `all()`, and `input()`?
- Can you explain the relationship (and potential overlap) between Form Request `authorize()` and
  a Policy?
- Can you identify duplicated inline validation as a code smell before it causes rule drift?
- Do you know when a Form Request is overkill for a given route?
