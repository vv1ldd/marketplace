# ADR 0013: Rebuildability Is A System Requirement

## Status

Accepted

## Context

Operational databases contain many tables that behave like projections: balances, order views, search indexes, catalog read models, analytics aggregates, continuity summaries, and operational dashboards.

If a table is called a projection but cannot be rebuilt from accepted transitions, authority decisions, and required proofs, it is not actually a projection. It is hidden authority state.

Hidden authority state creates recovery risk because losing or corrupting that table can destroy marketplace truth rather than merely degrading operational convenience.

## Principle

Every operational projection must have a documented and tested replay path.

## Invariant

If a projection cannot be rebuilt, it is not a projection.

## Decision

Every rebuildable operational projection must be registered with enough information to prove and test recoverability.

The Projection Rebuild Registry must declare:

- `projection_name`
- `source_transitions`
- `source_authority_decisions`
- `required_anchor_range`
- `rebuild_command`
- `verify_command`
- `last_verified_at`

## Architectural Test

For any table or read model, ask:

> Can this table be dropped and rebuilt?

If the answer is no, the table is not a projection and must be treated as authority-critical state.

## Consequences

Operational databases become reproducible representations of legitimate history.

Recovery becomes a tested capability rather than a backup assumption.

Projection freshness and verification status become observable system properties. A projection that has not been recently rebuilt and verified is degraded, even if the database itself is reachable.
