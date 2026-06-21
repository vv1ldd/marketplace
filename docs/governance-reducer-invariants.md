# Governance Reducer Invariants

Design contract — **before runtime**. The schema, ADR, and vocabulary are mature
enough; bugs will appear in **how the reducer interprets event history**, not in
the model labels.

## What changed

Identity is no longer modeled as:

```text
Passkey → Identity
```

It is modeled as:

```text
Credentials
    ↓
Evidence (session-scoped)
    ↓
Policy Engine
    ↓
Identity Continuity (sl1e_)
```

```text
Identity ≠ credential
Identity continuity ≠ passkey
Identity continuity = governance policy evaluated against evidence
```

That is the boundary where Simple L1 stops being “login with passkey” and becomes
an identity layer.

Related:

- [ADR 0023](adr/0023-identity-authority-and-credential-ownership.md) — Identity / Proof
- [ADR 0024](adr/0024-identity-root-authority-and-phased-sovereignty.md) — Governance / Continuity
- [recovery-policy-schema.md](recovery-policy-schema.md) — Policy / Factors / Evidence
- [identity-governance-event-vocabulary.md](identity-governance-event-vocabulary.md) — log vocabulary, registry boundary
- Reference implementation: `app/Services/Identity/Governance/GovernanceReducer.php`
- Registry projection: `IdentityRegistryReducer.php`
- Property tests: `tests/Unit/Governance/GovernanceReducerInvariantsTest.php`

Run:

```bash
php artisan test tests/Unit/Governance/GovernanceReducerInvariantsTest.php
```

## Architecture stack

```text
ADR 0023     Identity / Proof        (sl1e_, credentials, relying parties)
ADR 0024     Governance / Continuity (policy, authority, phased sovereignty)
Reducer      Execution engine        (deterministic fold of event log)
Session engine Runtime only           (policy + factors + session evidence → allow/deny)
```

The reducer answers:

> Did identity continuity survive this entire event history?

Provider → hybrid → user_root becomes a **policy change**, not an identity
migration, **if and only if** the reducer layer stays pure and replayable.

## Log vs registry (Invariant 7)

The main risk after shipping an append-only log is **drift between registry and
log**.

```text
Identity Registry (projection)     Governance log (source of truth)
 ├─ entity exists                    ├─ identity.created
 ├─ username                         ├─ policy / credentials / authority
 └─ bindings                         └─ continuity
```

Not:

```text
Registry owns sl1e_
Log references sl1e_
```

Both registry and governance materialized views **fold the same log**:

```text
identity.created → append → IdentityRegistryReducer.fold
                         → GovernanceReducer.fold
```

See `identity-governance-event-vocabulary.md`.

## Scope split

| Component | Input | Output | Persisted? |
|-----------|-------|--------|------------|
| **Governance reducer** | Governance event log | Materialized projection | Projection is cache |
| **Session policy engine** | Policy + active factors + **session** evidence | Allow / deny | Never |

Invariants 1–3 and 5 apply to the **reducer**. Invariants 4 and 6 apply to the
**session engine** (and to which events the reducer consumes).

The reducer MUST NOT fold `recovery.factor.verified` into materialized view
state. Verified factors are session-scoped evidence, not permanent enrollment.

## Invariant 1 — Replay determinism

The same ordered event log always yields the same projection.

```text
fold(events) === fold(events)
```

Implications:

- No `now()`, randomness, DB lookups, or external API calls inside `fold`.
- Event ordering uses explicit `sequence` (or log position), not insertion time.
- `computed_at` and cache metadata are added **when writing** a materialized
  view row — not by `fold`.

Violations show up months later as “works in prod, different result after rebuild”.

## Invariant 2 — Revocation wins

After:

```text
credential.bound   (factor_id = X)
credential.revoked (factor_id = X)
```

factor `X` is **not active**, regardless of earlier binds.

```text
active_factor(f) = last binding_state(f) === active
```

Re-bind is a **new** `credential.bound` event, not mutation of history.

## Invariant 3 — Tier is derived

`protection_tier`, `recovery_state`, `fulfilled_classes`, and
`independent_dimensions_met` are **never** source-of-truth fields.

