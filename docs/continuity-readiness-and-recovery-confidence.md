# Continuity Readiness And Recovery Confidence

## Purpose

Continuity Readiness measures whether the marketplace can preserve legitimate history and reproduce operational state.

It is separate from infrastructure readiness.

Infrastructure asks:

> Can the platform execute?

Continuity asks:

> Can the platform preserve truth?

## Readiness Domains

```text
ContinuityReadiness
├── WriterAuthorityReadiness
├── ProjectionRebuildReadiness
├── LedgerContinuityReadiness
├── AuthorityLedgerReadiness
├── AnchorVerificationReadiness
└── OperationalProjectionReadiness
```

## Domain Definitions

### WriterAuthorityReadiness

Proves exactly one active writer per authority scope and epoch.

Failure examples:

- duplicate writer;
- stale authority heartbeat;
- unfenced old writer;
- ambiguous emergency override.

### ProjectionRebuildReadiness

Proves operational projections can be rebuilt and verified.

Evidence:

- `last_rebuilt_at`
- `last_verified_at`
- `verification_result`
- `source_revision`
- `anchor_range`

### LedgerContinuityReadiness

Proves accepted transition streams are complete and hash-linked.

Evidence:

- no ledger gaps;
- previous hash chain verifies;
- idempotency keys are unique;
- source partitions have monotonic accepted transition ranges.

### AuthorityLedgerReadiness

Proves legitimacy decisions are present, append-only, and linked to authority scopes.

Evidence:

- decision hash verifies;
- supersedes graph is valid;
- authority basis is present;
- decision scope is explicit.

### AnchorVerificationReadiness

Proves required transition and decision ranges are anchored to Simple L1.

Evidence:

- anchor range exists;
- anchor hashes match local transition ranges;
- actor signatures verify;
- anchor freshness target is met.

### OperationalProjectionReadiness

Proves current operational tables are consistent with their source transitions and authority decisions.

Evidence:

- read model verification succeeded;
- projection source revision matches registry;
- no stale projection beyond allowed freshness window.

## Recovery Confidence

Recovery Confidence is an evidence-derived KPI. It must not be manually declared.

Inputs:

- Writer Authority Readiness
- Projection Rebuild Readiness
- Ledger Continuity Readiness
- Authority Ledger Readiness
- Anchor Verification Readiness
- recent replay success
- recent verification success

Example interpretation:

- `95-100`: continuity healthy
- `80-94`: continuity degraded
- `50-79`: continuity at risk
- `<50`: continuity unhealthy

The exact scoring weights should be implemented after the first readiness command exists.

## Continuity States

```text
Execution Healthy + Continuity Healthy
= normal operation

Execution Unhealthy + Continuity Healthy
= operational incident, truth preserved

Execution Healthy + Continuity Degraded
= system works, recovery confidence reduced

Execution Healthy + Continuity Unhealthy
= dangerous state, service responds but truth preservation is compromised

Execution Unhealthy + Continuity Unhealthy
= full continuity incident
```

## Initial Command Target

Future command:

```bash
php artisan marketplace:db-continuity-readiness
```

Expected output domains:

- execution status;
- continuity status;
- recovery confidence;
- writer authority status;
- projection rebuild status;
- ledger continuity status;
- authority ledger status;
- anchor verification status;
- operational projection status;
- blocking reasons.
