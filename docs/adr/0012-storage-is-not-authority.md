# ADR 0012: Storage Is Not Authority

## Status

Accepted

## Context

Marketplace state is increasingly distributed across encrypted operational storage, transition ledgers, authority decisions, and Simple L1 proofs. Treating the operational database as the source of truth would collapse storage, ordering, legitimacy, and recovery into one mechanism.

That model is fragile for orders, balances, settlements, provider fulfillment, and identity-linked proofs because a stored value can exist without proving that the transition was legitimate, authorized, ordered, or recoverable.

## Principle

A stored value is not authoritative merely because it exists in storage.

## Invariants

- Storage does not imply authority.
- Ledger does not imply legitimacy.
- Proof does not imply execution.

## Decision

Marketplace truth is derived from accepted transitions, legitimate authority decisions, and verifiable proofs.

Encrypted operational databases store current state. They do not determine whether a transition was legitimate, whether an action was authorized, whether conflicting histories were resolved correctly, or whether a state should be considered authoritative.

The system separates responsibility as follows:

- Encrypted Database stores operational state.
- Transition Ledger stores accepted transitions.
- Authority Ledger stores legitimacy decisions.
- Simple L1 stores cryptographic proofs and anchors.

## Consequences

Operational state may be replaced, rebuilt, or migrated between storage technologies.

Legitimate history must be preserved.

Database technology choices such as MySQL, Vitess, PostgreSQL, CockroachDB, or an event store are valid only if they preserve the continuity model:

- `RPO = 0` for accepted transitions.
- Writer authority remains explicit.
- Rebuildability remains possible.
- Verifiable continuity remains intact.
