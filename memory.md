# Memory — Laravel Progressive Architecture Training Course

Last updated: 2026-08-12

## What was built

- **Tailwind CSS wired up and applied** — `resources/views/components/layouts/app.blade.php` was missing `@vite('resources/css/app.css')` entirely, so Tailwind (already a devDependency) was never actually loading; added it plus a styled nav/flash/error block. Styled `events/index`, `events/show`, `events/create`, `events/edit`, `auth/login`, `auth/register` with a consistent card/table/form look (gray-900 buttons, gray-200 borders, status badges). `node_modules`/`package-lock.json` were missing — ran `npm install` then `npm run build`. Committed as `900225c` (events pages) and `bf4c8ba` (auth pages).
- **Lesson 06 — Purchasing Flow (naive version) — implemented and committed** (`b06f543`, **not yet pushed**):
  - `docs/course/06-purchasing-flow.md` already existed (untracked, not yet committed) with the full lesson writeup.
  - `Order`/`Ticket` models, `OrderStatusEnum`, `OrderPolicy` (only `Attendee` role can `create`), `orders`/`tickets` migrations (tickets cascade-delete on order/ticket-type; UUID `code` column), `OrderFactory`/`TicketFactory` filled in.
  - `StoreOrderRequest` fixed — was validating `event_id`/`status` (wrong fields, and `status` should never come from the client); now validates `ticket_type_id`/`quantity`.
  - `events/show.blade.php` purchase form fixed — quantity input had a broken array-keyed name (`quantity[{{ $ticketType->id }}]`) alongside a separate hidden `ticket_type_id`; simplified to a plain `quantity` field.
  - `OrderController::store()` — naive flow per lesson §4: load `TicketType`, `Ticket::where(...)->count()` for sold, reject via `ValidationException` if requested qty exceeds availability, else create `Order` (server-computed `total_amount`) + `Ticket` rows in a loop. **Deliberately no `DB::transaction()`/`lockForUpdate()`** — that's the next lesson step.
  - `tests/Feature/OrderPurchaseTest.php` — 3 tests: happy-path purchase, over-quantity rejection, and a race-condition test that **intentionally fails**, proving the naive flow oversells a `TicketType` with `quantity = 1` (`Failed asserting that 2 is identical to 1`).
- **Bug fix bundled into `b06f543`**: `events/show.blade.php`'s "Quantity Remaining" column was showing `TicketType.quantity` (total capacity) instead of remaining stock. Added `TicketType::tickets()` relation (was missing) and a `remainingQuantity` `Attribute` accessor (exposed as `$ticketType->remaining_quantity`); `EventController::show()` now eager-loads `ticketTypes` with `withCount('tickets')`.

## Decisions made

- User explicitly overrode the course's "no implementation code up front" rule for the naive purchase flow ("Write the naive solution. I am explicitly telling you to do so.") — code was written directly rather than guiding the user to implement it.
- Race-condition test: a real dual-PDO-connection approach against a shared SQLite file was tried first (per the lesson's literal suggestion) but hit SQLite's own shared-cache/whole-database locking (`SQLSTATE[HY000]: database is locked`) even with WAL mode + busy_timeout — a SQLite testing artifact, not the actual bug. Replaced with manually interleaving the same read-then-write steps the controller performs, on the single default `:memory:` test connection — deterministic, no infrastructure fighting, same proof.
- `OrderController::store()` still hand-computes `quantity - ticketsSold` instead of using `TicketType::remaining_quantity` — flagged as duplicated logic in conversation but **not yet refactored**, pending user decision.

## Problems solved

- `@vite` directive missing from the layout — Tailwind was installed but never loaded; nothing styled anywhere until this was added.
- `node_modules` absent — `npm install` had never been run in this environment.
- SQLite `SQLSTATE[HY000]: database is locked` when trying genuine two-PDO-connection interleaving for the race test — see decision above.
- "Quantity Remaining" showing total capacity, not remaining stock — see bug fix above.

## Current state

- Lessons 01–06 implemented. **Local `main` is 5 commits ahead of `origin/main`** (still at `c991c28`): `cc47c01` (Lesson 04), `8abce4f` (Lesson 05), `900225c` (style events), `bf4c8ba` (style auth), `b06f543` (Lesson 06). None pushed.
- `php artisan test --compact`: 4 pass, 1 **intentionally** fails (the race-condition proof) — expected red, awaiting the `DB::transaction()`/`lockForUpdate()` fix (lesson §8) to go green.
- Working tree not clean: `docs/course/README.md` (modified, roadmap likely needs Lesson 06 status update) and `memory.md` are unstaged; `docs/course/06-purchasing-flow.md` is untracked. None committed yet — user was told this explicitly after the `b06f543` commit.
- Carried over from before, still unresolved/unverified: whether to push commits to `origin/main` (asked multiple times now, never confirmed); `EventPolicy::delete()` duplicates `update()`'s expression rather than deferring to it; older Lesson 01 findings (index ordering, leftover `canceled` status option).

## Next session starts with

Two independent threads, either can go first:
1. **Lesson 06 continuation**: implement `DB::transaction()` + `TicketType::where(...)->lockForUpdate()->first()` in `OrderController::store()` per lesson §8, then re-run `OrderPurchaseTest` — the race test should flip to passing. Consider also refactoring to use `$ticketType->remaining_quantity` at the same time (same underlying read, just deduplicated) — but note the accessor's fallback query must happen *inside* the locked read, not before it.
2. **Housekeeping**: decide whether to commit `docs/course/README.md` + `docs/course/06-purchasing-flow.md` (roadmap/lesson doc), and whether to finally push the 5 unpushed commits.

## Open questions

- Push of the 5 unpushed commits (`cc47c01` through `b06f543`) still unconfirmed — don't assume `origin/main` is current without checking.
- Should `EventPolicy::delete()`'s duplication of `update()`'s logic be cleaned up before continuing, or left as a deliberate teaching artifact?
- Should `OrderController::store()` be refactored to use `TicketType::remaining_quantity` before or alongside the `lockForUpdate()` fix?
