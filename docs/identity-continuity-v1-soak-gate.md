# Identity Continuity v1 — 24h production soak gate

**Purpose:** production readiness, not architecture validation. Goal is to **try to break** the
system under real append load, restarts, and retries — not to prove the design is elegant.

## Current status (not production sign-off)

```text
Identity Continuity v1
Local precursor:        PASSED
Production readiness:   PENDING
Certificate:            NOT ISSUED
```

Do **not** use “Identity Continuity v1 — operationally proven” until staging 24h soak completes
and a filled certificate is issued. Local precursor ≠ production readiness.

```text
9b88741          → system can do it (implementation)
this soak gate   → how we prove it in staging (ops)
staging soak     → certificate (future artifact)
```

## Three layers of responsibility (stop here — no more architecture)

| Layer | Doc | Question |
|-------|-----|----------|
| **1. Design contract** | reducers, vocabulary, policy | Stream + reducers + policy — is the fold correct? |
| **2. Soak gate** | this document | Can **production** break it? |
| **3. Retention runbook** | [`identity-continuity-v1-retention-replay-runbook.md`](identity-continuity-v1-retention-replay-runbook.md) | Can **time** break it? |

Retention did not become a second truth source. Hold layer roles strictly:

```text
Stream      = history
Snapshot    = acceleration
Projection  = view
Cache       = optimization
```

## Before staging soak (checklist)

**Before 24h clock starts** — all must pass; do not advance the v1 label early:

- [ ] Commit `9b88741` deployed to staging
- [ ] Flags enabled (`IDENTITY_GOVERNANCE_STREAM_*`)
- [ ] Retention runbook §0 execution record filled
- [ ] Baseline captured (stream heads, replay p95/p99, convergence)
- [ ] Durability passed (runbook §7)
- [ ] Destructive restore drill passed (runbook §6 / soak §7)

**Execution order** (restore before 24h — no point measuring drift if identity cannot be rebuilt):

| Phase | When | Doc |
|-------|------|-----|
| **1. Durability** | Day 0 | runbook §7 — stream survives storage restart |
| **2. Restore drill** | Day 0 | runbook §6 / soak §7 — delete projections → replay → authorize |
| **3. 24h soak** | Day 1+ | this document §1–8 — real traffic, measure drift/replay |
| **4. Sign-off** | After T+24h | runbook **After sign-off** tag |

- [ ] Fill retention runbook §0 — environment parameters + **Soak execution record**
- [ ] Stream durability check (runbook §7)
- [ ] Destructive restore drill passed (runbook §6) **before** starting 24h clock
- [ ] Both flags enabled on target environment
- [ ] Baselines copied into execution record

**Append contract** (commit `9b88741`): `event_id` = producer idempotency key at append boundary —
not a database UUID. Producer → append boundary → event stream.

**Next step is not more code** — run the sequence until you can report:

> we deleted state and restored identity only from history.

That is when the model starts to live — not when reducer tests pass.

### Why this foundation matters (Simple Phone)

```text
Phone lost          → not identity lost
Database rebuilt    → not identity lost
New device          → new credential.bound event (same stream)
```

Device is **replaceable**; identity is a **historical object**. Simple Phone builds on this
layer — not on another mobile wallet with a hidden user table.

## Operational freeze point (what v1 means)

Not “done forever”. **Identity Continuity v1** means:

| Pillar | Meaning |
|--------|---------|
| **Model frozen** | Event vocabulary + reducers fixed until real load forces change |
| **Recovery of state proven** | Projections disposable; stream rebuilds identity |
| **Operational behavior measured** | Convergence, replay budget, soak + drills |

Real contract after pass:

```text
Event Stream
    +
Reducer correctness
    +
Operational replay
```

**After `Identity Continuity v1 — operationally proven`:** no new ADRs on **core identity**.
Accepted changes only:

| Allowed | Not allowed |
|---------|-------------|
| Producer changes | New recovery / identity tables |
| Policy changes | Parallel “master” identity DB |
| Operational changes | New root-of-trust services |
| New events via existing extension path | Hidden state outside stream |

New capability → new event producer → same stream → same replay.

**Scope:** identity governance stream path with both flags enabled:

```env
IDENTITY_GOVERNANCE_STREAM_ENABLED=true
IDENTITY_GOVERNANCE_STREAM_AUTHORIZE_ENABLED=true
```

**Model status during soak:** frozen. No new domain events, reducers, or ADRs until soak passes
or fails with actionable ops data.

**Pass label (after soak):** `Identity Continuity v1 — operationally proven`  
Then: no model changes until real production load forces them.

---

## Operational contract (unchanged)

```text
Event Stream → Replay Engine → Registry / Governance
                                    ↓
                         Credential Projection
                                    ↓
                    Authorize + Runtime Evidence
```

