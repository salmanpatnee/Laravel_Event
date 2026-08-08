# Lesson 01 — Foundation: Routing, Controllers, Blade, and the First Model

## 1. Goal

Stand up the Laravel application and build the simplest possible version of the core entity in
this course: **Events**. By the end of this lesson, an organizer (no login required yet) can
create, list, view, edit, and delete events through a browser.

## 2. Current State

Nothing exists yet. This is a brand-new Laravel application.

## 3. New Requirement

> "We're building a platform where organizers publish events. Before anything else, we need
> basic event management: create an event, see a list of events, view one event's details,
> edit it, and delete it."

No authentication, no ticket types, no purchasing — just events, because everything else in this
course is built on top of the `Event` entity.

## 4. Initial Implementation

This is intentionally the simplest reasonable implementation: **one model, one controller, plain
Blade views, validation written directly in the controller.** No Service, no Repository, no Form
Request yet — there is no problem yet that would justify them.

### What to build

**Environment setup**

- Scaffold a fresh Laravel application in this directory (see "Scaffolding Instructions" below —
  you'll run this yourself).
- Configure `.env` for MySQL.
- Confirm the app boots with `php artisan serve` and the default welcome page loads.

**Migration**

Create a migration for an `events` table with these columns:

- `name` (string)
- `description` (text, nullable)
- `venue` (string)
- `starts_at` (datetime)
- `ends_at` (datetime)
- `status` (string — values like `draft` and `published`; default `draft`)
- standard `id` and `timestamps`

Think about which columns should be nullable and why (e.g., is `description` optional in a real
event platform? Probably. Is `venue` optional? Probably not).

**Model**

Create an `Event` Eloquent model. Add `starts_at` and `ends_at` to the model's `$casts` as
`datetime` so they come back as Carbon instances instead of raw strings — you'll want that for
formatting in Blade later. Add `name`, `description`, `venue`, `starts_at`, `ends_at`, and
`status` to `$fillable`.

**Controller**

Create a resource controller for events (`php artisan make:controller EventController --resource`
generates the method stubs — `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`).
Implement all seven methods:

- `index` — list all events, most recently created first.
- `create` — show the "new event" form.
- `store` — validate input directly in the controller method (using `$request->validate([...])`),
  create the `Event`, redirect to the event's `show` page with a success flash message.
- `show` — display one event.
- `edit` — show the edit form, pre-filled.
- `update` — validate, update the record, redirect back to `show`.
- `destroy` — delete the record, redirect to `index`.

**Routes**

Register a single resourceful route (`Route::resource('events', EventController::class)`) in
`routes/web.php`. Think about why a single `Route::resource()` call is preferable here to writing
seven individual `Route::get`/`Route::post` lines — what does the resourceful convention buy you,
and what would you lose if you needed a route that didn't fit the convention (e.g., "publish this
event")?

**Blade views**

Create views under `resources/views/events/`:

- `index.blade.php` — table or list of events with links to view/edit/delete each, and a link to
  create a new one.
- `create.blade.php` — a form for a new event.
- `edit.blade.php` — a form for editing, pre-filled with the event's current values.
- `show.blade.php` — event details.

Use a shared layout (`resources/views/layouts/app.blade.php` with `@yield`/`@section`, or a Blade
component if you prefer) so you're not repeating `<html>`/`<head>` boilerplate in every view.
Display validation errors (`$errors`) and old input (`old('field')`) on the create/edit forms —
this matters even before Form Requests are introduced, because Laravel's redirect-back-with-errors
behavior is a core convention you'll rely on throughout the course.

### Scaffolding Instructions (you run these)

1. From `E:\code\Laravel_Architecture_Trainning`, scaffold Laravel. Either:
   - `composer create-project laravel/laravel .` (installs into the current directory), or
   - if you have the Laravel installer: `laravel new .`
2. Open `.env` and set the `DB_*` variables for MySQL (`DB_CONNECTION=mysql`, plus host/port/database/username/password matching your local MySQL setup — e.g. Herd's default MySQL service if that's what you're running).
3. Create the database itself (e.g. via your MySQL client, or `php artisan db:create` if you're on a Laravel version/tooling that supports it — otherwise create it manually).
4. Run `php artisan serve` and confirm the default welcome page loads at the printed URL.
5. Come back here once that's working and build the Event feature described above.

## 5. Problem Appears

There isn't one yet — and that's fine. At this size, a plain controller with inline validation
*is* the correct architecture. Adding a Service, Repository, or Form Request right now would be
solving problems you don't have. Keep this in mind for later lessons: the course will deliberately
let this controller start to feel uncomfortable before introducing anything new.

## 6. Concept Introduction

Not applicable this lesson — no new pattern is being introduced. This lesson establishes the
baseline: routing, controllers, Eloquent models/migrations, and Blade.

## 7. Why This Solution?

Laravel's MVC-ish structure (routes → controller → model/view) is the right default for a feature
this small. A resourceful controller maps directly onto the CRUD operations you need, Eloquent
gives you a expressive model for a single table with no complex relationships yet, and Blade gives
you server-rendered views with minimal ceremony. Every additional abstraction (Service, Repository,
Form Request, DTO) has a cost — more files, more indirection, more to learn to trace a request —
and right now nothing is buying back that cost.

## 8. Implementation

See "What to build" above — that *is* the implementation guidance for this lesson, deliberately
given as instructions rather than code (see the course's Constraint 1). Implement it yourself,
then come back with questions or your implementation for review.

## 9. Refactoring

Nothing to refactor yet — this is the first lesson.

## 10. Alternatives

- **API Resource controller instead of Blade**: overkill — there's no separate frontend client yet.
- **Single-action controllers per operation**: more files for no benefit at this size; a resource
  controller is the conventional, discoverable choice for standard CRUD.
- **Form Request for validation immediately**: reasonable, but the course wants you to feel the
  controller grow before introducing it, so the "why" of the Form Request lands as its own lesson.

## 11. When Not To Use It

A plain resource controller with inline validation stops being appropriate once: validation rules
get reused across multiple actions/controllers, the controller starts doing things beyond
"validate → persist → redirect" (e.g. it also decides business rules, sends notifications, talks
to other services), or you need the same logic invoked from somewhere other than an HTTP request
(a console command, a queued job, an API). None of that is true yet for `EventController`.

## 12. Practice

1. Implement everything in "What to build."
2. Add a route-model-bound `show`/`edit`/`update`/`destroy` (Laravel does this automatically via
   implicit route-model binding if your controller method type-hints `Event $event` — use it
   instead of manually querying by ID).
3. Stretch goal: add a `status` toggle (draft ↔ published) as a small custom route/action outside
   the standard seven — this will surface the "not everything fits the resourceful convention"
   question directly.

## 13. Review Questions

1. Why does `Route::resource()` generate exactly the method names it does (`index`, `create`,
   `store`, `show`, `edit`, `update`, `destroy`) — what's the convention it's built around?
2. What does implicit route-model binding save you from writing, and what would break it (e.g. a
   soft-deleted or globally-scoped model)?
3. Why is validating directly in the controller acceptable right now but likely to become a
   problem later? What specifically would have to change about the app for it to become a problem?
4. What's the difference between `$fillable` and `$guarded` on an Eloquent model, and which did
   you choose here, and why?
5. Why cast `starts_at`/`ends_at` to `datetime` instead of leaving them as raw strings?

## 14. Takeaways

- Laravel's conventions (resourceful routes, `$fillable`, redirect-with-errors) exist to remove
  boilerplate decisions for the common case — lean on them until you have a concrete reason not to.
- The simplest implementation that solves the actual current requirement is the *correct*
  architecture at this stage, not a shortcut you'll regret. Premature abstraction has a real cost.
- Every later lesson in this course will point back to a specific pain this controller starts to
  feel — pay attention to what starts to feel awkward as you keep building on top of it.

---

## Interview Preparation

### What Interviewers May Ask

- "Walk me through what happens when a request hits `POST /events`."
- "Why use `Route::resource()` instead of defining routes individually?"
- "What's the difference between `$fillable` and `$guarded`, and which do you prefer and why?"
- "Where should validation live in a small Laravel app, and when does that change?"
- "What is route-model binding, and how does Laravel resolve it?"

### What the Interviewer Is Testing

Baseline Laravel fluency and whether you understand *why* the framework's conventions exist,
not just that they exist — plus early signal on whether you reach for abstraction reflexively
or only when justified (a recurring theme this whole course is built around).

### How I Should Answer

Don't recite the request lifecycle from memory — explain it in terms of *this* feature: the
router matches the URL/verb to a resourceful route, dispatches to the matching controller method,
the controller validates and talks to Eloquent, Eloquent maps to/from the `events` table, and the
controller returns a redirect or a Blade view. For `$fillable` vs `$guarded`, explain the actual
security concern (mass-assignment vulnerabilities) rather than just naming the two properties.

### Real Interview Scenario

> "You're building a CRUD feature for a new resource. A junior dev on your team wants to
> immediately create a Repository, a Service, and a DTO for it before writing the controller.
> How do you respond?"

A strong candidate pushes back constructively: ask what problem the abstraction solves *right
now*. If the answer is "none yet, but we might need it," explain the cost of speculative
abstraction (more indirection, harder to read, harder to change later because now there's an
interface contract to honor) and propose starting simple, refactoring toward the abstraction when
a concrete pain point (reuse, testability, multiple data sources) actually appears.

### Interview Difficulty

**Junior–Mid.** Routing/controllers/Eloquent basics are asked at junior level; the "why not just
add abstraction everywhere" framing is where mid-level architectural judgment starts to get
evaluated.

---

## Laravel Interview Checklist

- Can you explain the full lifecycle of a request through routes → controller → model → view?
- Can you explain resourceful routing and implicit route-model binding?
- Can you explain mass assignment and why `$fillable`/`$guarded` exist?
- Can you articulate *why* this stage doesn't need a Service/Repository/Form Request yet, in terms
  a reviewer would find convincing (not just "it's simpler")?
- What would make you introduce a Form Request next? (Preview for Lesson 05.)
