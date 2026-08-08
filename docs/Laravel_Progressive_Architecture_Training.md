# Laravel Progressive Architecture Training Course

## Objective

I want to build a **hands-on mini course inside a real Laravel application** that teaches me Laravel features, architecture, design patterns, and engineering practices through incremental development.

The goal is not simply to finish an application.

The primary goal is:

> **By the end of this course, I should be comfortable reading an unfamiliar Laravel application, understanding why its architecture is structured the way it is, identifying code smells, and choosing the appropriate Laravel feature, abstraction, or design pattern for a given problem.**

I want to learn **the right tool at the right time**, rather than being introduced to architectural patterns prematurely.

---

# Core Learning Philosophy

The application must evolve incrementally.

Start with the simplest reasonable implementation.

When the application reaches a point where the existing implementation becomes difficult to maintain, extend, test, or reason about, **introduce the appropriate Laravel feature or design pattern at that exact point**.

For example:

### Do NOT do this

Start the application with:

```text
Controller
    ↓
Service
    ↓
Repository
    ↓
Interface
    ↓
Implementation
```

before there is a real reason for those abstractions.

### Instead

Start with a simple controller implementation.

As the controller becomes too large or business logic needs to be reused:

> "The controller is now handling too much business logic. This is the point where extracting a Service makes sense."

Then introduce the Service.

Later, if there is a genuine need for abstraction around data access:

> "We now have multiple data sources / complex persistence logic / a meaningful reason to abstract data access. This is where we should evaluate whether a Repository is appropriate."

The same principle should apply to:

* Services
* Action classes
* Repositories
* DTOs
* Events
* Listeners
* Jobs
* Queues
* Notifications
* Mail
* Policies
* Gates
* Middleware
* Form Requests
* API Resources (where applicable)
* Events
* Observers
* Model scopes
* Eloquent relationships
* Caching
* Transactions
* Laravel Containers
* Dependency Injection
* Service Providers
* Facades
* Contracts
* Interfaces
* Singletons
* Scheduling
* Console Commands
* Filesystem
* Storage
* Authentication
* Authorization
* Sessions
* Validation
* Rate limiting
* Broadcasting (if appropriate)
* Testing
* Logging
* Exception handling
* Database transactions
* Queues and failed jobs
* Notifications
* Events/listeners
* Laravel configuration
* Environment management
* Deployment considerations

Do not force every Laravel feature into the application simply to say it was covered.

If a feature does not naturally belong in the application, explain why and introduce it through a focused exercise instead.

---

# Application Requirements

The application must:

* Be built using **Laravel + Blade**.
* Use Laravel's standard conventions wherever appropriate.
* Have realistic business logic.
* Be more substantial than a basic CRUD/blog application.
* Have multiple related entities and workflows.
* Evolve from simple code into production-grade architecture.
* Eventually contain realistic complexity requiring architectural decisions.
* Be suitable for learning how real Laravel applications are structured.

Avoid trivial applications such as:

* Blog
* Basic Todo app
* Simple CRUD inventory
* Simple contact manager

The application should resemble something I might encounter in a real commercial Laravel project.

---

# Incremental Development Strategy

Build the application in stages.

Each stage should introduce only the concepts that are justified at that point.

Example progression:

```text
Stage 1
Simple Laravel application
↓
Routes
↓
Controllers
↓
Blade
↓
Models
↓
Migrations
↓
Eloquent relationships
↓
Validation
↓
Form Requests
↓
Authentication
↓
Authorization
↓
Policies
↓
Business logic grows
↓
Service extraction
↓
Reusable actions
↓
Events / Listeners
↓
Queues / Jobs
↓
Notifications
↓
Mail
↓
Caching
↓
Transactions
↓
File storage
↓
Scheduled tasks
↓
Console commands
↓
Dependency Injection
↓
Container
↓
Service Providers
↓
Contracts / Interfaces
↓
Repository evaluation
↓
DTOs where justified
↓
Testing
↓
Error handling
↓
Logging / Observability
↓
Performance optimization
↓
Security hardening
↓
Production readiness
```

