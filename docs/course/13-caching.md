# Lesson 13 — Caching: Listings and Dashboard Aggregates

## 1. Goal

Stop recomputing the same expensive read on every single request when the underlying data hasn't
changed, without ever serving a stale number to someone who just changed it. By the end of this
lesson you'll have cached a plain listing query and a computed aggregate, and — more importantly —
you'll have solved the harder half of caching: knowing exactly when a cached value has to be thrown
away, and proving your invalidation actually fires everywhere the underlying data can change.

## 2. Current State

`EventController::index()` runs `Event::with('ticketTypes', 'organizer')->orderBy('start_time', 'asc')->get()`
on every request to the public events page — no caching, no memoization, a fresh query (plus its
eager loads) every time, for every visitor, even though events are created and edited far less often
than the page is viewed. There is no organizer sales dashboard yet — no page anywhere aggregates "how
many tickets has this event sold" or "how much revenue has this event made" across `Order`/`Ticket`/
`Refund`. This app's configured cache driver is `database` (`config/cache.php`, confirmed via
`php artisan config:show cache.default`) — not Redis or Memcached.

## 3. New Requirement

> "The events listing page is getting slow under load. Also, organizers want a dashboard for each of
> their events showing total tickets sold and total revenue (net of refunds) — recalculating that on
> every page view is wasteful once an event has hundreds of orders."

Two different things to cache: a **listing query** (cheap per-row, expensive because it runs
constantly) and a **computed aggregate** (expensive per-computation — sums across potentially
thousands of rows — but requested less often, by fewer people).

## 4. Initial Implementation

Wrap the events listing query in `Cache::remember()` with a fixed key and a short TTL (say, 5
minutes). Build the organizer dashboard's "tickets sold" and "revenue" numbers as real-time
aggregate queries first — `Ticket` count joined through non-cancelled `Order`s, `SUM(total_amount)`
minus `SUM(refunds.amount)` — then wrap those in `Cache::remember()` too, keyed per event.

Now create a new event, or edit an existing one's name, and reload the public listing. Nothing
changes until the TTL expires — attendees see stale event data for up to 5 minutes after an organizer
publishes or edits it. Now cancel an order for an event with a dashboard cached from before the
cancellation: the dashboard's revenue number doesn't move until its own TTL expires either, even
though the underlying `Refund` row was created the instant you clicked cancel.

## 5. Problem Appears

**A time-based TTL is a guess, not a guarantee.** It trades staleness for simplicity — you're
choosing to be wrong for up to N minutes, on purpose, and calling that acceptable. Sometimes it is.
It is not here: an organizer editing their own event and immediately checking the public page to
confirm the edit landed is a real, common workflow, and "wait 5 minutes" is a broken experience for
it.

**Nothing tells the cache when the data it's holding has actually changed.** `Event::update()`,
`TicketOrderService::order()`, and `TicketOrderService::cancel()` all write to tables the cached
values were computed from, and none of them know or care that a cache entry now disagrees with the
database. The cache and the database have silently diverged, and nothing in the codebase would tell
you that happened short of noticing the UI is wrong.

**The dashboard aggregate has more invalidation triggers than the listing does.** The events listing
only needs to be invalidated when an `Event` itself changes. The revenue/tickets-sold numbers need to
be invalidated by `Event` changes *and* by every `Order` placed *and* every `Order` cancelled for that
event — three different write paths, in two different classes, all needing to agree on the same cache
key for the same event.

## 6. Concept Introduction

Laravel's `Cache` facade gives you `Cache::remember($key, $ttl, $callback)` — compute-and-store the
first time, return the stored value on every call after, until the TTL expires or something calls
`Cache::forget($key)` first. That "or something calls forget first" clause is the entire lesson:
TTL-based expiry and explicit invalidation aren't alternatives to each other — TTL is your safety net
for the invalidation you forgot to wire up, not a substitute for wiring it up at all.

**Cache tags** (`Cache::tags(['events'])->remember(...)`) let you invalidate a whole group of keys at
once — "flush everything tagged `events`" instead of tracking every individual key. They are not
available on every driver: the `database` and `file` drivers this app is currently configured with
don't support tags at all (attempting to use them throws), only `redis` and `memcached` do. That's a
concrete, checkable constraint on this app right now, not a hypothetical — which is exactly why
Section 9 below is about explicit per-key invalidation, not tags.

## 7. Why This Solution?