There is no code path that sets `"protection_tier": "gold"` without calling
`deriveProjection(state)`.

Gold (default v1 policy) conceptually:

```text
gold ⇔ policy_satisfied(current_policy, active_recovery_factors)
       AND independent_dimensions_met >= policy.minimum_independent_dimensions
```

Tier is a UI label on top of fulfillment — not part of the domain entity.

## Invariant 4 — Evidence is session-scoped

`recovery.factor.verified` creates **session evidence**, not enrollment state.

```text
Recovery Session A
  recovery.factor.verified(code)

Recovery Session B
  starts with empty evidence
```

Anti-pattern: folding verified events into “user has verified code forever” —
stale evidence becomes exploitable weeks later.

Session engine rules:

- Evidence list is keyed by `session_id`.
- Expired / completed sessions discard evidence from runtime evaluation.
- Only `recovery.completed` (and follow-ups like `passkey.rebound`) become
  permanent history — and rebound is expressed as `credential.bound`, not as
  retained verification.

## Invariant 5 — Policy changes are forward-only

Events are immutable. A stricter policy does not rewrite history:

```text
T1  recovery_policy.declared  { knowledge, possession }
T2  credential.bound           (knowledge)
T3  recovery_policy.declared  { knowledge, possession, social }
```

After T3, projection may drop from Gold → Silver, but T1–T2 events are unchanged.
Only the **latest** `recovery_policy.declared` (by sequence) defines `current_policy`.

## Invariant 6 — Verifier independence

The governance layer must not branch on **how** a factor was verified.

These events are equivalent to policy evaluation:

```text
recovery.factor.verified  verification_method=webauthn
recovery.factor.verified  verification_method=code_hash
recovery.factor.verified  verification_method=guardian_attestation
```

The engine sees only:

```text
factor_id X satisfied in session S
```

Anti-pattern in the governance core:

```php
if ($method === 'webauthn') { ... }
```

**Reducer:** ignores `recovery.factor.verified` entirely (Invariant 4) — method
is irrelevant because verification never enters fold state.

**Session engine:** `GovernanceSessionEvidence::fromVerifiedEvent()` strips
`verification_method`; `SessionPolicyEngine::evaluate()` is method-blind.

Verifiers (WebAuthn, recovery code UI, guardian, root key) live at the edge and
emit normalized events. They are not part of the reducer.

## Invariant 7 — Registry is a projection

`vault_identities`, username rows, and binding tables are **read models** — not
owners of `sl1e_`. Creation and changes append log events; registry folds them.

If registry and log disagree, **log wins** — rebuild registry projection.

Implementation: `IdentityRegistryReducer.php`

## Invariant 8 — Historical compatibility

```text
events_v1  →  reducer_v2  →  same semantic continuity
```

Legacy type names (`recovery_policy.declared`, `root_authority.declared`, …) MUST
keep working via `GovernanceEventTypes::normalize()`. New code emits canonical
names; old logs replay without migration.

Provider → hybrid → user_root = `authority.mode_changed`, not ALTER TABLE.

Implementation: `GovernanceReducerInvariantsTest::test_legacy_event_types_*`

## Primary property test — snapshot replay equivalence

Before snapshots, caches, or background rebuild jobs ship:

```text
∀ events, ∀ split point k:

  fold(events)
    ===
  fold_from_snapshot( fold_to_snapshot(events[0..k]), events[k+1..] )
```

Run with synthetic random histories (1 000–10 000 iterations):

```text
credential.bound
credential.revoked
recovery_policy.declared
root_authority.declared
...
```

If this holds, incremental materialized views are safe.

Implementation: `GovernanceReducerInvariantsTest::test_snapshot_replay_equivalence`

## Snapshot idempotency

Re-canonicalizing a snapshot must not change state:

```text
reSnapshot(snapshot) === snapshot
reSnapshot(reSnapshot(snapshot)) === snapshot
```

If snapshot compression is applied in production caches, idempotency guarantees
that “snapshot of a snapshot” does not drift.

Implementation: `GovernanceReducerInvariantsTest::test_snapshot_is_idempotent`

## Secondary property tests