This is only an example.

**Do not blindly follow this sequence.**

Choose the sequence based on the needs of the application.

---

# Teach Through Refactoring

One of the most important requirements is that I should experience **why a pattern is needed**.

When introducing a new architectural concept:

1. Show the current implementation.
2. Explain the problem with the current implementation.
3. Explain the symptoms/code smell.
4. Explain the possible solutions.
5. Explain why the chosen Laravel feature/pattern is appropriate.
6. Refactor the existing implementation.
7. Show the improved structure.
8. Explain the trade-offs.
9. Explain when NOT to use that pattern.

For example:

```text
Current problem:
Controller has become 250 lines.

Why this is a problem:
Business logic is difficult to test and reuse.

Possible solutions:
- Service
- Action class
- Domain object

Chosen solution:
Service

Why:
...

When NOT to use it:
...
```

The goal is to develop **architectural judgment**, not pattern memorization.

---

# Pattern Decision Training

Whenever a new architectural problem appears, explicitly make me think about the decision.

For important decisions, explain:

### Problem

What problem are we solving?

### Options

What possible approaches are available?

### Decision

Which approach are we choosing?

### Why

Why is it appropriate for this situation?

### Trade-offs

What are the disadvantages?

### Alternatives

What could we have used instead?

### When Not To Use It

When would this abstraction be unnecessary or harmful?

This is particularly important for:

* Service vs Action
* Service vs Domain class
* Repository vs Eloquent
* Event vs direct method call
* Job vs synchronous execution
* Queue vs normal request
* Notification vs Mail
* DTO vs array
* Interface vs concrete class
* Singleton vs normal dependency
* Observer vs explicit business logic
* Policy vs controller authorization
* Middleware vs authorization/business logic
* Event-driven vs direct workflow

---

# Avoid Cargo-Cult Architecture

Do NOT introduce patterns merely because they are considered "best practices."

For example:

Do not automatically create:

```text
Repositories/
Services/
DTOs/
Contracts/
Interfaces/
Actions/
Traits/
```

for every feature.

If a simple Laravel implementation is the best solution, keep it simple.

The course should teach me:

> **Good architecture is about appropriate trade-offs, not maximum abstraction.**

---

# Laravel Feature Coverage

Across the entire course, aim to expose me to the major Laravel features that a professional Laravel developer should understand.

Cover them naturally where possible.

## Foundation

* Routing
* Controllers
* Blade
* Layouts
* Components
* Blade directives
* Middleware
* Configuration
* Environment variables

## Database

* Migrations
* Seeders
* Factories
* Eloquent
* Relationships
* Query Builder
* Scopes
* Accessors / Mutators
* Casting
* Transactions
* Pagination
* Eager loading
* N+1 problems

## Forms & Validation

* Form Requests
* Validation
* Custom validation
* Error handling
* Old input
* CSRF

## Authentication & Authorization

* Authentication
* Gates
* Policies
* Roles/permissions where appropriate
* Middleware

## Application Architecture

* Dependency Injection
* Service Container
* Service Providers
* Contracts
* Interfaces
* Services
* Actions
* Repositories where justified
* DTOs where justified
* Events
* Listeners
* Observers

## Async Processing

* Jobs
* Queues
* Failed jobs
* Retry behavior
* Notifications
* Mail

## Infrastructure

* Cache
* Filesystem
* Storage
* Scheduling
* Console commands
* Task scheduling
* HTTP client

## Testing

* Feature tests
* Unit tests
* Factories
* Mocking
* Fakes
* Database testing
* Testing queues, mail, notifications, events

## Production Engineering

* Logging
* Exception handling
* Security
* Performance
* Caching
* Database optimization
* Queue monitoring
* Configuration caching
* Deployment considerations
* Environment management

Only include features that make sense for the application. If an important Laravel feature cannot reasonably fit, create a small isolated exercise for it rather than forcing it into the main application.

---

# Course Structure

Create the course as a series of Markdown files.

Use a structure similar to:

```text
laravel-course/
│
├── README.md
│
├── 01-foundation.md
├── 02-database.md
├── 03-authentication.md
├── 04-validation.md
├── 05-business-logic.md
├── 06-services.md
├── 07-events.md
├── 08-queues.md
├── 09-notifications.md
├── 10-architecture.md
├── 11-testing.md
├── 12-performance.md
├── 13-production.md
└── ...
```

Adjust the structure based on the actual learning progression.

---

# Structure of Each Lesson

Every Markdown lesson should contain:

## 1. Goal

What are we trying to accomplish?

## 2. Current State

What does the application currently look like?

## 3. New Requirement

Introduce the next realistic business requirement.

## 4. Initial Implementation

Implement the simplest reasonable solution.

## 5. Problem Appears

As complexity grows, identify the architectural problem.

## 6. Concept Introduction

Introduce the Laravel feature or design pattern that solves the problem.

## 7. Why This Solution?

Explain the reasoning.

## 8. Implementation

Provide the exact implementation steps.

## 9. Refactoring

Show how the previous implementation evolves.

## 10. Alternatives

Explain other approaches.

## 11. When Not To Use It

Explain when the pattern would be unnecessary.

## 12. Practice

Give me a small exercise to implement or refactor.

## 13. Review Questions

Ask questions that test whether I understand the concept.

## 14. Takeaways

Summarize the important architectural lessons.

---

# Hands-On Learning

Do not simply give me completed code.

Whenever possible:

1. Give me the requirement.
2. Let me attempt the implementation.
3. Ask me what approach I would take.
4. Review my implementation.
5. Explain what is good or problematic.
6. Introduce the next concept only when appropriate.

The objective is to make me **think like a Laravel developer**, not copy code.

---
# Learning-First & Interview Preparation Constraints

## Constraint 1 — Instructions Before Code

**Do not provide implementation code as part of the course instructions.**

The purpose of this course is for me to **write the code myself and develop architectural thinking**, not copy the implementation.

For each lesson or feature:

1. First give me the business requirement.
2. Explain what I need to build.
3. Give me clear implementation instructions.
4. Explain the Laravel concepts/features that are relevant.
5. Explain why this approach is appropriate.
6. Explain what design decisions I should consider.
7. Let me implement it myself.
8. After the instructions, provide a separate section explaining:
   - What a good implementation should look like conceptually.
   - Where the logic should live.
   - Which Laravel features should be used.
   - Why those features are appropriate.
   - What common mistakes I should avoid.
   - What alternative approaches exist.

### Example

Instead of:

> Create a Category model and use this code...

Do this:

### Task

> Create CRUD functionality for Categories.

### Instructions

- Create the Category model and migration.
- Define the required database fields.
- Create the appropriate controller.
- Add routes for the CRUD operations.
- Create Blade views for listing, creating, editing, and viewing categories.
- Add validation for category input.
- Follow Laravel conventions for naming and organization.

Then explain:

### How This Should Be Approached

Explain the expected architecture, Laravel conventions, where validation should live, how the controller should be structured, and why these decisions are appropriate.

**Do not give me the actual implementation code.**

I should be able to implement the feature from the instructions and then compare my implementation against the architectural guidance.

If I explicitly ask for code after attempting the task, provide it only then.

---

## Constraint 2 — Laravel Developer Interview Preparation

The course should simultaneously prepare me for **Laravel developer interviews**.

Whenever a Laravel feature, architectural decision, design pattern, or implementation approach is introduced, include an **Interview Preparation** section.

For each important concept, explain:

### What Interviewers May Ask

Provide realistic Laravel interview questions related to the feature.

Examples:

- Why would you use a Service class?
- When would you use a Service vs an Action class?
- When should you use a Repository pattern in Laravel?
- Why should heavy work be moved to a Queue?
- What is the difference between a Job and a Queue?
- When would you use Events and Listeners?
- What problem does Dependency Injection solve?
- How does Laravel's Service Container work?
- When should you use a Singleton?
- What is the difference between a Policy and Middleware?
- How would you prevent an N+1 query problem?