| Layer | Truth? |
|-------|--------|
| `identity_governance_stream_events` | **Yes** |
| Projection cache | Disposable |
| `sign_count` counter cache | Telemetry only |
| Login / authorize | **Must not** append identity events |

Future mechanisms stay **new producers + policy evaluation** on the same stream:

| Mechanism | Shape |
|-----------|--------|
| Recovery | evidence → policy → `credential.bound` |
| Guardian | attestation → policy → event |
| Root | `authority.mode_changed` |

---

## Soak schedule (24h clock — after pre-soak gates pass)

Pre-soak ( **not** part of the 24h clock): durability §7 → restore drill §6 → execution record filled.

| When | Action |
|------|--------|
| T+0 | Start 24h clock; baselines in execution record |
| Every 15–60 min | Sections 1–3 checks |
| Every 4–6 h | Section 4 crash drill (rotate A / B / C) |
| Every 6 h | Section 5 duplicate-delivery drill |
| T+12 h | Section 6 authorize survival (cache wipe + restart) |
| T+24 h | Sections 1–3 final pass; retention §0 signed; sign-off |

Optional: repeat stream restore drill (soak §7) during soak only on **isolated** staging shard.

### Stop soak (halt clock immediately)

From retention runbook §0 **Soak execution record**:

- convergence ≠ clean
- replay budget exceeded (critical)
- stream version gap
- duplicate event anomaly
- authorize failure after projection rebuild
- **unexpected append during authorize** → **`authorize ≠ append`** violated

Fix or roll back flags. Partial soak does not count.

---

## 1. Stream health

Collect periodically **per stream** (`stream_id`):

| Metric | Source |
|--------|--------|
| `stream_head_version` | `MAX(version)` for stream |
| `event_count` | `COUNT(*)` for stream |
| `append_rate` | appends / hour (from timestamps or app metrics) |
| `append_error_rate` | failed appends / total append attempts |
| `duplicate_rate` | idempotent replays / append attempts |

### Integrity checks

For each stream, versions must be contiguous:

```text
version(n+1) = version(n) + 1
```

**Must not appear:**

- gaps in `version` sequence (holes)
- backdated append (`version` not equal to prior head + 1 at insert time)
- unexpected head jumps without a matching append audit trail

Example SQL (adjust connection / RO replica as needed):

```sql
SELECT stream_id,
       COUNT(*) AS event_count,
       MAX(version) AS stream_head_version,
       MIN(version) AS min_version
FROM identity_governance_stream_events
GROUP BY stream_id;

-- holes: expected count vs (max - min + 1)
SELECT stream_id,
       MAX(version) - MIN(version) + 1 AS expected_count,
       COUNT(*) AS actual_count
FROM identity_governance_stream_events
GROUP BY stream_id
HAVING expected_count <> COUNT(*);
```

**Fail:** any hole query returns rows, or head/version monotonicity breaks between samples.

---

## 2. Projection convergence

Each cycle, for every active stream:

```text
current projection  ==  replay(stream)
```

| Drift metric | Target |
|--------------|--------|
| `registry_drift` | 0 |
| `governance_drift` | 0 |
| `credential_projection_drift` | 0 |

**Automated check (Invariant 10):**

```bash
php artisan identity-governance:check-convergence
php artisan identity-governance:check-convergence sl1e_...
```

Implementation: `IdentityGovernanceProjectionConvergenceChecker`  
Covers: full replay vs cache, registry idempotency, governance snapshot+tail, credential idempotency.

**Fail:** any non-empty violation list. **Stop soak.**

Run on cron during soak (e.g. every 5–15 minutes), not only at T+24 h.

---

## 3. Replay budget

Collect per stream, each sample:

| Metric | In code today |
|--------|----------------|
| `event_count` | yes |
| `full_replay_ms` | yes |
| `snapshot_tail_ms` | yes |
| `credential_replay_ms` | yes |
| `ms_per_1000_events` | yes (`ms_per_1k_events`) |

```bash
php artisan identity-governance:replay-budget
php artisan identity-governance:replay-budget sl1e_...
```

Config thresholds (`config/identity_governance.php`):

| Env | Default | Role |
|-----|---------|------|
| `IDENTITY_GOVERNANCE_REPLAY_MS_PER_1K` | 500 | critical ms/1k |
| `IDENTITY_GOVERNANCE_MAX_FULL_REPLAY_MS` | 30000 | critical full replay |
| `IDENTITY_GOVERNANCE_WARN_STREAM_EVENTS` | 10000 | warn event volume |

### Soak alerting (beyond single-sample artisan)

Record **every** budget sample to time-series storage during 24h.

| Severity | Condition |
|----------|-----------|
| **warning** | `ms_per_1k_events` > **baseline × 2** (per stream) |
| **critical** | `full_replay_ms` > `max_full_replay_ms` |

