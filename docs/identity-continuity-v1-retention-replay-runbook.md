# Identity Continuity v1 — Retention & Replay Runbook

Operational policy only. This runbook is **not** a new layer of truth — it describes how
`identity_governance_stream_events` is kept, accelerated, and verified over time.

Companion: [`identity-continuity-v1-soak-gate.md`](identity-continuity-v1-soak-gate.md) (§7 stream
restore drill, §9 sign-off).

**Fill environment parameters before staging soak** — see [§0 Environment parameters](#0-environment-parameters-fill-before-staging-soak).

## Current status (not production sign-off)

```text
Identity Continuity v1
Local precursor:        PASSED
Production readiness:   PENDING
Certificate:            NOT ISSUED
```

This runbook describes **how** to prove continuity over time in staging/production — not that
continuity v1 is already proven in production.

---

## Layer roles (hold strictly)

Retention must **not** become a second source of truth. These roles do not overlap:

| Layer | Role | Truth? |
|-------|------|--------|
| **Stream** | history | **Yes** |
| **Snapshot** | acceleration | No — must fold same as full history |
| **Projection** | view | No — replay output |
| **Cache** | optimization | No — disposable |

```text
delete projections  = acceptable
delete stream       = identity loss event
archive             = durable copy of stream rows, not a new model
```

The operational question is no longer “where is the user stored?” but:

> **Which event history allows this user to be restored?**

---

## 0. Environment parameters (fill before staging soak)

Copy this table into your staging/prod runbook footer. **Do not start 24h soak until filled.**

| Parameter | Value | Notes |
|-----------|-------|-------|
| Hot window | _e.g. 90 days_ | Events kept in online DB |
| Snapshot every | _e.g. 10 000 events or 24 h_ | Phase C; write `N/A` until shipped |
| Archive after | _e.g. hot window exceeded_ | Cold copy; archive ≠ truth |
| **RPO** | **0 for committed events** | See below |
| **RTO — projection restore** | _e.g. < 15 min_ | Replay + cache warm |
| **RTO — authorize availability** | _e.g. < 30 min_ | Includes replay if cold start |
| Max replay ms | _from `IDENTITY_GOVERNANCE_MAX_FULL_REPLAY_MS`_ | Invariant 13 |
| Max drift | **0** | Convergence violations = stop |

Config defaults: `config/identity_governance.php` (`REPLAY_MS_PER_1K`, `MAX_FULL_REPLAY_MS`, …).

### RPO — recovery point objective

**How much history can be lost in a catastrophe?**

For identity, the usual answer:

```text
RPO = 0 for committed events
```

If append was **confirmed to the user** (successful append response, `event_id` assigned,
`version` committed), that event must survive storage failure. Unconfirmed / in-flight appends
may be retried via idempotent `event_id` — not counted as accepted history.

Operational implication: stream backup/replication lag must be measured; lag on **committed**
tail is an incident, not a warning.

### RTO — recovery time objective

**How long can identity restoration take?**

| Phase | Target (set per env) |
|-------|----------------------|
| Projection restore | replay all affected streams → convergence clean |
| Authorize availability | sample `options` + `verify` succeed |

Record actuals during §6 destructive drill and 24h soak; adjust targets if needed.

### Soak execution record (fill before 24h clock starts)

Operational launch contract — not architecture. Copy into staging/prod runbook; link from
[`identity-continuity-v1-soak-gate.md`](identity-continuity-v1-soak-gate.md).

```text
Scheduled start:
Owner:
Environment:
Commit SHA:
Database migration version:

Flags:
  IDENTITY_GOVERNANCE_STREAM_ENABLED=
  IDENTITY_GOVERNANCE_STREAM_AUTHORIZE_ENABLED=

Traffic source:
  - vault creation
  - passkey registration
  - authorize flows

Baseline (T+0):
  stream_count:
  max_stream_version:
  replay_p95:
  replay_p99:
  convergence_status:
```

**Stop soak if** (halt clock; do not “soak through”):

| Condition | Why |
|-----------|-----|
| `convergence != clean` | projection drift |
| Replay budget exceeded (critical / p95) | Invariant 13 |
| Stream version gap detected | stream integrity |
| Duplicate event anomaly | idempotency broken (`event_count` +2 on retry) |
| Authorize failure **after** projection rebuild | recovery contract broken |
| **Unexpected append during authorize** | **Invariant 11 — `authorize ≠ append`** |

The soak must catch violations of **`authorize ≠ append`**: login must change telemetry and
session only — never `identity_governance_stream_events` head or row count.

**Completion record (fill at sign-off):**

```text
Scheduled end:
Actual duration:
Stop reason (if aborted):
Final stream_count:
Final max_stream_version:
Final replay_p95 / p99:
Final convergence_status:
Restore drill pass (Y/N):
Durability check pass (Y/N):
Tag applied (Y/N):
```

---

## 1. Truth boundary

**SOURCE OF TRUTH**

```text
identity_governance_stream_events
```

**NOT SOURCE OF TRUTH**

- projection cache (`identity_governance_projection_cache`)
- replay-derived registry / governance / credential state
- snapshots (Phase C — when deployed)
- telemetry (`sign_count` counter cache, audit logs)

| Action | Severity |
|--------|----------|
| Delete projections | **Acceptable** — rebuild via replay |
| Delete stream | **Identity loss event** — not recoverable from projections |

---

## 2. Hot window

**Goal:** fast reads + fast replay for active operations.

| Parameter | Example (staging/prod) |
|-----------|-------------------------|
| Hot retention | 0–90 days of events in online DB |
| Contains | full events, indexes, stream metadata |
| Writers | append only to hot (tail always hot) |

**Verification (run on schedule, not only on incident):**

```bash
php artisan identity-governance:check-convergence
php artisan identity-governance:replay-budget
```

**Fail:** any convergence violation or critical replay budget breach → stop writes, investigate
before resuming.

---

## 3. Snapshot policy

Snapshot = **acceleration**, not replacement for history.

| Parameter | Example |
|-----------|---------|
| Trigger | every **10 000 events** **or** every **24 h** (whichever first) |
| `snapshot_version` | equals `stream_head_version` at capture time |
| `snapshot_hash` | hash of folded governance/registry state at that version |

**Invariant (must hold on every snapshot):**

```text
snapshot + tail events  ==  full replay(all events)
```

Implementation (when Phase C ships): persisted governance snapshots + tail replay — same check as
Invariant 10 in `IdentityGovernanceProjectionConvergenceChecker`.

Until Phase C is deployed: full replay from hot stream is the only path; document “snapshots N/A”
in environment runbook footer.

---

## 4. Cold archive

**When:** hot window exceeded (age or policy threshold).

```text
events (older than hot window)
        ↓
cold archive (object storage / RO replica / separate tablespace)
```

**Rule:**

```text
archive ≠ new truth
```

Archive is a **durable copy of stream rows**, not a second identity model. Append path and
authorize material must still be derivable from **archive slice + hot tail**.

**Archive acceptance test (required before archive is considered valid):**

```text
restore archive slice (+ hot tail if split)
    ↓
replay
    ↓
projection rebuild
    ↓
authorize test (sample identity)
    ↓
check-convergence
```

**Fail:** do not delete hot rows until archive test passes.

---

## 5. Replay cadence

Do not wait for an incident.

### Weekly (sample)

| Step | Action |
|------|--------|
| 1 | Pick N active streams (or stratified sample) |
| 2 | Restore from backup / archive+tail if testing cold path |
| 3 | Full replay + `identity-governance:check-convergence` |
| 4 | `identity-governance:replay-budget` — record p95 |

### Monthly (full destructive drill)

Same procedure as **§6 Destructive restore drill** on staging (or isolated prod shard).

Log: stream ids, head versions, replay ms, authorize result, operator, date.

---

## 6. Destructive restore drill

Proves: **projection can be lost; identity remains** (soak gate §7).

| Step | Action |
|------|--------|
| 1 | **Freeze writers** (maintenance flag / queue drain) |
| 2 | **Export stream** — `pg_dump -t identity_governance_stream_events` or equivalent |
| 3 | **Destroy projections** — truncate cache + any non-stream identity tables under test |
| 4 | **Restore stream only** — import dump; no projection rows |
| 5 | **Replay** — implicit on read or explicit rebuild job |
| 6 | **Check convergence** — `php artisan identity-governance:check-convergence` |
| 7 | **Authorize test** — options + verify for known credential |
| 8 | **Resume writers** |

**Pass criteria**

| Check | Expected |
|-------|----------|
| Identity state after replay | matches pre-drill replay |
| Authorize | works |
| Stream head after authorize | **unchanged** |
| New events from login | **none** |

**Fail:** authorize depends on legacy `passkeys`, external runtime DB, or other non-stream state.

---

## 7. Stream durability check

Projection rebuild proves **replay works**. This check proves **the sequence itself survives
storage** — the primary object is no longer a user row but an ordered event log.

| Step | Action |
|------|--------|
| 1 | Record last **committed** event: `stream_id`, `version`, `event_id`, timestamp |
| 2 | Perform controlled **storage restart** (DB restart, failover, or volume remount — per env) |
| 3 | Re-read stream: same row exists, head `version` unchanged, no gap |
| 4 | Optional: `check-convergence` + sample authorize |

**Pass:** last committed event identical after restart.  
**Fail:** head regressed, row missing, or gap appeared → **RPO violation**; stop soak.

Run once before 24h soak starts and optionally after monthly drill. Complements §6 (restore from
backup), not a substitute.

---

## 8. Alerts

Minimum set for production monitoring. Wire to your ops stack; thresholds from
`config/identity_governance.php` and soak baselines.

### Critical (page immediately)

| Alert | Condition |
|-------|-----------|
| Projection drift | `identity-governance:check-convergence` violations > 0 |
| Stream gap | hole query on `version` sequence returns rows |
| Replay budget | `full_replay_ms` > max **or** p95 `ms_per_1k` > critical |
| Authorize failure spike | stream authorize error rate >> baseline |

### Warning (ticket / next business day)

| Alert | Condition |
|-------|-----------|
| Event count growth | stream events/hour >> 7-day baseline |
| Replay latency trend | p95 `ms_per_1k` > baseline × 2 (sustained) |
| Snapshot lag | Phase C: snapshot_version << stream_head − threshold |

---

## After sign-off

When soak gate + this runbook are live for an environment, issue the internal milestone record:

**[`identity-continuity-v1-certificate.template.md`](identity-continuity-v1-certificate.template.md)**

```text
Code          → guarantees (tests, invariants)
Ops execution → proves production behavior (soak record + certificate)
```

Certificate summary tag:

```text
Identity Continuity v1 — Operationally Proven
Environment: <staging|production>
Date: <YYYY-MM-DD>

Guarantees:
  ✓ state reconstructible
  ✓ authorization recoverable
  ✓ projections disposable
  ✓ schema evolution supported
  ✓ replay measurable
```

First real proof (staging or prod drill):

> We deleted state and restored identity **only from history**.

That moment is when the model starts to live — not when the reducer tests pass.

Core identity is **closed** for architecture work. Domain extensions use the same mechanism:

| Extension | Shape |
|-----------|--------|
| Recovery | evidence → policy → event |
| Guardian | attestation → event |
| Root | authority change → event |

Allowed: producer changes, policy changes, operational changes.  
Not allowed: new core identity ADRs, parallel identity stores, hidden roots of trust.

---

## Related

- [`governance-reducer-invariants.md`](governance-reducer-invariants.md) — Invariants 10–13
- [`identity-claims-model.md`](identity-claims-model.md) — model freeze + v1 status
- [`identity-continuity-v1-certificate.template.md`](identity-continuity-v1-certificate.template.md) — fill at sign-off (ops record)