### What the Interviewer Is Testing

Explain what knowledge or engineering judgment the interviewer is trying to evaluate.

For example:

- Understanding of Laravel internals
- Architectural judgment
- SOLID principles
- Separation of concerns
- Performance awareness
- Scalability
- Testing knowledge
- Understanding of trade-offs

### How I Should Answer

Give me the key points that a strong candidate should mention.

Do not give me a memorized scripted answer. Teach me the reasoning so I can explain the concept naturally.

### Real Interview Scenario

Where appropriate, provide a realistic interview scenario or prompt.

For example:

> "This controller has become 300 lines long and contains business logic used by multiple controllers. How would you refactor it?"

Then explain what a strong candidate should identify and how they should reason about the solution.

### Interview Difficulty

Identify the likely level:

- Junior
- Mid-level
- Senior
- Senior/Staff

Also indicate whether the topic is commonly asked in Laravel interviews or is more likely to appear as an architecture/system-design discussion.

---

## Interview Connection

At the end of every major lesson, include:

### Laravel Interview Checklist

- What Laravel concepts should I now be able to explain?
- What questions could an interviewer ask?
- What practical problem does this feature solve?
- What alternatives should I know?
- What trade-offs should I understand?
- What common mistakes should I avoid?

The goal is that by completing the course, I can both **implement Laravel features** and **explain the architectural reasoning behind them during a technical interview**.

# Architecture Checkpoints

At major stages, stop and perform an architecture review.

Ask:

* Is this code still simple enough?
* Is any class becoming too large?
* Is business logic in the right place?
* Are we duplicating logic?
* Are dependencies becoming tightly coupled?
* Is this abstraction actually justified?
* What would happen if this feature doubled in complexity?
* What would happen if another workflow needed the same logic?

Then introduce the next architectural concept only if it solves a real problem.

---

# Final Application

By the end, the application should be:

* Fully functional
* Realistic
* Well structured
* Tested
* Secure
* Performant
* Maintainable
* Production-oriented

It should demonstrate appropriate use of Laravel features and patterns rather than artificially containing every possible pattern.

---

# Final Architecture Review

At the end of the course, perform a complete architecture review.

Explain:

* Why the project is structured this way.
* Why each major abstraction exists.
* Which patterns were used.
* Why they were chosen.
* Which patterns were deliberately NOT used.
* Where the architecture could still be improved.
* What trade-offs were made.

Create a final document:

```text
ARCHITECTURE.md
```

containing the application's architectural decisions and reasoning.

---

# Final Skill Assessment

Create a final assessment designed to determine whether I can independently analyze Laravel code.

Give me unfamiliar scenarios such as:

* A controller has become too large.
* The same business operation is used by HTTP and CLI.
* A process takes 30 seconds.
* Multiple systems need to react to the same action.
* Database access is becoming complex.
* A workflow must run asynchronously.
* An operation must be retried.
* A user needs authorization for a specific resource.
* Multiple implementations of a dependency are required.

For each scenario, ask me:

1. What is the problem?
2. What would you change?
3. Which Laravel feature or pattern would you use?
4. Why?
5. What alternatives did you consider?
6. When would your chosen solution be inappropriate?

The goal is to test **decision-making**, not whether I remember definitions.

---

# Important Rules for Claude Code

Before starting implementation:

1. Do not immediately start coding.
2. First propose **3 realistic application ideas**.
3. For each idea, explain:

   * Business problem
   * Target users
   * Core workflows
   * Why it is suitable for learning Laravel
   * Which Laravel concepts it could naturally introduce
   * Potential complexity progression
4. Let me choose one.
5. After I choose, create the course roadmap.
6. Get the roadmap approved before building the application.

Do not prematurely introduce advanced architecture.

Always ask:

> **"Does the current problem justify this abstraction?"**

If the answer is no, keep the implementation simple.

The ultimate objective is not to learn how to use every Laravel feature.

The objective is to develop the ability to look at a Laravel application and confidently answer:

> **"What problem is this code solving, why was it designed this way, and is this actually the right solution?"**