- **Cache the listing with a short TTL, invalidate it explicitly on write.** The TTL exists as a
  backstop (in case an invalidation call gets missed somewhere), not as the primary mechanism —
  `EventController::update()`, `store()`, and `destroy()` should each explicitly forget the listing's
  cache key the moment they write, so an organizer's edit is visible immediately, not "eventually."
- **Cache the dashboard aggregate per-event, invalidated by every write path that touches that
  event's numbers.** This is the one that actually tests whether you understand the concept: you have
  to go find every place `Order`/`Ticket`/`Refund` rows change for a given event and make each of them
  responsible for invalidating that event's cached aggregate — the same "every caller has to remember"
  discipline problem [[12-refunds-cancellation]] raised about explicit service methods versus
  Observers.
- **No tags, because this app's driver doesn't support them.** Explicit keys
  (`"event:{$event->id}:dashboard"`) are more verbose to invalidate one at a time, but they work on
  every driver, including the one actually configured — building on a feature that would silently
  break in this environment isn't a real option here.

## 8. Implementation

### Task

Cache the public events listing and each event's organizer dashboard aggregate, and make sure both
are invalidated at every point the underlying data can change — not just eventually correct via TTL.

### Instructions

- Add `Cache::remember()` around `EventController::index()`'s query, keyed by something stable (e.g.
  `'events.index'`), with a TTL you can justify (this is your backstop, not your invalidation
  strategy).
- Add explicit `Cache::forget('events.index')` calls to every `EventController` action that changes
  what the listing would show: `store()`, `update()`, `destroy()`, `toggleStatus()`. Decide whether
  this belongs directly in the controller or is better centralized — consider what happens once a
  second write path to `Event` exists somewhere else in the app.
- Design the dashboard aggregate query: tickets sold (count of `Ticket`s whose `Order` is not
  `Cancelled`) and net revenue (`SUM(orders.total_amount)` minus `SUM(refunds.amount)`, again only
  for non-cancelled orders) for a given event. Cache it keyed per event, e.g.
  `"event:{$event->id}:dashboard"`.
- Find every place that needs to invalidate that per-event key: `TicketOrderService::order()`,
  `TicketOrderService::cancel()`, and anywhere `Event` itself is updated (does an event edit change
  the dashboard numbers? decide, and justify either answer). Add the `Cache::forget()` call at each
  one.
- Build a minimal dashboard view for organizers (their own events only — reuse the authorization
  pattern from Lesson 04) showing the two cached numbers.
- Write a test that actually proves invalidation works: cache a dashboard value, cancel an order for
  that event, and assert the *next read* reflects the cancellation — not just that the cache key
  exists.

### How This Should Be Approached

Treat every `Cache::remember()` call as making a promise: "this value is safe to reuse until X."
Before writing the `remember()`, list every place X can become false. If you can't name all of them
confidently, that's a sign the value needs a shorter TTL, a simpler cache key (less to invalidate), or
doesn't need caching yet at all.

## 9. Refactoring

Once there are three or four `Cache::forget()` calls scattered across `TicketOrderService` and
`EventController` for the same dashboard key, notice the shape of the problem: it's the same "who is
responsible for a side effect that has to happen no matter which of several call sites triggered it"
question [[12-refunds-cancellation]] already worked through for cancellation — except this time the
side effect is "invalidate this cache key," not "release inventory." Consider whether an `Event`
observer watching for relevant changes (with the bulk-update caveat from Lesson 12 in mind), or a
small dedicated `EventDashboardCache` class that every write path calls into, reads better once you
can see all the call sites at once. Don't build that abstraction before you have all the call sites in
front of you, though — this is Lesson 07's rule again: let the actual duplication justify the
refactor.

## 10. Alternatives

- **Cache tags for one-call invalidation**: not usable here — `database`/`file` drivers don't support
  them. Worth knowing as the right answer *if* this app were on Redis, and worth being able to say
  why it isn't the answer today.
- **TTL-only, no explicit invalidation**: rejected as the primary mechanism — acceptable for data
  where staleness genuinely doesn't matter (a "last updated" footer, maybe), wrong for a page an
  organizer expects to reflect their own edit immediately.
- **Database-level caching (a `tickets_sold`/`revenue` column on `Event`, updated by triggers or
  application code on every write)**: moves the "who updates this on every write path" problem from
  the cache layer to the schema layer — it doesn't avoid the invalidation problem, it just relocates
  it, and adds a denormalized column you now have to keep in sync forever. Worth knowing as a genuine
  option once read volume is high enough that even a cache-miss recomputation is too slow — not
  justified yet here.

