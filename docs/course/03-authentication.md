# Lesson 03 — Authentication: Organizer vs Attendee Users

## 1. Goal

Introduce real users into the app. By the end of this lesson, people can register and log in,
every `Event` belongs to the organizer who created it, organizers only manage their own events,
and you can explain how Laravel's session-based auth actually works under the hood — not just
that `Auth::user()` "works."

## 2. Current State

`Event` and `TicketType` exist with a clean `hasMany`/`belongsTo` relationship (Lesson 02), but
there is no concept of *who* owns an event. The default `users` table exists (from Laravel's
starter migrations) but nothing uses it yet — anyone hitting `/events/create` can create an event
on behalf of no one in particular, and any event can be edited or deleted by anyone.

## 3. New Requirement

> "Organizers need accounts so they can manage their own events. Attendees will eventually need
> accounts too (to buy tickets, see order history), but for now we only need to distinguish
> *organizer* users from everyone else, and make sure an event always has an owner."

This is the first lesson where "who is making this request" starts to matter — every future
authorization lesson builds on the identity established here.

## 4. Initial Implementation

**No package installed for this lesson.** Laravel ships session-based authentication scaffolding
(`Auth` facade, `Illuminate\Auth\Middleware\Authenticate`, hashed passwords, the `users` table)
without needing Breeze/Fortify/Jetstream — those packages just generate UI and routes around the
same underlying mechanism. Building it by hand once is the point: you'll understand what those
generators actually give you, which matters a lot in interviews ("what does Breeze actually do
for you?").

### What to build

**Users table — add a role**

The default `users` migration already has `name`, `email`, `password`. Add a `role` column
(string or a small enum-like set of allowed values — `organizer` / `attendee`) with a sensible
default. Think about whether this belongs as a new migration (yes — never edit a migration that
may have already run) or editing the existing one (only acceptable here because the app is brand
new and the table has never shipped).

Update the `User` model's `$fillable` to include `role`, and think about whether `password` needs
any casting in this Laravel version (check how the default `User` model already handles it).

**Link events to their owner**

Add an `organizer_id` (or `user_id` — pick a name and be consistent) foreign key on `events`,
referencing `users`, non-nullable, cascading or restricting on delete (think about which makes
sense: should deleting a user delete their events, or should that be blocked?).

Add the relationship pair:
- `Event::organizer()` → `belongsTo(User::class)`
- `User::events()` → `hasMany(Event::class)`

**Registration & login**

Build the manual auth flow:
- A `RegisterController` (or a single `AuthController` — your call) with `create`/`store` for
  registration. Validate `name`, `email` (unique), `password` (confirmed, Laravel's default
  password rules). Hash the password before saving — check what helper/facade Laravel provides
  for this and whether the `User` model already does it for you via casting.
- A login `create`/`store` action. Use the `Auth` facade's `attempt()` method against
  `email`/`password`. On success, regenerate the session. On failure, redirect back with an
  error using Laravel's validation error bag (`withErrors`).
- A `logout` action. Invalidate the session and regenerate the CSRF token — think about *why*
  both of those matter, not just that "logout" needs to happen.
- Blade views for register/login forms, reusing the existing layout component.

**Protect routes**

Wrap the event `create`, `store`, `edit`, `update`, `destroy`, and `toggle-status` routes in the
`auth` middleware. Leave `index`/`show` public (anyone can browse events, only organizers manage
them). When storing a new event, set `organizer_id` from the authenticated user — never trust a
client-submitted owner ID.

**Scope events to their organizer (partial authorization preview)**

For now, keep this simple: when an authenticated user visits `edit`/`update`/`destroy` for an
event they don't own, what should happen? You don't need a full Policy yet (that's Lesson 04) —
a simple `abort_unless($event->organizer_id === auth()->id(), 403)` check inside the controller
is fine here. Notice how repetitive this check would get across 3 controller methods — that
repetition is exactly the seed for the next lesson.

## 5. Problem Appears

Once you've wired up ownership checks in `edit`, `update`, and `destroy`, you'll have the same
`abort_unless(...)` (or equivalent) line duplicated three times. It's not a crisis yet — three
lines isn't a real problem — but it's the first visible sign of an authorization concern that
doesn't belong inline in the controller forever. Hold onto that observation; Lesson 04 is where
it gets a name (`Policy`) and a proper home.

## 6. Concept Introduction

**Authentication** answers "who is this?" — Laravel's default guard is session-based: on login,
a signed session cookie is issued, and on each subsequent request Laravel resolves `Auth::user()`
by looking up the user ID stored in that session. Passwords are never stored or compared in
plaintext — `Hash::make()` (bcrypt by default) produces a one-way hash, and `Auth::attempt()`
internally uses `Hash::check()` to verify without ever reversing the hash.

This is distinct from **authorization** ("what is this user allowed to do?"), which is Lesson 04
— don't conflate the two even though the manual ownership check above brushes against it.

## 7. Why This Solution?

Building auth manually (rather than installing Breeze) is deliberate here: the mechanism is a
handful of Eloquent queries, a hashing function, and session state — nothing about it requires a
scaffolding package, and seeing it built by hand demystifies what `Auth::user()`,
`Auth::attempt()`, and the `auth` middleware are actually doing. In a real production app you'd
likely reach for Breeze/Fortify/Jetstream/Sanctum depending on the surface (web, API, SPA) — but
knowing what's underneath them is what separates "I ran an installer" from "I understand Laravel
auth."

## 8. Implementation

See "What to build" above. Implement the migration, model changes, controllers, routes, views,
and route protection yourself, then bring it back for review.

## 9. Refactoring

`EventController::store()` changes from creating an event with no owner to setting
`organizer_id` from `auth()->id()`. `EventController::edit/update/destroy` gain an ownership
check. Routes gain `auth` middleware on the write actions. This is the first time the controller
needs to know *who* is making the request, not just *what* they're requesting.

## 10. Alternatives

- **Breeze/Fortify/Jetstream**: faster to set up, production-appropriate for real projects —
  intentionally skipped here so you build the mechanism once by hand. Worth installing later as
  an exercise once you understand what they automate.
- **API token auth (Sanctum)**: appropriate for a JSON API/SPA consumer instead of server-rendered
  Blade — not needed yet since this app is Blade-first; may become relevant much later if a
  mobile client or public API is introduced.
- **Role as a separate `roles` table with a pivot**: more flexible (many-to-many roles/permissions)
  but overkill for two fixed roles right now — a string column is the right amount of complexity
  today. Revisit if the app ever needs more than organizer/attendee or per-role permission sets.

## 11. When Not To Use It

Don't reach for a full roles-and-permissions package (e.g. Spatie's permission package) for two
static roles — that's solving a problem you don't have yet. A simple `role` column and explicit
checks are correct until you have a real need for dynamic, admin-configurable permissions.

## 12. Practice

1. Implement everything in "What to build."
2. After wiring up the three duplicated ownership checks, write down (a sentence or two is fine)
   what you'd want to happen if a fourth controller method needed the same check — this primes
   Lesson 04.
3. Stretch goal: add a `role` check so that only `organizer` role users can access
   `events.create`/`store` at all (an `attendee` account shouldn't even see the form) — think
   about whether this belongs in the controller, a middleware, or both.

## 13. Review Questions

1. What actually happens, step by step, when `Auth::attempt(['email' => ..., 'password' => ...])`
   is called — what gets queried, what gets compared, and what gets stored on success?
2. Why does session regeneration matter on login and logout? What attack does it mitigate?
3. Why should `organizer_id` be set from `auth()->id()` on the server rather than accepted as a
   form field, even though it would work either way in the happy path?
4. What's the difference between authentication and authorization, concretely, using this
   lesson's `abort_unless` check as an example of the boundary between the two?
5. Why is a plain `role` string appropriate here, but might not be appropriate for a system with
   many fine-grained permissions?

## 14. Takeaways

- Authentication establishes identity; it does not by itself establish permission — those are
  separate concerns even when the code briefly overlaps (as in this lesson's ownership check).
- Session-based auth is a handful of well-understood primitives (hashing, session storage, a
  middleware) — scaffolding packages assemble these for you, they don't invent new mechanisms.
- Repetition is a signal, not an emergency — three duplicated authorization checks are fine to
  notice and leave alone until the next lesson gives you the right abstraction for them.

---

## Interview Preparation

### What Interviewers May Ask

- "Walk me through what happens when a user logs into a Laravel app."
- "How does Laravel know a request is authenticated on every subsequent page load?"
- "Why do you hash passwords, and why can't you 'decrypt' a hash?"
- "What's the difference between authentication and authorization?"
- "Why regenerate the session ID on login?"
- "When would you reach for Sanctum vs session auth vs Passport?"

### What the Interviewer Is Testing

Whether you understand the underlying session/cookie mechanism rather than treating `Auth::user()`
as magic, whether you can articulate the authn/authz boundary precisely, and whether you're aware
of basic security reasoning (session fixation, password hashing) rather than just knowing the API
surface.

### How I Should Answer

Describe the request lifecycle concretely: login validates credentials via `Hash::check()`
against the stored hash, on success Laravel stores the user's ID in the session and issues a
signed session cookie, and every subsequent request the `auth` middleware resolves that ID back
into a `User` model via the configured guard/provider. Session regeneration on login prevents
session fixation — an attacker who fixed a session ID before login shouldn't be able to hijack
the now-authenticated session. Keep authn/authz separate in your answer: authentication is "who,"
authorization is "what are they allowed to do," and conflating them in an answer is a common
tell of surface-level understanding.

### Real Interview Scenario

> "A user reports that after logging in on a shared computer, logging out doesn't seem to fully
> log them out — a browser back button still shows their dashboard. What's going on and how do
> you fix it?"

A strong candidate identifies this as a caching/session-invalidation issue (the page is served
from browser cache, not an active session), points out that `logout()` should invalidate the
session and regenerate the CSRF token, and may suggest cache-control headers on sensitive pages
as a defense-in-depth measure — while being clear that the "session" itself is correctly dead on
the server side once `Auth::logout()` and `$request->session()->invalidate()` have run.

### Interview Difficulty

**Junior-to-Mid.** Basic auth flow questions are asked at nearly every level as a warm-up; the
session-fixation/security reasoning pushes this toward mid-level, and "when would you choose
Sanctum vs session auth" is where it starts probing senior-level judgment about the right tool
for API vs web contexts.

---

## Laravel Interview Checklist

- Can you explain what happens end-to-end during login and logout?
- Can you explain why passwords are hashed, not encrypted?
- Can you clearly separate authentication from authorization?
- Do you know when to reach for Breeze/Fortify/Jetstream/Sanctum vs rolling auth by hand?
- Can you explain why `organizer_id` must come from the server, not client input?
- What would make you reach for a Policy instead of an inline ownership check? (Preview for
  Lesson 04.)