| Property | Assertion |
|----------|-----------|
| Revocation | After bind + revoke, factor absent from `active_factors` |
| Determinism | Shuffle-safe: only sequence order matters, not event object identity |
| No verified in fold | Injecting `recovery.factor.verified` into log does not change projection |
| Verifier independence | Same projection regardless of `verification_method` on verified events |
| Session method-blind | `SessionPolicyEngine` same decision for webauthn / code / guardian |
| Policy forward-only | Prefix events unchanged when suffix adds `recovery_policy.declared` |
| Tier purity | Projection equals `deriveProjection(foldState)` — no shortcut setters |
| Snapshot idempotency | `reSnapshot` is idempotent on random histories |
| Registry projection | Revoked credential absent from registry bindings and governance factors |
| Historical compatibility | Legacy event type names → same governance projection as canonical |

## Reducer fold algorithm (sketch)

```text
state = empty

for event in events ordered by sequence:
  match event.type:
    credential.bound      → state.factors[id] = active(payload)
    credential.revoked      → state.factors[id] = revoked
    recovery_policy.declared → state.policy = payload
    root_authority.declared  → state.root_authority_mode = payload.mode
    recovery.factor.verified → IGNORE (session engine only)
    recovery.completed       → IGNORE for materialized view (or optional audit flag)
    passkey.rebound          → treat as credential.bound if not already modeled

return deriveProjection(state)
```

## Governance events consumed by reducer

| Event | Effect on fold state |
|-------|----------------------|
| `recovery_policy.declared` | Replace `current_policy` |
| `root_authority.declared` | Replace `root_authority_mode` |
| `credential.bound` | Upsert active factor |
| `credential.revoked` | Mark factor revoked |

## Events **not** consumed by reducer

| Event | Handler |
|-------|---------|
| `recovery.factor.verified` | Session policy engine only |
| `recovery.completed` | Session outcome; may trigger new binds |
| `recovery_code.consumed` | Session / factor lifecycle (future) |
| `passkey.rebound` | Prefer explicit `credential.bound` for daily passkey |

## Design freeze (stop adding concepts)

The architecture is sufficient for implementation:

Identity · Governance · Policy · Factors · Evidence · Continuity · Event Log ·
Reducers · Snapshots · Projections · 9 invariants · property tests · historical
compatibility.

**Do not add** new ADRs, domain entities, or vocabulary entries until a real
append-only stream exists and dual replay runs on production-shaped data.

**Do not build** until log exists:

- Guardian · Root Recovery Key · Social recovery · Hardware token · Recovery portal
- New protection tiers · New factor classes · New recovery UX flows

Real logs surface ordering, idempotency, optimistic locking, and stream
versioning problems that ADRs cannot predict.

## Invariant 9 — Stream monotonicity (infrastructure)

For one stream (`stream_id = sl1e_...`):

```text
event.version(n+1) === event.version(n) + 1
```

No gaps. No reordering. No backdated append. The reducer assumes **linear history**.

Implementation: `IdentityGovernanceStreamAppendRules.php` (append boundary only).

Persisted record shape (Phase A):

```text
stream_id     sl1e_...
version       strict 1..N
event_type    canonical vocabulary
payload       JSON
created_at    audit only — not used by fold
```

Code maps `version` ↔ `GovernanceEvent.sequence` in tests and reducers.

## Append contract (Phase A — concurrency)

The next risk is **not recovery** — it is **concurrency** at append time.

```php
append(
    stream_id: $streamId,
    expected_version: $expectedVersion,
    event_id: $eventId,
    event_type: $eventType,
    payload: $payload,
);
```

### Expected version (optimistic locking)

`expected_version` MUST equal current stream head (`0` for genesis).

Two writers both reading head `12` and both writing version `13` — only one succeeds;
the other gets `IdentityGovernanceStreamConcurrencyException`.

Without this, reducer invariants are meaningless.

### Event id idempotency

Client retry after timeout:

```text
append → DB commit → network timeout → retry same event_id
```

Same `event_id` MUST return the same result — no duplicate row.

Different payload with same `event_id` → `IdentityGovernanceStreamIdempotencyConflictException`.

### Concurrency gate

> Can two concurrent writers corrupt an identity stream?

