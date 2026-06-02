# Replay And Failover Design

## Purpose

Marketplace failover must restore operational service quickly while preserving accepted transitions.

Target:

- `RTO < 60 seconds` for operational recovery.
- `RPO = 0` for accepted transitions.

## Failure Model

The system assumes primary operational storage may become:

- unavailable;
- blocked;
- stale;
- corrupted;
- partitioned;
- writable by an unsafe old writer.

The system must distinguish operational outage from continuity loss.

## Failover Flow

```text
Detect failure
  -> pause Class A writes for affected scopes
  -> evaluate Writer Authority Readiness
  -> fence old writer
  -> promote standby encrypted DB
  -> replay missing accepted transitions
  -> verify ledger continuity
  -> verify authority decisions
  -> verify Simple L1 anchor ranges
  -> rebuild or refresh projections
  -> resume writes
  -> rebuild Class B projections in background
```

## Writer Fencing

Before standby writes begin, the system must prove that the old writer cannot emit accepted transitions for the same scope and epoch.

Accepted fencing evidence:

- expired authority lease;
- explicit authority decision;
- infrastructure-level fencing;
- manual emergency override with authority basis;
- verified old-writer shutdown.

If fencing cannot be proven, Class A writes should remain paused.

## Replay Sources

Replay uses:

- Transition Ledger;
- Authority Ledger;
- transactional outbox;
- Simple L1 anchor ranges;
- idempotency records.

Replay must be deterministic for each projection.

## Replay Phases

### Phase 1: Transition Range Discovery

Find the last verified transition for each affected scope.

Determine missing transition range from:

- ledger sequence;
- transition hash chain;
- outbox records;
- anchor range.

### Phase 2: Integrity Verification

Verify:

- hash chain continuity;
- idempotency uniqueness;
- authority decision availability;
- anchor range match;
- no duplicate active writer in the same scope and epoch.

### Phase 3: Projection Rebuild

Rebuild or refresh operational tables from verified transitions.

Projection rebuild output must include:

- source revision;
- anchor range;
- rebuilt row count;
- verification result;
- verification errors if any.

### Phase 4: Resume Writes

Writes resume only when:

- writer authority is singular;
- replay has completed for Class A scopes;
- ledger continuity is verified;
- required authority decisions are available;
- projections required for command validation are consistent.

## Degraded Modes

### Read-Only Mode

Use when execution can continue safely for reads but Class A write authority is uncertain.

### Limited Write Mode

Use when only some authority scopes are healthy.

Example: catalog writes may continue while settlement writes are paused.

### Continuity Healthy, Execution Recovering

Use when legitimate history is intact and replay verified, but infrastructure is still promoting or warming projections.

## Readiness Command Target

Future command:

```bash
php artisan marketplace:db-continuity-readiness
```

Required checks:

- writer authority status by scope;
- ledger continuity status;
- authority ledger status;
- outbox pending/dead-letter status;
- anchor verification status;
- projection rebuild freshness;
- replay verification status;
- recovery confidence.

## Test Scenarios

- primary DB unavailable;
- standby promoted;
- stale replica missing accepted transitions;
- duplicate writer detected;
- old writer not fenced;
- ledger gap detected;
- missing authority decision;
- missing or stale Simple L1 anchor;
- projection rebuild stale;
- projection verification failed;
- outbox event pending after accepted transition;
- provider call retried idempotently after failover.
