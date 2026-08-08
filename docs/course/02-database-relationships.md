# Lesson 02 — Database & Relationships: TicketType, Eloquent Relationships, Eager Loading, N+1

## 1. Goal

Introduce **TicketType** (a ticket tier belonging to an Event — e.g. "General Admission",
"VIP") and its Eloquent relationship to `Event`. By the end of this lesson, an event can have
multiple ticket types, the events list shows ticket-type counts/pricing without triggering N+1
queries, and you can explain *why* eager loading matters, not just how to write it.

## 2. Current State

`Event` is a single flat model (`app/Models/Event.php`) with a resource controller and CRUD
views (Lesson 01). There is no way to express "this event has these ticket options at these
prices" — pricing and inventory live nowhere yet.

## 3. New Requirement

> "Organizers need to define ticket tiers for an event before anyone can buy anything. A
> concert might have 'General Admission' at $50 and 'VIP' at $150, each with a limited quantity.
> The events list should show organizers, at a glance, how many ticket types each event has and
> the starting price."

This is the first relationship in the app, and the first place a naive implementation will
visibly cause a performance problem (N+1) the moment the list page renders real data.

## 4. Initial Implementation

One new model, one new migration, a `hasMany`/`belongsTo` relationship pair, and a
deliberately naive change to `EventController::index()` that will expose the N+1 problem before
you fix it. No Service, no Repository — the problem this lesson teaches is a *query* problem,
not an architecture problem, and Eloquent gives you the tools to solve it directly.

### What to build

**Migration**

Create a migration for a `ticket_types` table:

- `event_id` (foreign key to `events`, cascade on delete — if an event is deleted, its ticket
  types should go with it)
- `name` (string — e.g. "General Admission")
- `price` (decimal, appropriate precision for currency — think about why `float` is the wrong
  choice for money)
- `quantity` (unsigned integer — total tickets available in this tier)
- standard `id` and `timestamps`

Think about whether `event_id` should be nullable (it shouldn't — a ticket type without an event
makes no sense) and what `onDelete()` behavior is correct here.

**Model**

Create a `TicketType` Eloquent model. Add `event_id`, `name`, `price`, and `quantity` to
`$fillable`. Cast `price` appropriately (decimal cast, e.g. `'price' => 'decimal:2'`) so you
don't deal with float rounding issues when displaying or summing money.

**Relationships**

- On `Event`: add a `ticketTypes()` method returning `hasMany(TicketType::class)`.
- On `TicketType`: add an `event()` method returning `belongsTo(Event::class)`.

Think about *why* Eloquent needs both sides defined even though the foreign key only lives on
one table — what does each direction actually let you do that the other doesn't?

**Factory**

Create a `TicketTypeFactory`. Give it sensible fake data (a handful of realistic tier names, a
price, a quantity). You'll use this to seed multiple ticket types per event for testing eager
loading — a single event with only one related row will never show you an N+1 problem.

**Controller — introduce the problem first**

In `EventController::index()`, change the view to display, per event, the number of ticket
types and the lowest price among them (e.g. `$event->ticketTypes->count()` and
`$event->ticketTypes->min('price')` in the Blade view). Do **not** eager load yet — write it the
naive way first.

Seed at least 5 events with 2–4 ticket types each and load the events index page. Then look at
the query log.

**Observe the problem**

Use Laravel Boost's `database-query` tool or `DB::listen()`/the debug toolbar to count the
queries the index page fires. Confirm you see 1 query for the events plus 1 additional query
*per event* for its ticket types — this is the N+1 problem.

**Fix it**

Change the query in `index()` to eager load: `Event::with('ticketTypes')->...`. Reload the page
and confirm it now runs a constant, small number of queries regardless of how many events exist.

**Show page**

On `events/show.blade.php`, list the event's ticket types (name, price, quantity remaining —
quantity remaining is just `quantity` for now, since there's no purchasing yet). Eager load here
too if you're fetching the event via route-model binding and then accessing `$event->ticketTypes`
— or decide it's unnecessary here and explain why the show page's access pattern is different
from the index page's.

## 5. Problem Appears

The naive `$event->ticketTypes` access inside a loop over multiple events is exactly the N+1
problem: one query to fetch the events, then one additional query *for every event* to lazily
fetch its ticket types the first time it's accessed. At 5 events that's 6 queries; at 500 events
in production, that's 501 queries on one page load. This is one of the most common real-world
Laravel performance bugs, and it's invisible until you actually look at the query count — the
page still "works," it's just slow, and gets slower linearly with data growth.

## 6. Concept Introduction

**Eloquent relationships** (`hasMany`/`belongsTo`) let you express and traverse the association
without writing raw joins. **Eager loading** (`with()`) tells Eloquent to fetch the related rows
for the *entire collection* in one additional query (using a `WHERE IN`), instead of one query
per model instance. This is the fix for N+1 — not by avoiding the relationship, but by loading it
at the right time and in the right shape.

## 7. Why This Solution?

