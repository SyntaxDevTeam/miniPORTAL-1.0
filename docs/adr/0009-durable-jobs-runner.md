# ADR-0009: Durable jobs runner for production

Status: Accepted  
Date: 2026-09-22

## Context

Milestone 2 needs a transport-neutral Jobs contract before modules can schedule
background work. A request-local queue is useful for contract tests but loses
jobs on process exit. Q-012 asks which first production runner to use.

## Constraints

- Jobs belong to packages and need stable status and progress.
- Modules must not open their own database connections or execute arbitrary
  payload code through the queue.
- Worker crashes must not silently lose accepted production work.
- The existing Storage contract and package migration planning remain the
  database boundary.

## Considered options

1. Run jobs synchronously inside the request.
2. Store jobs only in PHP process memory.
3. Use a database-backed queue with a CLI worker.
4. Require an external broker at the first milestone.

## Decision

Define `JobQueue` as the public contract and provide `InMemoryJobQueue` for
development and contract testing. Production will use a database-backed queue
and CLI worker built on the Storage contract. The in-memory provider is not a
production default and gives no persistence or cross-process claim guarantee.
Modules receive only a package-scoped `JobScheduler`; claiming and status
mutations remain worker-facing operations.

The first database provider must specify lease recovery, atomic claiming,
bounded attempts, idempotency retention, and package-owned migration before it
can be activated. Its worker will dispatch only registered job handlers; the
declarative payload is data, never a class name or executable code supplied by
a package request.

## Consequences

The contract and tests can advance independently of the persistence engine.
Milestone 2 remains incomplete until the durable provider, worker, and failure
recovery tests are implemented.

## Revisit trigger

Reconsider the storage choice if real deployments demonstrate that database
claim contention or job throughput requires a broker.
