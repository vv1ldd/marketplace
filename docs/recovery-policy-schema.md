# Recovery Policy Schema (v1)

Design checkpoint — **not implemented in runtime yet**. Canonical machine-readable
schema: [`schemas/recovery-policy-v1.schema.json`](schemas/recovery-policy-v1.schema.json).

Related: [ADR 0024](adr/0024-identity-root-authority-and-phased-sovereignty.md),
[identity-claims-model.md](identity-claims-model.md).

**Implementation priority:** event log + reducer invariants (see
`docs/governance-reducer-invariants.md`) before new factor types.

## Three persistence layers

| Layer | Role | Stored? |
|-------|------|---------|
| **Event log** | Source of truth | Append-only, permanent |
| **Materialized view** | Read projection for UI / API | Cache — recomputable |
| **Runtime decision** | Policy engine per session | **Never** persisted |

```text
Event log          Materialized view       Runtime (ephemeral)
─────────          ─────────────────       ───────────────────
credential.bound → protection_tier    →   Policy
recovery_policy  → recovery_state    →   + Active factors
  .declared      → fulfilled_classes →   + Session evidence
recovery.factor                           ↓
  .verified                            Allow | Deny
```

### Event log (source of truth)

```text
recovery_policy.declared
root_authority.declared
credential.bound
credential.revoked
recovery.factor.verified
recovery.completed
passkey.rebound
```

Never emit derived state as events:

```text
protection_tier.changed    ← tier is UI-only; recompute from log
recovery_state.changed     ← same
```

When policy weights or factor catalog change: re-run reducer on all entities —
no migration of stored tier flags.

### Materialized view (projection)

```json
{
  "entity": "sl1e_xxx",
  "root_authority_mode": "provider",
  "protection_tier": "gold",
  "recovery_state": "ready",
  "fulfilled_classes": ["knowledge", "possession"],
  "independent_dimensions_met": 2,
  "computed_at": "2026-06-20T12:00:00Z",
  "engine_version": "policy-v1"
}
```

Tier derivation (default v1 policy):

```text
bronze  = no active recovery factors
silver  = recovery available; policy not satisfied
gold    = policy satisfied AND independent_dimensions_met >= minimum
```

### Runtime decision (not stored)

```text
Policy + Active Factors + Session Evidence
              ↓
         Policy Engine
              ↓
      Allow rebind  |  Deny
```

Outcome may append `recovery.completed` and `passkey.rebound` to the event log.
The evaluation itself is not persisted.

## Core separation (Policy / Factors / Evidence)

Model **policy**, not **current credentials**.

| Layer | Question | Mutated by enrollment? | Mutated by recovery session? |
|-------|----------|------------------------|------------------------------|
| **Policy** | What is required? | `recovery_policy.declared` | No |
| **Factors** | What exists on the entity? | `credential.bound`, `recovery_code.issued` | No (except rebound / revoke) |
| **Evidence** | What was proved in this session? | No | `recovery.factor.verified` |

```text
recovery.factor.verified  →  creates Evidence
recovery.completed      →  policy engine decision (enough evidence + enrolled factors)
```

Policy is stable during a session. Verification appends evidence. The engine
decides whether accumulated evidence satisfies policy for identity continuation.

## Anti-pattern

Do **not** store credential booleans on the entity:

```json
{
  "recovery_code": true,
  "recovery_passkey": true
}
```

Guardian, root key, and hardware token would force migrations. Express
requirements as **classes**, **rules**, and **constraints** instead.

Do **not** write computed fulfillment back to the entity:

```json
{
  "protection_tier": "gold",
  "recovery_complete": true
}
```

That couples domain rows to a specific policy engine version. Use materialized
view instead.

## Policy

What is required to re-establish control after everyday credential loss.

### v1 default (reference ceremony)

```json
{
  "version": 1,
  "rule": "all",
  "required_factor_classes": ["knowledge", "possession"],
  "minimum_independent_dimensions": 2,
  "independence_dimensions": ["device", "ecosystem", "custody"],
  "declared_at": "2026-06-19T00:00:00Z"
}
```

Same tier engine, alternate Gold policy (future):

```json
{
  "version": 1,
  "rule": "min_strength",
  "minimum_strength": 100,
  "minimum_independent_dimensions": 2
}
```

Or hybrid N-of-M:

```json
{
  "version": 1,
  "rule": "min_count",
  "min_factor_count": 2,
  "required_factor_classes": ["knowledge", "possession", "social", "root"]
}
```

### Policy rules

| `rule` | Meaning |
|--------|---------|
| `all` | Every `required_factor_class` satisfied by distinct active factors |
| `any` | At least one listed class |
| `min_count` | At least `min_factor_count` factors (optionally scoped by classes) |
| `min_strength` | Sum of verified `strength_weight` ≥ `minimum_strength` |

## Factors

What is registered on the entity. Each factor has a stable `id` referenced by
evidence and history events.

### v1 reference ceremony — enrolled factors

```json
[
  {
    "id": "f7a2c1d0-8b3e-4f1a-9c2d-1e0f8a7b6c5d",
    "class": "knowledge",
    "type": "recovery_code",
    "status": "active",
    "strength": "medium",
    "strength_weight": 30,
    "metadata": {},
    "bound_at": "2026-06-19T01:00:00Z"
  },
  {
    "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "class": "possession",
    "type": "passkey",
    "purpose": "recovery",
    "status": "active",
    "strength": "high",
    "strength_weight": 40,
    "metadata": {
      "ecosystem": "android",
      "device_id": "pixel-8-abc",
      "custody": "self",
      "label": "Meanly Recovery · @alice"
    },
    "bound_at": "2026-06-19T02:00:00Z"
  }
]
```

