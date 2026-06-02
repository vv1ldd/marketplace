# ADR 0015: Continuity Readiness Is Observable

## Status

Accepted

## Context

Traditional infrastructure monitoring answers whether the platform can execute:

- database reachable;
- API responding;
- queue processing;
- disk available;
- network reachable.

For marketplace continuity, that is not enough. A platform can be infrastructure-healthy while continuity is degraded or broken. Examples include writer authority conflicts, ledger gaps, stale projection rebuild verification, anchor verification failure, or unverified replay paths.

The platform needs an observable continuity model that answers whether legitimate history can continue and operational state can be reproduced.

## Principle

Continuity health must be derived from verifiable continuity artifacts rather than infrastructure availability.

## Invariant

A platform is not continuity-healthy merely because its infrastructure is healthy.

## Decision

The marketplace must expose continuity readiness separately from execution readiness.

Execution Plane asks:

> Can the system operate?

Continuity Plane asks:

> Can the system preserve legitimate history?

Continuity monitoring must answer:

- Can legitimate history continue?
- Can legitimate history be proven?
- Can operational state be reproduced?
- Can authority continue safely?

## Continuity Readiness Domains

- `WriterAuthorityReadiness`
- `ProjectionRebuildReadiness`
- `LedgerContinuityReadiness`
- `AuthorityLedgerReadiness`
- `AnchorVerificationReadiness`
- `OperationalProjectionReadiness`

## Recovery Confidence

Recovery Confidence is an operational KPI derived from observable continuity evidence.

Example inputs:

- Writer Authority Readiness
- Projection Rebuild Readiness
- Ledger Continuity Readiness
- Authority Ledger Readiness
- Anchor Verification Readiness
- Recent replay success
- Recent verification success

Recovery Confidence is evidence-derived, not manually declared.

## Projection Verification Evidence

Each rebuildable projection should expose:

- `projection_name`
- `last_rebuilt_at`
- `last_verified_at`
- `verification_result`
- `source_revision`
- `anchor_range`

## Failure Classification

Infrastructure healthy, continuity unhealthy:

```text
DB reachable
Queue healthy
Storage healthy
WriterAuthorityReadiness = conflict
```

Infrastructure healthy, continuity degraded:

```text
DB healthy
ProjectionRebuildReadiness = stale
```

Infrastructure recovering, continuity healthy:

```text
DB unavailable
Standby promoted
Replay verified
Authority intact
```

## Consequences

The system must not treat green infrastructure checks as proof of continuity health.

Continuity incidents become first-class incidents even when infrastructure is green:

- writer authority conflict;
- ledger gap;
- anchor verification failure;
- stale projection rebuild;
- unverified replay path.

Recovery readiness can be measured over time instead of assumed from backups.