Answer must be **No** before Phase B.

Implementation: `IdentityGovernanceStreamAppender.php`  
Tests: `tests/Feature/IdentityGovernanceStreamAppendTest.php`

## Genesis rule

First append to an empty stream MUST be:

```text
version: 1
event_type: identity.created
```

Reject `credential.bound`, `policy.declared`, or any other event before the
stream exists. Prevents import/migration/repair paths from creating orphan
credentials.

Reducers may still fold synthetic test histories without genesis; **append API**
enforces genesis.

## Phase A — closed

Append-only stream with:

- stream monotonicity + genesis rule
- `append(stream_id, expected_version, event_id, event)`
- optimistic concurrency + event id idempotency
- persisted events + `loadEvents()`

**Do not add new domain rules** until real writers run in production.

## Read consistency contract (append ↔ projection boundary)

The next risk is **projection lag**, not stream correctness.

```text
register passkey → credential.bound → immediate authorize
```

If authorize reads a stale projection cache, users get false denials.

### Strong (chosen for Identity Provider v1)

```text
append → persist → replay projections → update cache → 200 OK
```

Read after successful append MUST see updated projections. Slower writes;
consistent reads.

Implementation: `IdentityGovernanceStreamWriter.php` (same DB transaction).

### Eventual (not used for identity flows in v1)

```text
append → 200 OK → async projection catch-up
```

Faster writes; clients must tolerate lag. Unacceptable for immediate authorize
after credential bind unless reads go to stream directly.

### Read path

```text
read(stream_id):
  projection cache hit  → return
  cache miss / restart  → replayFull(stream) → warm cache → return
```

Cache is a performance layer — **stream is truth**. Restart drops cache and
rebuilds identical projections from events.

## Projection replay invariant (persisted stream)

On real DB-loaded events:

```text
projectFromEvents(all)
  ==
governance: foldFromSnapshot(prefix) + tail loaded from DB
registry:   full replay (must match twice)
```

Implementation: `IdentityGovernanceProjectionRebuilder.php`  
Tests: `tests/Feature/IdentityGovernanceStreamReplayTest.php`

## Restart milestone (Phase B gate)

```text
create identity
append events
kill projection cache   (simulate process death)
replay stream
identical projections
```

If this passes on real database, continuity is infrastructure — not just a model.

Tests: `create_append_kill_cache_restart_replay_milestone`

## Provider reconstruction gate (Phase B)

Hypothesis:

```text
Can the governance provider be reconstructed from the stream alone?
```

Procedure:

```text
identity.created
identity.username_assigned
policy.declared
credential.bound(A)
credential.bound(B)
credential.revoked(A)

delete identity_governance_projection_cache (all rows)
replayFull(stream_id)
```

Expected after replay:

```text
username           = alice
current_policy     = policy.declared payload
active_factors     = [B]
registry.bindings  = [B active]
through_version    = 6
```

If live projection equals replayed projection, registry + governance + bindings +
policy are **projections of the log**, not parallel sources of truth.

Tests: `tests/Feature/IdentityGovernanceStreamReconstructionGateTest.php`

**Scope:** governance projections only. WebAuthn public key material for identity
runtime authorize is a separate store until `credential.bound` carries replay-ready
key material or runtime reads the same stream.

See **Credential material (Variant A target)** below and
`IdentityGovernanceCredentialReconstructionGateTest.php`.

## Credential material — source of truth (Variant A vs B)

Architectural stop signal:

```text
If Identity Provider cannot be fully restored from stream,
hidden state still exists somewhere.
```

Governance reconstruction gate covers registry + governance + bindings + policy.
**Credential continuity** is a separate question:

```text
After replay, can navigator.credentials.get(...) succeed server-side verify?
```

### Variant B — today (partial hidden state)

```text
Stream:     credential.bound { factor_id, class, type, metadata }
Runtime DB: credential_id, public_key, sign_count, transports
```

Runtime store is a **second source of truth**. Rebuilding governance without it
yields half-restored continuity (username/policy/factors OK, authorize fails).

Current producers (`IdentityGovernanceVaultStreamProducer`) emit metadata only.

### Variant A — target