Use **p95 / p99** of `full_replay_ms` and `ms_per_1k_events`, not mean alone. A correct fold
that spikes on tail latency still kills ops.

**Fail:** critical threshold breached on p95 at end of soak, or sustained warning with no
explainable load spike.

Phase C snapshots are accelerators only — they must not become a second source of truth.

---

## 4. Crash drills

Run at least one of A / B / C per rotation during soak (staging or controlled production
window). Happy path writers use `IdentityGovernanceStreamWriter` (append + projection in one
transaction). Drills B and C deliberately exercise failure modes replay must heal.

### A — before append

Kill process **before** append begins.

| Expect |
|--------|
| No new row in stream |
| Head version unchanged |
| No partial projection |

### B — after append, before projection

Persist event via raw appender **without** cache update (or kill between commit and cache write).

| Expect |
|--------|
| Stream contains event |
| Projection missing or stale |
| After restart + replay → projection restored |
| Convergence checker clean |

CI precursor: `IdentityGovernanceChaosSoakGateTest::chaos_crash_after_append_before_projection_is_healed_by_replay`

### C — after projection, before response

Client sees timeout; server may have completed write.

| Expect |
|--------|
| Retry with same `event_id` is safe |
| No duplicate version |
| Same semantic result |

---

## 5. Duplicate delivery

Simulate:

```text
client append → timeout → retry → retry → retry
(same event_id, same expected_version on first success)
```

| Expect | Must not |
|--------|----------|
| 1 event row | 4 event rows |
| 1 version increment | 4 version increments |
| Same replay result | Divergent projections |

CI precursor: `IdentityGovernanceChaosSoakGateTest::chaos_duplicate_delivery_retry_keeps_event_count_plus_one`  
Appender: `event_id` unique + idempotent replay (`idempotentReplay: true` on retry).

Track `duplicate_rate` during soak; spikes should correlate with client retries, not with
version gaps.

---

## 6. Authorize survival

The defining production test:

```text
register / vault bind
    → append stream (identity.created … credential.bound.webauthn)
    → DELETE projection cache (+ counter telemetry if testing full cold start)
    → restart app / worker node(s)
    → replay (implicit on read or explicit rebuild)
    → authorize (options + verify)
```

| Check | Expect |
|-------|--------|
| `can authorize` | true |
| Stream head after login | **unchanged** |
| Stream row count after login | **unchanged** |
| New identity events from login | **none** |

Login = runtime evidence + telemetry, not new history.

CI precursor: `IdentityGovernanceStreamAuthorizeContinuityGateTest`,  
`IdentityGovernanceChaosSoakGateTest::chaos_kill_cache_restart_concurrent_append_replay_authorize`

This proves projections are disposable. Section 7 proves **the stream alone** survives total
data loss elsewhere.

---

## 7. Stream restore drill (pre-production gate)

Stronger than section 6 (delete projection → replay). Tests the promise:

> projection can be lost; identity remains.

```text
backup identity_governance_stream_events (all streams or sample stream)
    ↓
destroy database (or fresh empty DB — no projection cache, no passkeys, no legacy identity rows)
    ↓
restore stream table / dump only
    ↓
rebuild everything (replay all projections from stream)
    ↓
authorize (options + verify)
```

| Check | Expect |
|-------|--------|
| Rows in stream after restore | match backup |
| Registry / governance / credential after replay | match pre-destroy replay |
| `can authorize` | true |
| Stream head after authorize | **unchanged** |

**Fail:** authorize works only with legacy tables, passkeys, or other non-stream state.

Run once in staging before production cutover; optional repeat on a sample stream during 24h
soak if infra allows (destructive — not on shared prod without isolation).

Procedure sketch:

```bash
# 1. Backup
pg_dump … -t identity_governance_stream_events > stream-backup.sql

# 2. Destroy / fresh migrate (staging)
php artisan migrate:fresh   # or targeted truncate of all non-stream identity tables

# 3. Restore stream only
psql … < stream-backup.sql

# 4. Rebuild + verify
php artisan identity-governance:check-convergence
php artisan identity-governance:replay-budget
# manual authorize against restored identity
```

---

## 8. Observability (minimum dashboard)

During soak, surface at least:

| Panel | Notes |
|-------|--------|
| Identity streams | active `stream_id` count |
| Events / hour | aggregate + per stream |
| Append latency | p50 / p95 / p99 |
| Replay latency | from scheduled `replay-budget` samples |
| Projection freshness | cache `updated_at` vs stream head (if exposed) |
| Authorize success rate | stream authorize path only |
| Authorize failures | by reason (challenge, assertion, not found) |

Not all panels exist in-repo today — soak owner wires app logs / APM / cron JSON output into
their ops stack. Commands emit the numbers replay and convergence need:

