# Laravel Progressive Architecture Training — Event Ticketing Platform

This course teaches Laravel architecture and design patterns by building a real application
incrementally: an **Event Ticketing Platform** where organizers create events with limited-inventory
ticket tiers, attendees purchase tickets, and the app grows real production concerns (concurrency,
async processing, notifications, authorization, caching, testing) as they become genuinely necessary.

See `../Laravel_Progressive_Architecture_Trainnning.md` for the full course philosophy and rules.

## Chosen Application

**Event Ticketing Platform**

- **Entities**: Organizer, Event, TicketType, Order, Ticket, Attendee, CheckIn, Refund
- **Core workflows**: event creation with ticket tiers → attendee purchase (inventory-safe) →
  order confirmation + PDF ticket → door check-in → refunds/cancellation → reminders → sales dashboard

## Roadmap

Lessons are written one at a time, in order, and only fully detailed once you're ready for them —
later lessons may shift based on what the app actually needs by that point.

| # | Lesson | Status |
|---|--------|--------|
| 01 | [Foundation](01-foundation.md) — routing, controllers, Blade, Event model/migration, basic CRUD | Ready |
| 02 | [Database & Relationships](02-database-relationships.md) — TicketType, Eloquent relationships, eager loading, N+1 | Implemented |
| 03 | [Authentication](03-authentication.md) — Organizer vs Attendee users | Ready |
| 04 | Authorization — Policies/Gates for event ownership | Planned |
| 05 | Validation — Form Requests | Planned |
| 06 | Purchasing Flow — inventory, transactions, row locking | Planned |
| 07 | Service Extraction — Service vs Action | Planned |
| 08 | Events & Listeners — `OrderPlaced` | Planned |
| 09 | Queues & Jobs — PDF generation, email, failed jobs | Planned |
| 10 | Notifications & Mail | Planned |
| 11 | Scheduling & Console Commands — reminders, exports | Planned |
| 12 | Refunds & Cancellation — Observer vs explicit logic | Planned |
| 13 | Caching — listings, dashboard aggregates | Planned |
| 14 | Repository/DTO Evaluation — is the abstraction justified? | Planned |
| 15 | Service Container & Contracts | Planned |
| 16 | Testing — feature/unit, fakes for queue/mail/notifications/events | Planned |
| 17 | Error Handling & Logging | Planned |
| 18 | Performance & Security | Planned |
| 19 | Production Readiness | Planned |
| — | `ARCHITECTURE.md` — final architecture review | Planned |
| — | `ASSESSMENT.md` — scenario-based skill assessment | Planned |

## How This Works

1. Each lesson gives you a business requirement and implementation **instructions** — not code.
2. You implement it yourself.
3. Share your implementation (or ask questions) and it gets reviewed against the architectural
   reasoning for that stage before the next lesson is written.
4. New concepts are introduced only when the current code genuinely justifies them — not preemptively.