```text
Stream
  credential.bound { factor_id, webauthn: { credential_id, public_key, ... } }
      ↓ replay
IdentityCredentialReducer
      ↓
Credential projection (verify-ready allowCredentials + public keys)
```

Credential store becomes a **projection** — destroy and replay like registry cache.

Target `credential.bound` payload extension (v1.1, backward compatible):

```json
{
  "factor_id": "…",
  "class": "possession",
  "type": "passkey",
  "webauthn": {
    "credential_id": "base64url",
    "public_key": "base64url",
    "sign_count": 0,
    "aaguid": "…",
    "transports": ["internal"]
  }
}
```

Events without `webauthn` fold to governance/registry only (legacy / interim).

### sign_count caveat

`sign_count` advances on each verified assertion. Target options:

| Approach | Model |
|----------|--------|
| Append `credential.counter_observed` | Counter in stream |
| Tolerant verify after replay | Counter cache, resync on next auth |
| Ignore for dev replay gate | Bind-time counter only |

Bind-time material in stream is the minimum bar for **credential reconstruction gate**.

Implementation: `IdentityCredentialReducer.php`  
Tests: `tests/Feature/IdentityGovernanceCredentialReconstructionGateTest.php`

**Not yet wired:** legacy identity runtime proxy when `stream_authorize_enabled` is false; full cryptographic E2E in CI (verify path uses real `AuthenticatorAssertionResponseValidator` in production).

### Authorize continuity gate (architecture completion)

```text
register → append credential.bound.webauthn
DELETE projection cache + passkeys (+ users)
replay stream
POST /api/sl1e/authorize/options   → allowCredentials from stream
POST /api/sl1e/authorize/verify    → PublicKeyCredentialSource from stream
```

Tests: `IdentityGovernanceStreamAuthorizeContinuityGateTest`

Flags: `IDENTITY_GOVERNANCE_STREAM_ENABLED`, `IDENTITY_GOVERNANCE_STREAM_AUTHORIZE_ENABLED`

### sign_count — runtime telemetry, not identity

Bind-time `sign_count` lives in `credential.bound.webauthn`. Advances after verify go to
`IdentityGovernanceCredentialCounterStore` (cache only) — **not** appended to the
identity stream. Future optional: `credential.counter_observed` if audit needs it.

## Stream Is Truth audit (stream authorize path)

Scope: `IdentityGovernanceStreamAuthorizeService` when both governance flags are
enabled. Question for each state used in authorize:

```text
Can this be recovered only from identity_governance_stream_events?
```

### Identity material (governance + registry projections)

| State | From stream? | Notes |
|-------|--------------|-------|
| `sl1e_` / stream_id | ✅ | Subject of all events |
| username | ✅ | `identity.username_assigned` |
| policy | ✅ | `policy.declared` |
| active factors | ✅ | `credential.bound` − `credential.revoked` |
| registry bindings | ✅ | Same log, `IdentityRegistryReducer` |
| protection tier | ✅ | Derived — never stored as truth |

### Credential material (credential projection)

| State | From stream? | Notes |
|-------|--------------|-------|
| factor_id | ✅ | `credential.bound` |
| credential_id | ✅ | `credential.bound.webauthn` |
| public_key | ✅ | `credential.bound.webauthn` |
| aaguid | ✅ | `credential.bound.webauthn` |
| transports | ✅ | `credential.bound.webauthn` |
| bind-time sign_count | ✅ | `credential.bound.webauthn.sign_count` |
| userHandle (verify) | ✅ | Derived as `stream_id` in `IdentityGovernanceWebAuthnCredentialSourceFactory` |

### Runtime / ephemeral (not identity — OK)

| State | From stream? | Role |
|-------|--------------|------|
| post-bind sign_count advance | ❌ | `IdentityGovernanceCredentialCounterStore` — telemetry |
| authorize challenge + flowId | ❌ | Cache 10 min — session evidence |
| WebAuthn assertion | ❌ | Ephemeral possession proof |
| Laravel session / proof tokens | ❌ | Commerce session, not identity log |

### Deployment config (not per-identity hidden state)

| State | From stream? | Role |
|-------|--------------|------|
| rpId | ❌ | `config('passkeys.relying_party.id')` — deployment invariant |
| attestationType / trustPath | ❌ | Hardcoded `none` / empty for passkey v1 |