```bash
php artisan identity-governance:check-convergence
php artisan identity-governance:replay-budget
```

---

## 9. Data retention / replay horizon (ops policy, before sign-off)

Production will ask: **how much history do we actually keep?**

The **model** assumes:

```text
stream → full history (append-only truth)
```

**Operations** must document an answer — not as a model change, but as **ops policy**:

| Question | Policy must answer |
|----------|-------------------|
| Is the full event stream retained always? | yes / no + horizon |
| When do snapshots appear? | trigger (age, event count, replay budget) |
| Can old events be archived? | yes / no + rules |
| How is archive verified? | periodic replay test |

Example lifecycle (illustrative — set numbers per environment):

```text
Hot stream (online DB)
    0–90 days (or N events)
        ↓
Snapshot (Phase C accelerator — fold checkpoint, not truth)
        ↓
Cold archive (object storage / RO replica)
        ↓
Replay test (restore archive slice + tail → same projection)
```

### Non-negotiable rule

```text
archive ≠ new truth
```

| Layer | Role |
|-------|------|
| Stream (+ archive as **copy of stream**) | Truth |
| Snapshot | Replay accelerator; must reproduce same fold as full history |
| Projection cache | Disposable |
| Cold storage | Durability + cost; not a second identity model |

Before sign-off, publish and follow
[`identity-continuity-v1-retention-replay-runbook.md`](identity-continuity-v1-retention-replay-runbook.md)
(hot window, snapshot triggers, cold archive, replay cadence, alerts). Keep it ops-only — it
must not become a new layer of truth.

Changing retention policy is an **operational change**, not a v2 model — unless replay from
declared horizon can no longer reproduce authorize material (then fix ops or escalate with
evidence, do not silently add a parallel identity store).

---

## CI structural precursors (not a substitute for 24h)

`php artisan test tests/Feature/IdentityGovernanceChaosSoakGateTest.php`

| Scenario | Soak section |
|----------|----------------|
| Kill cache + restart + authorize | 6 |
| Stale concurrent append | 1 (append_error_rate) |
| Append without projection update | 4B |
| Duplicate `event_id` | 5 |
| Replay budget sample | 3 |

Section 7 (full DB restore) and section 9 (retention policy) are **staging / runbook** gates —
no automated CI equivalent yet.

Full governance suite:

```bash
php artisan test tests/Unit/Governance/ tests/Feature/IdentityGovernance*.php
```

---

## Sign-off checklist

- [ ] **Soak execution record** filled (retention runbook §0 — start + baselines)
- [ ] Pre-soak: durability + restore drill **passed before** 24h clock
- [ ] 24h continuous append on production-shaped traffic (not synthetic-only)
- [ ] Stream health: no holes, monotonic heads, duplicate_rate explained
- [ ] Convergence: zero violations every cycle
- [ ] Replay budget: p95 within warning; no critical breaches
- [ ] Crash drills A, B, C executed at least once each
- [ ] Duplicate delivery drill passed under load
- [ ] Authorize survival after cache wipe + restart (§6)
- [ ] **Stream restore drill:** backup stream → destroy DB → restore stream only → replay → authorize (§7)
- [ ] **Retention / replay horizon** — runbook published and acknowledged (§9 →
      [`identity-continuity-v1-retention-replay-runbook.md`](identity-continuity-v1-retention-replay-runbook.md))
- [ ] Dashboard / alerts reviewed; no silent authorize degradation (§8)
- [ ] **Certificate** issued from template (filled, stored outside or in `docs/records/`)

**On pass:** copy [`identity-continuity-v1-certificate.template.md`](identity-continuity-v1-certificate.template.md)
→ fill → store as ops record (e.g. `docs/records/identity-continuity-v1-<env>-<date>.md` or wiki).
Do not treat the launch date as part of the identity system — the repo holds **guarantees**;
the certificate holds **this version proved them then-there**.

Short tag for release notes:

```text
Identity Continuity v1 — Operationally Proven
Guarantees: state reconstructible · authorization recoverable · projections disposable ·
schema evolution supported · replay measurable
```

Update `docs/identity-claims-model.md` with link to filled certificate.

**On fail:** document which section broke, stream ids affected, and head version at failure.
Fix ops or code; re-run full 24h — partial soak does not count.

---

## Related docs

- `docs/governance-reducer-invariants.md` — Invariants 10–13
- `docs/identity-governance-event-vocabulary.md` — frozen event types
- `docs/identity-claims-model.md` — model freeze + v1 status
- [`identity-continuity-v1-certificate.template.md`](identity-continuity-v1-certificate.template.md) — sign-off milestone (ops record)
- [`identity-continuity-v1-retention-replay-runbook.md`](identity-continuity-v1-retention-replay-runbook.md) — hot / snapshot / archive / replay cadence
