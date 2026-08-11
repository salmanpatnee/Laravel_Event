# Lesson 04 — Authorization: Policies for Event Ownership

## 1. Goal

Replace the inline `abort_unless(...)` ownership/role checks scattered across `EventController`
with a single `EventPolicy`. By the end of this lesson, "can this user manage this event?" lives
in exactly one place, is enforced consistently across every write action, and you can explain
*why* Policies exist rather than just knowing the `$this->authorize()` syntax.

## 2. Current State

`EventController` (Lesson 03) already checks ownership and role by hand, but inconsistently:

- `create()` — checks role only: `abort_unless(auth()->user()->role === RoleEnum::Organizer, 403)`
- `store()` — same role check, duplicated
- `edit()` — checks **both** ownership and role: `abort_unless($event->user_id === auth()->id() && auth()->user()->role === RoleEnum::Organizer, 403)`
- `update()` — checks ownership only: `abort_unless($event->user_id === auth()->id(), 403)`
- `destroy()` — same ownership-only check, duplicated
- `toggleStatus()` — same ownership-only check, duplicated again

Five near-identical lines, no two of them checking quite the same thing, spread across five
methods. Every route is already correctly authenticated (`auth` middleware from Lesson 03) —
this lesson is entirely about *authorization* ("what can this specific user do to this specific
event"), not authentication.

## 3. New Requirement

> "Only the organizer who created an event should be able to edit, delete, or toggle the status
> of that event. Only users with the `organizer` role should be able to create events at all.
> These rules need to be enforced the same way everywhere they apply, and it should be obvious
> from reading the controller that authorization is happening — not buried in an `abort_unless`
> that looks like validation."

## 4. Initial Implementation

Nothing new to build from scratch here — the checks already exist and already work. This lesson
is a **refactor**, not a new feature. Resist the urge to add anything beyond moving the existing
logic into its proper home.

## 5. Problem Appears

Look again at the five checks in Section 2. Ask yourself:

- If a sixth write action is added next lesson (e.g. duplicating an event), do you remember to
  copy the right `abort_unless` line, with the right condition, into it too?
- `edit()` checks role *and* ownership; `update()`/`destroy()`/`toggleStatus()` check ownership
  only. Was that intentional, or did it drift? (It happened to be harmless here, because only
  organizers can ever own an event — but the inconsistency itself is the smell, not any specific
  bug it caused.)
- If the ownership rule changes tomorrow (e.g. "co-organizers can also manage an event"), how
  many places does that change have to be made, and how confident are you you'd find all of
  them?

This is authorization logic leaking into every controller method that touches an `Event`,
instead of living in one place that describes the *rule*, not just enforcing it ad hoc.

## 6. Concept Introduction

A **Policy** is a class dedicated to answering authorization questions about a specific model.
Laravel auto-discovers a policy for a model by naming convention (`Event` → `EventPolicy`) and
lets you ask `$user->can('update', $event)` or, in a controller, `$this->authorize('update',
$event)` — which throws a `403` automatically if the check fails, the same way your
`abort_unless` calls do today, but from one canonical definition instead of five copies.

A **Gate** is the more general-purpose sibling — a closure-based check not tied to a specific
Eloquent model (e.g. "can this user access the admin panel?"). Policies are the right tool here
because every check in `EventController` is fundamentally "can this user do X to this
`Event`?" — exactly the shape a Policy is built for.

## 7. Why This Solution?

- **Single source of truth**: the rule "organizer role + ownership" is defined once, not
  reconstructed slightly differently in five methods.
- **Discoverability**: `php artisan make:policy` and Laravel's auto-discovery mean any developer
  who knows Laravel conventions can find the authorization rule without reading every controller
  method first.
- **Testability**: a Policy method is a plain function you can unit test directly, without
  spinning up HTTP requests through five different controller actions.
- **Framework-native `authorize()` / `@can` integration**: once the Policy exists, you get Blade
  directives (`@can('update', $event)`) and form-request-level authorization for free, which
  matters the moment you want to conditionally show an "Edit" button in a view.

## 8. Implementation

Don't copy the exact checks verbatim — think about what each one is actually expressing, then
implement it as policy methods.

**Create the policy**

Use `php artisan make:policy EventPolicy --model=Event`. Laravel will scaffold the standard
ability methods (`viewAny`, `view`, `create`, `update`, `delete`, plus `restore`/`forceDelete`
you can ignore for now).

**Decide what each ability means for this app**

- `create(User $user)` — should return whether the user is allowed to create events at all. What
  does the current `create()`/`store()` check actually test? Express that as a single boolean
  expression using `RoleEnum`.
- `update(User $user, Event $event)` — should return whether this specific user can edit/update
  this specific event. Base it on ownership (`$event->user_id === $user->id`) — think about
  whether role needs to be checked here too, or whether ownership alone is sufficient given how
  `user_id` gets set in the first place (Lesson 03: only ever from `auth()->id()` at creation
  time, and only organizers reach that code path).
- `delete(User $user, Event $event)` — is this the same rule as `update`, or different? Decide,
  and if it's identical, consider whether `delete` should just defer to `update` internally
  rather than duplicating the expression.
- Toggling status is a custom action, not one of the default resource abilities — you'll need a
  custom ability name (e.g. `toggleStatus`) registered the same way, or you can decide it's
  authorization-equivalent to `update` and reuse that ability directly in the route/controller.

**Wire it into the controller**

Replace every `abort_unless(...)` with `$this->authorize('<ability>', $event)` (for
model-specific checks) or `$this->authorize('create', Event::class)` (for the class-level
`create`/`store` check, where there's no `$event` instance yet). Note the different second
argument shape — a model instance vs. a model class string — and think about why Laravel needs
that distinction.

**Confirm auto-discovery**

Check whether your Laravel version needs the policy registered explicitly (older versions used
an `AuthServiceProvider::$policies` array) or whether naming convention (`EventPolicy` next to
`Event`) is enough on its own — verify by testing the behavior rather than assuming.

## 9. Refactoring

`EventController` shrinks: every `abort_unless` line becomes a single `$this->authorize(...)`
call, and the actual *rule* text moves out of the controller entirely. The controller's job
becomes "handle the HTTP concern (validate, persist, redirect)" — not "decide who's allowed to
do this," which is exactly the separation of concerns a Policy exists to enforce.

## 10. Alternatives

- **Gates**: viable for the class-level `create` check (no model instance involved), but since
  every other check here is model-specific, splitting the logic between a Gate and a Policy
  would scatter the same rule across two mechanisms instead of one. Not worth it for this app.
- **Form Request `authorize()` method**: Laravel's `FormRequest::authorize()` hook can also
  perform authorization before validation even runs. Reasonable for simple cases, but doesn't
  cover `toggleStatus` (no form/validation involved) or `destroy` (no request body) — a Policy
  covers all five actions with one consistent API, so it's the better fit here.
- **Keep the inline checks**: appropriate only for a single one-off check that will never be
  reused. Once the same rule appears in more than one place — which happened here by Lesson 03 —
  that argument stops holding.

## 11. When Not To Use It

Don't reach for a Policy for authorization rules that are truly one-off and never reused (e.g. a
single admin-only debug route). Also don't create a Policy per model reflexively "because that's
the pattern" — if a model has no authorization rules more interesting than "must be logged in"
(already handled by middleware), it doesn't need one.

## 12. Practice

1. Implement `EventPolicy` per the instructions above and wire it into `EventController`.
2. Write down (a sentence or two) what happens if `authorize()` fails — where does the `403`
   actually come from, and how is that different from your old `abort_unless` calls in terms of
   what gets logged/returned?
3. Stretch goal: add an `@can('update', $event)` check around the "Edit"/"Delete" links in
   `events/index.blade.php` and `events/show.blade.php` so attendees never even see controls they
   can't use — not a security boundary (the controller still enforces it), but good UX hygiene.

## 13. Review Questions

1. What's the actual difference between a Gate and a Policy, and why did this app's rules fit
   a Policy better?
2. Why does `authorize('create', Event::class)` take a class string, while
   `authorize('update', $event)` takes a model instance — what is Laravel doing differently in
   each case?
3. If `edit()` used to check role *and* ownership but `update()` only checked ownership, and you
   now unify both onto a single `update()` policy method — which check "wins," and why is that
   the correct behavior for this app?
4. Where should this Policy be unit tested, and what would a good test actually assert?
5. What would make you reach for a Gate instead of a Policy on a future feature in this app?

## 14. Takeaways

- Repetition across controller methods — even correct, working repetition — is a signal to
  extract, not just tolerate. Lesson 03 deliberately left this in place so you could see the
  smell before fixing it.
- A Policy doesn't add new authorization *rules* — it relocates existing ones to a place where
  they're defined once and reused everywhere they apply.
- Authorization and validation are different concerns even when they're both `abort`-shaped:
  validation asks "is this input well-formed," authorization asks "is this user allowed to do
  this at all." Keeping them in separate mechanisms (Form Requests vs. Policies) keeps that
  distinction visible in the code.

---

## Interview Preparation

### What Interviewers May Ask

- "What's the difference between a Gate and a Policy in Laravel?"
- "How does Laravel decide which Policy applies to which model?"
- "Walk me through what happens when `$this->authorize()` fails."
- "When would you put an authorization check in a Policy vs. a Form Request vs. middleware?"
- "How would you test a Policy?"

### What the Interviewer Is Testing

Whether you understand Laravel's authorization layer as a distinct concept from authentication
and validation, whether you know the practical API surface (`authorize`, `can`, `@can`,
auto-discovery vs. explicit registration), and whether you can justify *when* a Policy is the
right level of abstraction versus an inline check or a Gate.

### How I Should Answer

Explain that a Policy is a class scoped to a specific Eloquent model that answers "can this user
do this to this model instance," while a Gate is a closure-based check not tied to any model —
useful for coarser checks like feature flags or admin access. `$this->authorize()` (or the
`Authorizable` trait's `can()`) resolves the correct Policy method by convention, evaluates it,
and throws an `AuthorizationException` (rendered as a 403) on failure — no different in outcome
from a manual `abort_unless`, but centralized and discoverable. Emphasize the "why," not just the
API: the value is one definition of a rule instead of N copies that can drift.

### Real Interview Scenario

> "A junior developer added an `abort_unless($event->user_id === auth()->id(), 403)` check to a
> new controller method, but forgot to add the role check that a sibling method has. How would
> you prevent this class of bug going forward?"

A strong candidate identifies this as an authorization-logic-duplication problem, proposes
consolidating the rule into a Policy method so there's exactly one definition to get right, and
notes that a unit test on the Policy method (rather than five separate feature tests hitting five
controller methods) is the cheapest way to guarantee the rule is applied consistently.

### Interview Difficulty

**Mid-level.** Knowing that Policies exist is junior-level; explaining *why* to extract one at
this specific moment (recognizing the duplication smell, and articulating the Gate-vs-Policy
trade-off) is where it becomes a mid-level architectural-judgment question.

---

## Laravel Interview Checklist

- Can you explain the difference between a Gate and a Policy, with a concrete example of when
  you'd choose each?
- Can you explain what triggers Laravel's Policy auto-discovery?
- Can you explain the difference between `authorize()` on a class string vs. a model instance?
- Can you identify duplicated authorization logic as a code smell before it causes a bug?
- Do you know where a Policy fits relative to Form Request validation and route middleware?