`hasMany`/`belongsTo` are the correct, idiomatic Eloquent representation of a one-to-many
association — no custom join logic needed, and Laravel handles foreign key inference from
convention (with an override available when convention doesn't match). Eager loading solves the
N+1 problem without changing the shape of your code (`$event->ticketTypes` still works exactly
the same in the view) — you only change *how* the data gets fetched upstream, which is precisely
the kind of query-layer concern that belongs in the controller, not the view.

## 8. Implementation

See "What to build" above. Implement the migration, model, relationships, and the deliberate
before/after (naive query → observe N+1 → add eager loading) yourself, then come back with your
implementation or questions.

## 9. Refactoring

`EventController::index()` changes from a query with no relationship awareness to one using
`with('ticketTypes')`. This is a one-line change with an outsized performance implication —
notice how small the *code* diff is compared to how large the *query count* diff is. That gap is
exactly why N+1 bugs are easy to introduce and easy to miss in code review: the code looks fine.

## 10. Alternatives

- **Lazy eager loading (`$events->load('ticketTypes')`)**: useful when you already have a
  collection and decide *after the fact* that you need the relationship — not needed here since
  you control the query from the start.
- **`loadCount()` / `withCount()`**: if you only need the *count* of ticket types (not the full
  records), `Event::withCount('ticketTypes')` is more efficient than eager loading full rows just
  to call `->count()` on the collection. Worth using instead of `with()` if the index page truly
  never needs anything but the count — but you also need `min(price)`, so a plain `with()` (or a
  more targeted aggregate) is more appropriate here. This is a good question to sit with: *what
  is the minimum data this view actually needs?*
- **Raw SQL join**: possible, but throws away Eloquent's model hydration and relationship
  ergonomics for no benefit at this scale.

## 11. When Not To Use It

Eager loading everything by default (e.g. via a global scope) is itself a smell — it can load
data you never use on views that don't need it, wasting memory and query time in the other
direction. Eager load the specific relationships a specific query path actually needs, not
reflexively on every fetch.

## 12. Practice

1. Implement everything in "What to build," including deliberately observing the N+1 problem
   before fixing it — don't skip straight to the fix.
2. Use `withCount('ticketTypes')` instead of `with('ticketTypes')` on the index page and compare
   the query plan/count to the `with()` version. When would you prefer one over the other?
3. Stretch goal: add a `lowestPrice()` accessor or scope on `Event` that encapsulates
   `->ticketTypes->min('price')` — think about whether that belongs on the model, and what
   would change if "lowest price" needed its own query instead of relying on already-loaded data.

## 13. Review Questions

1. Why does accessing `$event->ticketTypes` inside a `foreach` over events cause N+1, but
   accessing it once on a single `$event` (like on the `show` page) doesn't?
2. What does `Event::with('ticketTypes')->get()` actually do differently at the SQL level
   compared to `Event::all()` followed by accessing `->ticketTypes` on each result?
3. Why is `decimal:2` a better cast than leaving `price` as a plain float/string for money?
4. What's the difference between `with('ticketTypes')` and `withCount('ticketTypes')`, and when
   would each one be the right (and wrong) choice?
5. Why does the foreign key convention (`event_id` on `ticket_types`) matter for how Eloquent
   infers the relationship — what would you have to add if the column were named something else?

## 14. Takeaways

- Relationships are cheap to define; the cost shows up in *how* you fetch them. Defining
  `hasMany`/`belongsTo` correctly is necessary but not sufficient — you also have to think about
  the access pattern at each call site.
- N+1 is invisible in the code and only visible in the query log — get in the habit of checking
  query counts on any page that loops over a collection and touches a relationship.
- Eager loading is not "always add `with()` everywhere" — it's "load exactly what this specific
  view needs, in one pass."

---

## Interview Preparation

### What Interviewers May Ask

- "What is the N+1 query problem, and how would you detect it in a Laravel app?"
- "What's the difference between `with()`, `load()`, and `withCount()`?"
- "How does Eloquent know how to join `ticket_types` to `events` without you writing a join?"
- "Why would you use a decimal cast for a money column instead of a float?"
- "Walk me through what SQL `Event::with('ticketTypes')->get()` actually generates."

### What the Interviewer Is Testing

Whether you understand what Eloquent is doing *underneath* the convenient API — not just that
`with()` "fixes performance," but that you can explain the query-count mechanics, recognize the
smell without tooling, and reason about the right level of eager loading rather than reflexively
eager-loading everything.

### How I Should Answer

Describe the mechanism concretely: without eager loading, each `$model->relationship` access
that hasn't been loaded yet triggers a fresh query at access time — inside a loop, that's one
query per iteration. `with()` intercepts this by issuing a second query up front, scoped with
`WHERE event_id IN (...)` across all the parent IDs already fetched, and hydrates the
relationship on every model before your code ever touches it. Ground the money-column question
in the real failure mode: floats can't represent every decimal fraction exactly, which silently
corrupts totals over many operations — decimals avoid that class of bug entirely.

### Real Interview Scenario

> "A dashboard page that lists 200 orders, each showing the customer's name, is taking 4 seconds
> to load. The query for the orders themselves is fast in isolation. What do you check first,
> and how would you fix it?"

A strong candidate immediately suspects N+1 on the `customer` relationship, checks the query log
or count, confirms ~201 queries instead of 2, and fixes it with eager loading
(`Order::with('customer')`) — then explains *why* that's the fix rather than just naming it, and
mentions `withCount`/`select()` scoping as a follow-up optimization if only specific columns are
needed.

### Interview Difficulty

**Mid-level.** N+1 is one of the most commonly asked Laravel/ORM performance questions and is
considered a baseline competency for anyone claiming "production Laravel experience" — expect it
in almost every mid-level-and-above Laravel interview, often as a live-debugging exercise rather
than a definition question.

---

## Laravel Interview Checklist

- Can you define a `hasMany`/`belongsTo` pair and explain what each side buys you?
- Can you explain N+1 in terms of actual query counts, not just "it's slow"?
- Can you explain what `with()` does differently from lazy access, at the SQL level?
- Do you know when `withCount()` is more appropriate than `with()`?
- Can you justify the `decimal` cast choice for money columns?
- What would make you reach for a Service or Repository around ticket types next? (Preview for
  later lessons — not yet, based on what exists so far.)