These are environment boundaries, not alternate identity stores.

### Hidden state candidates (still in repo)

| Location | Risk | Mitigation |
|----------|------|------------|
| `passkeys` table | ❌ legacy authorize when stream authorize off | Flags + retire local login (ADR 0023) |
| Identity runtime DB (`SIMPLE_L1_RUNTIME_URL`) | ❌ when proxy path used | `stream_authorize_enabled` bypass |
| `simple_l1_identity_keys` | ❌ native direct proof path | Separate producer migration |
| `identity_governance_projection_cache` | ⚠️ performance only | Not read by stream authorize; replay on miss |
| `users.entity_l1_address` | ⚠️ commerce/profile | Not used by stream authorize verify |

**Stream authorize path:** no critical authorize field remains outside the log except
intentional runtime telemetry (`sign_count` cache) and ephemeral session artifacts.

### Two formulas (locked)

```text
Identity Continuity     = Replayable History
Authorization           = Replayable History + Runtime Evidence
```

Runtime evidence = WebAuthn assertion + challenge flow. Everything needed to know
**which keys exist** comes from replay.

### Architecture vs operations (next focus)

Architectural risk on stream authorize path: **closed** (gates green).

Next real failures usually appear in operations, not model:

- append/replay under load
- concurrent writers (Phase A contract — tests exist)
- real crash/restart drills
- event schema migration / historical compatibility
- observability: stream head lag, replay latency, projection freshness

Recovery, guardian, lineage = new producers on the same stream — not new roots of
trust.

## Model freeze — operations phase

Identity governance **model is frozen**. No new domain concepts until ops gates
hold in production. New capabilities (recovery, guardian, root authority) are
**producers** on the existing stream:

```text
credential.bound | credential.revoked | policy.declared | authority.mode_changed
```

Per-identity history (not global chain). Consistency within one `sl1e_` subject.

## Operational invariants (10–13)

### Invariant 10 — Projection convergence

After any replay path, projections MUST agree:

```text
fold(all events)
  ==
fold(snapshot + tail)     // governance
  ==
projection cache          // when warmed
  ==
fold(all events)          // registry + credential (idempotent)
```

Production check:

```bash
php artisan identity-governance:check-convergence
php artisan identity-governance:check-convergence sl1e_...
```

Implementation: `IdentityGovernanceProjectionConvergenceChecker.php`  
Tests: `IdentityGovernanceOpsInvariantsTest`, `IdentityGovernanceStreamReplayTest`

Run periodically (cron / health) — not only in CI.

### Invariant 11 — No hidden mutation on authorize

After `authorize/verify`, MUST change:

- telemetry (counter cache, audit logs)
- ephemeral session (challenge flow cache)

MUST NOT change:

- `identity_governance_stream_events` (head version, row count)
- credential identity in log
- policy / bindings in log

Login is not an identity mutation.

Tests: `IdentityGovernanceOpsInvariantsTest::invariant_11_authorize_does_not_mutate_identity_stream`

### Invariant 12 — Event schema evolution

Payloads carry optional `schema_version` (default `1` on append). Reducers
**normalize at read time** — never rewrite historical rows.

```json
{
  "schema_version": 1,
  "factor_id": "…",
  "webauthn": { }
}
```

```text
event vN  →  GovernanceEventPayloadNormalizer  →  current fold model
```

Old log remains historical fact.

Implementation: `GovernanceEventPayloadNormalizer.php`  
Tests: `IdentityGovernanceOpsInvariantsTest` (legacy + stamped append)

### Invariant 13 — Replay budget

Event-sourced systems fail operationally before they fail logically.

Track per stream:

```text
event_count
full_replay_ms
credential_replay_ms
snapshot_tail_ms
ms_per_1k_events
```

Budget config (`config/identity_governance.php`):

```text
max_ms_per_1k_events
max_full_replay_ms
warn_stream_event_count
```

Production check:

```bash
php artisan identity-governance:replay-budget
php artisan identity-governance:replay-budget sl1e_...
```

