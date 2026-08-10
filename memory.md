# Memory — Laravel Progressive Architecture Training Course

Last updated: 2026-08-08

## What was built

- **Course docs**: `docs/Laravel_Progressive_Architecture_Training.md` (full course philosophy/rules), `docs/course/README.md` (roadmap), `docs/course/01-foundation.md`, `docs/course/02-database-relationships.md`.
- **CLAUDE.md**: added a "Project Context" section documenting the course rules (no code before instructions, justified-only pattern introduction, interview-prep sections per concept, lesson structure, roadmap tracking).
- **Lesson 01 (Foundation) — implemented and committed** (`6743a3c`):
  - Migration `database/migrations/2026_08_08_101419_create_events_table.php` for `events` (name, description nullable, venue, status default draft, start_time, end_time, timestamps).
  - `app/Models/Event.php` — `$fillable` (protected), casts `start_time`/`end_time` to `datetime`.
  - `app/Http/Controllers/EventController.php` — full resource controller, plus a `toggleStatus` custom route (`POST events/{event}/toggle-status`) as the Practice stretch goal.
  - Blade views under `resources/views/events/` (`index`, `show`, `create`, `edit`) using a shared `resources/views/components/layouts/app.blade.php` layout component (`<x-layouts.app>`), with `$errors`/`old()` support.
  - `database/factories/EventFactory.php` and `database/seeders/EventSeeder.php`.
  - 5 events seeded into the local DB via tinker for testing.
  - Ran `vendor/bin/pint --dirty` — fixed style in `Event.php`, the migration, `EventSeeder.php`.
- **Lesson 02 (Database & Relationships) — written, not yet implemented** (`e46a870`): instructions for adding `TicketType` (belongs to `Event`), `hasMany`/`belongsTo`, seeding multiple ticket types per event, deliberately observing an N+1 query on the events index, then fixing it with `with('ticketTypes')` (and comparing to `withCount()`). Marked "Ready" in `docs/course/README.md`.

## Decisions made

- Course is taught strictly via instructions, not handed-implementation code (per the course's Constraint 1) — user implements, then brings work back for review.
- Column names diverged from the lesson spec (`start_time`/`end_time` instead of `starts_at`/`ends_at`) — flagged in review but left as-is; not corrected.
- `status` enum settled on `draft`/`published` only (no `canceled`) for validation — `edit.blade.php` still has a leftover `canceled` `<option>` that was flagged but not yet fixed.
- `index()` currently orders by `start_time asc`, not `latest()`/`created_at desc` as the lesson specified — flagged, not fixed.
- `store`/`update` currently return the view directly instead of redirecting (breaks Post/Redirect/Get) — flagged as the most important open issue, not yet fixed.
- `$request->all()` used for mass assignment instead of `$request->validated()` — flagged, not fixed.

## Problems solved

- N/A — no debugging issues hit yet this session; open review findings below are still outstanding.

## Current state

- Lesson 01 code is implemented, reviewed, and committed. It works functionally (CRUD + toggle-status), but has known deviations from the lesson spec (see "Decisions made") that were pointed out in review and left for the user to fix themselves per the course's hands-on philosophy.
- Lesson 02 is fully written and ready, but **no implementation has started** — no `TicketType` migration/model/factory/relationships exist yet.
- Git: all work committed on `main`, 2 commits ahead of `origin/main` as of last check (not pushed).

## Next session starts with

The user should implement Lesson 02 (`docs/course/02-database-relationships.md`): create the `TicketType` migration/model/factory, define `Event::ticketTypes()` (hasMany) and `TicketType::event()` (belongsTo), seed multiple ticket types per event, deliberately observe the N+1 query problem on the events index, then fix it with eager loading. Bring the implementation back for review.

Optionally, before starting Lesson 02, the user may want to fix the Lesson 01 review findings (store/update not redirecting, index ordering, leftover `canceled` status option, `validated()` vs `all()`) — not yet done.

## Open questions

- Should the Lesson 01 review findings be fixed before or after Lesson 02? (Left to user's discretion.)
- Local commits are ahead of `origin/main` and unpushed — confirm with user before pushing.