Daily passkey is also a factor (`purpose: daily`) but typically excluded from
recovery policy evaluation unless policy explicitly requires it.

### Strength (future-proof)

| `type` | Suggested `strength` | Suggested `strength_weight` |
|--------|---------------------|----------------------------|
| `recovery_code` | medium | 30 |
| `passkey` | high | 40 |
| `hardware_key` | high | 50 |
| `guardian` | medium | 35 |
| `root_key` | critical | 100 |

Weights are policy-engine defaults; individual factors MAY override at bind time.
`minimum_strength` policies use weights instead of hard-coded factor lists.

### Independence dimensions

Evaluated **between contributing factors**, using `metadata`:

| Dimension | Field | Example |
|-----------|-------|---------|
| Device | `device_id` | iPhone vs MacBook |
| Ecosystem | `ecosystem` | apple vs android |
| Custody | `custody` | self vs delegated |

iPhone + MacBook under one Apple ID: device ✓, ecosystem ✗ — insufficient for
Gold unless a third dimension or knowledge factor is present.

## Evidence

What was verified in an **active recovery session**. Immutable once written.

```json
{
  "id": "e1111111-2222-3333-4444-555555555555",
  "session_id": "s9999999-8888-7777-6666-555555555555",
  "factor_id": "f7a2c1d0-8b3e-4f1a-9c2d-1e0f8a7b6c5d",
  "factor_class": "knowledge",
  "verified_at": "2026-06-20T12:00:00Z",
  "verification_method": "code_hash",
  "strength_weight": 30
}
```

Session with two verified factors:

```json
{
  "id": "s9999999-8888-7777-6666-555555555555",
  "entity_l1_address": "sl1e_...",
  "policy_snapshot": {
    "version": 1,
    "rule": "all",
    "required_factor_classes": ["knowledge", "possession"],
    "minimum_independent_dimensions": 2
  },
  "started_at": "2026-06-20T11:55:00Z",
  "status": "completed",
  "completed_at": "2026-06-20T12:05:00Z",
  "evidence": [
    {
      "id": "e1111111-2222-3333-4444-555555555555",
      "session_id": "s9999999-8888-7777-6666-555555555555",
      "factor_id": "f7a2c1d0-8b3e-4f1a-9c2d-1e0f8a7b6c5d",
      "factor_class": "knowledge",
      "verified_at": "2026-06-20T12:00:00Z",
      "verification_method": "code_hash"
    },
    {
      "id": "e2222222-3333-4444-5555-666666666666",
      "session_id": "s9999999-8888-7777-6666-555555555555",
      "factor_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
      "factor_class": "possession",
      "verified_at": "2026-06-20T12:05:00Z",
      "verification_method": "webauthn"
    }
  ]
}
```

Event sequence:

```text
recovery.factor.verified  (knowledge evidence)
recovery.factor.verified  (possession evidence)
recovery.completed        (engine: policy satisfied)
passkey.rebound
```

## Materialized view (projection schema)

Not source of truth. Rebuilt from event log + policy engine.

```json
{
  "entity": "sl1e_...",
  "root_authority_mode": "provider",
  "protection_tier": "gold",
  "recovery_state": "ready",
  "fulfilled_classes": ["knowledge", "possession"],
  "independent_dimensions_met": 2,
  "active_factor_count": 2,
  "computed_at": "2026-06-20T12:00:00Z",
  "engine_version": "policy-v1"
}
```

| Field | Derivation |
|-------|------------|
| `protection_tier: bronze` | No active recovery factors |
| `protection_tier: silver` | Recovery available; policy not fully satisfied |
| `protection_tier: gold` | Policy satisfied + independence constraints met |
| `recovery_state: unavailable` | No recovery path |
| `recovery_state: incomplete` | Some factors; policy not satisfied |
| `recovery_state: ready` | Enrolled factors satisfy policy |
| `fulfilled_classes` | Classes with active factors matching policy |
| `independent_dimensions_met` | Max dimensions spanned by contributing factors |

Do not persist `satisfied: ["recovery_code", ...]` anywhere — fulfillment is
always computed.

## Mapping to claims model

```text
Node     → Identity (sl1e_ / VaultIdentity)
Edge     → Policy (recovery_policy on entity)
Factor   → Credential binding (purpose-tagged)
Evidence → Session proof (recovery.factor.verified payload)
History  → Events (append-only event log)
Materialized view → read cache (not History)
```

The policy engine is **not recovery-specific**. The same evaluation pattern applies
to credential rotation, guardian approval, root authority transfer, and
ownership disputes:

> Is there enough evidence to satisfy policy?

A verified factor in recovery **is** evidence in the claims vocabulary — same
chain as Polygon proof verification, different verifier and payload.

## Reducer algorithm (sketch)

See **`docs/governance-reducer-invariants.md`** for formal invariants and property
tests. Reference code: `app/Services/Identity/Governance/GovernanceReducer.php`.

```text
1. Fold event log → internal state (policy, factors — no session evidence)
2. deriveProjection(state) → protection_tier, recovery_state, fulfilled_classes
3. On recovery_policy.declared or engine_version bump → recompute affected views
4. Snapshot replay: fold(events) === fold_from_snapshot(snapshot, tail)
```

## Implementation notes (when Gate opens)

1. Ship event log + reducer **before** new factor types or enrollment UX polish.
2. Recovery sessions append evidence events; runtime engine decides allow/deny.
3. Marketplace reads materialized view only; never evaluates policy.
4. Extend `factorType` enum and default weights without changing policy `version`
   until breaking rule semantics require `version: 2`.
5. Discard and rebuild materialized view at will — never treat it as audit trail.