## 11. When Not To Use It

Don't cache a query just because it's a query — cache it because you've measured (or can reasonably
argue) that it's requested often relative to how often its underlying data changes, and that the cost
of a cache-miss recomputation is actually significant. A count query on a table with a few hundred
rows doesn't need caching; pretending it does just adds an invalidation surface for no real benefit.

## 12. Practice

1. Cache `EventController::index()`'s listing, with a short TTL and explicit `forget()` calls on
   every `Event`-mutating action.
2. Design and cache the per-event dashboard aggregate (tickets sold, net revenue).
3. Find and wire up every invalidation trigger for the dashboard key: order placed, order cancelled,
   event updated (if you decide it should invalidate).
4. Build the organizer-only dashboard view.
5. Test: place an order, confirm the dashboard reflects it; cancel that order, confirm the *next*
   dashboard read (not just eventually, immediately) reflects the cancellation.

## 13. Review Questions

1. What does a `Cache::remember()` call actually promise, and what determines whether that promise
   is safe to make?
2. Why is a TTL a backstop and not a substitute for explicit invalidation?
3. Why doesn't this app's dashboard cache key support cache tags, and what would change if it did?
4. Name every write path that has to invalidate the per-event dashboard cache. What happens if you
   miss one?
5. Why might an event edit need to invalidate the listing cache but not the dashboard cache (or vice
   versa)?

## 14. Takeaways

- Caching isn't "store the result" — it's "store the result *and* take on responsibility for knowing
  every place that result can go stale."
- A TTL is a safety net for invalidation you might have missed, not a plan for invalidation you
  haven't built.
- The set of places that must invalidate a cache key is exactly the set of write paths that touch the
  data it was computed from — go find them before you write the `remember()` call, not after a bug
  report.

---

## Interview Preparation

### What Interviewers May Ask

- "How do you decide what's worth caching?"
- "Walk me through how you'd invalidate a cache entry that depends on data written from three
  different places."
- "What's the difference between a TTL and cache tags, and when would you reach for each?"
- "Tell me about a time a cache made something worse, not better."

### What the Interviewer Is Testing

Whether you think about caching as "make it faster" (the naive framing) or as "trade correctness risk
for speed, and manage that risk deliberately" (the real skill). The tell is whether your answer
mentions invalidation before you're asked about it.

### How I Should Answer

Lead with the trade-off, not the mechanism: caching means choosing to serve a value that might be
wrong, for the sake of speed, and the entire job is bounding how wrong and for how long. Name TTL
versus explicit invalidation as complementary, not competing — TTL bounds the damage of a missed
invalidation, explicit invalidation is what makes the common case correct immediately. For the
three-write-paths question, describe finding every call site first, then deciding whether that
repetition justifies centralizing invalidation into one place (an Observer, a dedicated cache-owning
class) — the same "wait for the duplication to justify the abstraction" instinct as everywhere else in
this course.

### Real Interview Scenario

> "Your dashboard shows revenue, but a customer says they got refunded and the dashboard still shows
> the old total an hour later. How do you debug this, and how do you prevent it next time?"

A weaker answer jumps straight to "increase the TTL" or "just don't cache it." The actual debugging
path: find the cache key the dashboard reads, find every place a refund can be created, and check
whether that code path calls `Cache::forget()` on that key. The senior framing: this isn't a caching
bug, it's a missing-invalidation bug that caching made visible — the fix is finding the gap in your
list of write paths, not tuning the TTL.

### Interview Difficulty

**Mid–Senior.** Calling `Cache::remember()` is junior-accessible. Enumerating every write path that
has to invalidate a given cache key — and reasoning about what happens when one gets missed — is where
this becomes a senior conversation about correctness under change, not just performance.

---

## Laravel Interview Checklist

- Can you explain what a `Cache::remember()` call is actually promising the rest of the app?
- Can you name the concrete difference between TTL-based and explicit invalidation, and when each is
  the primary mechanism versus the backstop?
- Can you enumerate every write path that should invalidate a specific cache key, for a real feature
  in this app?
- Can you explain why cache tags aren't a universal solution — what driver constraint rules them out
  here?
- Can you justify, for a specific query, why it's (or isn't) worth caching at all?
