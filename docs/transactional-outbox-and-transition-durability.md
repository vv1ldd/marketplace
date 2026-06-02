# Transactional Outbox And Transition Durability

## Purpose

Class A writes must preserve accepted transitions even when external calls, queues, anchors, or projections fail.

The transactional outbox ensures that operational SQL writes, ledger entries, and follow-up processing do not diverge silently.

## Durability Target

- `RPO = 0` for accepted transitions.
- Accepted transitions must be replayable.
- Provider calls and Simple L1 anchoring must be retryable.
- Projection updates must be derived, not authoritative.

## Class A Write Path

```text
Command
  -> canonicalize payload
  -> acquire writer authority for scope
  -> encrypted SQL transaction
  -> operational row changes
  -> transition ledger entry
  -> authority decision if required
  -> outbox event
  -> commit
  -> async dispatch / anchor / projection
```

The outbox event must be committed in the same database transaction as the transition ledger entry.

## Acceptance Rule

A transition may be marked accepted only after:

- writer authority is valid for the transition scope;
- the transition ledger entry is persisted;
- idempotency key is recorded;
- outbox event is persisted;
- required authority decision is persisted or explicitly not required.

Simple L1 anchoring may be asynchronous, but missing anchors must affect Anchor Verification Readiness until confirmed.

## Outbox Event Shape

Initial fields:

- `id`
- `event_uuid`
- `scope`
- `aggregate_type`
- `aggregate_id`
- `transition_type`
- `transition_id`
- `transition_hash`
- `authority_decision_id`
- `authority_decision_hash`
- `idempotency_key`
- `payload_ciphertext`
- `payload_hash`
- `status`
- `attempts`
- `available_at`
- `processed_at`
- `last_error`
- `created_at`

Payloads should be encrypted or reduced to hashes and references. The outbox must not become a plaintext leak.

## Statuses

- `pending`
- `processing`
- `processed`
- `retry_wait`
- `failed`
- `dead_lettered`
- `superseded`

## Idempotency

Every Class A command must have an idempotency key scoped to the aggregate.

Examples:

- `order:{uuid}:accept`
- `wallet:{user_id}:{asset}:debit:{client_key}`
- `settlement:{id}:commit`
- `provider-order:{provider}:{external_id}:capture`

Retries must return the existing accepted transition when the same idempotency key has already succeeded.

## Failure Handling

If external provider fulfillment fails after a transition is accepted:

- do not delete the transition;
- record a compensating transition or authority decision;
- preserve the original ledger entry;
- update projections through replay.

If Simple L1 anchoring is delayed:

- keep the accepted transition;
- mark Anchor Verification Readiness degraded;
- retry anchoring from outbox or anchor queue.

If projection update fails:

- keep the accepted transition;
- mark Projection Rebuild Readiness degraded;
- rebuild projection later.

## Initial Migration Path

1. Add outbox table for Class A transitions.
2. Start with append-only records from existing order, wallet, and ledger flows.
3. Dispatch existing queue jobs from outbox instead of direct side effects.
4. Add idempotency checks to critical command handlers.
5. Add dead-letter and replay tooling.
6. Connect outbox status to Continuity Readiness.
