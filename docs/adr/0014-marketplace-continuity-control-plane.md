# ADR 0014: Marketplace Continuity Control Plane

## Status

Accepted

## Context

Marketplace continuity cannot be reduced to database availability. A database can be reachable while writer authority is conflicted, ledger continuity is broken, anchors cannot be verified, or projections cannot be rebuilt.

For orders, balances, settlements, provider fulfillment, identity, and authority-sensitive workflows, the primary objective is not to preserve a specific database instance. The objective is to preserve legitimate history and the ability to reproduce operational state after failures, blocks, migrations, split-brain, or storage loss.

## Principle

Continuity preserves truth by preserving legitimate history.

## Continuity Model

Legitimate history is formed from:

- accepted transitions;
- authority decisions;
- verifiable proofs.

Operational state is derived from legitimate history through replay and rebuild.

```text
Accepted Transitions
+ Authority Decisions
+ Verifiable Proofs
= Legitimate History

Legitimate History
+ Replay/Rebuild
= Operational State
```

## Decision

The marketplace must maintain a Continuity Control Plane separate from ordinary infrastructure health checks.

Continuity layers:

- Transition Ledger preserves accepted transitions.
- Authority Ledger preserves legitimacy.
- Simple L1 preserves proofs and anchors.
- Writer Authority preserves ordering rights.
- Projection Registry preserves rebuildability.
- Operational Database preserves convenience.

## Writer Authority Readiness

Each authority scope must expose:

- `scope`
- `authority_holder`
- `authority_epoch`
- `fencing_status`
- `conflict_status`

Invariant:

> Exactly one authority holder per scope and epoch.

The state `no active writer` is safer than `two active writers` for the same authority scope.

## Architectural Test

For any writer, ask:

> Why is this writer allowed to emit accepted transitions?

The answer must be explainable through:

- authority scope;
- authority holder;
- authority epoch;
- fencing state.

## Recovery Objectives

- `RTO < 60 seconds` for operational recovery.
- `RPO = 0` for accepted transitions.
- Loss of operational projections is recoverable.
- Loss of legitimate history is not acceptable.

## Consequences

Database failover, database replacement, storage migration, and projection rebuilds become operational events rather than threats to marketplace truth.

Any new storage, ledger, settlement, provider, or Simple L1 backend must preserve:

- `RPO = 0` for accepted transitions;
- writer authority;
- rebuildability;
- verifiable continuity.