Example failure mode: `100k events → 300ms` today, `50M events → 20min` in a year —
model still correct, ops dead. Snapshots (Phase C) are **accelerators**, not truth.

Implementation: `IdentityGovernanceReplayBudgetChecker.php`  
Tests: `IdentityGovernanceChaosSoakGateTest`

## 24h soak milestone (ops)

**Full gate:** [`identity-continuity-v1-soak-gate.md`](identity-continuity-v1-soak-gate.md)  
Production readiness — try to break the system, not prove the architecture.

Not a new feature — production-shaped endurance:

```text
append real events (24h)
  → stream health (heads, holes, duplicate_rate)
  → check-convergence every cycle (drift = stop)
  → replay-budget samples (p95/p99, not mean only)
  → crash drills A / B / C + duplicate delivery
  → authorize after cache delete + restart (§6)
  → stream restore drill: backup stream only → rebuild → authorize (§7)
  → retention / replay horizon documented — archive ≠ truth (§9)
  → sign-off → Identity Continuity v1 — operationally proven
```

After pass: **Event Stream + reducer correctness + operational replay** = contract.
No new core identity ADRs — producer, policy, and ops changes only.

CI structural precursor: `IdentityGovernanceChaosSoakGateTest`:

| Scenario | Proves |
|----------|--------|
| Kill cache + restart + authorize | Projections disposable |
| Stale concurrent append | Optimistic locking holds |
| **Append without projection update** | Stream has event → replay heals cache |
| **Duplicate delivery (same `event_id`)** | `event_count +1`, not `+2` |
| Replay budget | Ops feasibility |

Production writers SHOULD use `IdentityGovernanceStreamWriter` (append + projection in
one transaction). Raw appender + missing cache is a **failure mode** replay must heal —
not the happy path.

Pass criteria: see soak doc sign-off checklist. Then identity is **operationally**
restorable — not only architecturally correct. **Model frozen** until real load
forces change.

## Lineage vs legitimacy (do not conflate)

Future optional payload field:

```json
{ "enrolled_by": "factor_B" }
```

| Role | Use |
|------|-----|
| **Audit / lineage graph** | Reducer may store; UI may render `A → B → C` |
| **Policy / continuity** | **Must not** depend on parent factor still active |

Legitimacy of a factor:

```text
append rules + policy + stream history
```

Not:

```text
credential genealogy chain intact
```

Example: `A revoked`, `B revoked`, `C active` — identity may still be legitimate
under policy. A broken genealogy tree must **not** invalidate `C`.

Rule:

```text
enrolled_by explains origin; it does not define legitimacy.
```

Signed enrollment (user-sovereign) is evidence in the **event**, not a new root of
trust inside the passkey.

## Implementation roadmap (Phases A–D)

| Phase | Deliverable | Gate |
|-------|-------------|------|
| **A** | Append-only stream + append contract | **Closed** |
| **B** | Strong writer + restart replay on real DB + real writers wired | After A |
| **C** | Snapshot persistence table (optional optimization) | After B |
| **D** | Verifiers as event producers (enrollment UX) | After B/C |

Phase D producers emit ordinary events:

```text
credential.bound
evidence.verified
continuity.reestablished
```

Recovery is not the foundation — it is another evidence source for the same
governance engine.

## When to implement runtime
3. **Phase B** — dual replay on real persisted stream
4. **Phase C** — snapshot persistence on real log
5. **Phase D** — verifiers and enrollment UX at the edge

No new domain design documents until Phase B completes on real data.

Reference code:

| Component | Path |
|-----------|------|
| Reducer | `GovernanceReducer.php` |
| Registry reducer | `IdentityRegistryReducer.php` |
| Append rules | `IdentityGovernanceStreamAppendRules.php` |
| Stream append | `IdentityGovernanceStreamAppender.php` |
| Strong writer | `IdentityGovernanceStreamWriter.php` |
| Projection rebuild | `IdentityGovernanceProjectionRebuilder.php` |
| Projection cache | `IdentityGovernanceProjectionCacheStore.php` |
| Session engine | `SessionPolicyEngine.php` |
| Event types | `GovernanceEventTypes.php` |
| Method-blind evidence | `GovernanceSessionEvidence.php` |
