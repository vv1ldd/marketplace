# Identity Governance Event Vocabulary (v1)

Design checkpoint — **before production event log**. Defines the boundary between
**append-only log** (source of truth) and **projections** (registry, governance
materialized views).

Related:

- [governance-reducer-invariants.md](governance-reducer-invariants.md)
- [ADR 0024](adr/0024-identity-root-authority-and-phased-sovereignty.md)
- [recovery-policy-schema.md](recovery-policy-schema.md)
- Code: `GovernanceEventTypes.php`, `IdentityRegistryReducer.php`, `GovernanceReducer.php`

## The model

```text
Identity
    ↓
Governance
    ↓
Policy
    ↓
Evidence (session)
    ↓
Continuity
```

`sl1e_` survives passkey rotation, recovery model changes, authority mode changes,
guardian, and root key — **without changing the subject** — when the log is
primary and everything else is projection.

## Two worlds, one log

Today there are two logical surfaces:

```text
Identity Registry (projection)     Governance (projection)     Credential (projection, target)
 ├─ entity exists                    ├─ policy                    ├─ credential_id
 ├─ username                         ├─ credentials / factors     ├─ public_key
 └─ bindings                         └─ continuity / tiers        └─ sign_count, transports
```

Both fold from the **same append-only log**. Neither owns `sl1e_`.

Credential projection (`IdentityCredentialReducer`) is **target Variant A** — see
`governance-reducer-invariants.md` — Credential material. Not yet consumed by
identity runtime authorize.

Anti-pattern:

```text
Registry owns identity
Log references identity   ← drift guaranteed
```

Correct:

```text
identity.created  →  registry projection
credential.revoked → registry + governance projections update together
```

## Invariant 7 — Registry is a projection

The registry MUST NOT be written as a parallel source of truth.

```text
identity.created
      ↓
append-only log
      ↓
IdentityRegistryReducer.fold(events)  →  exists, username, bindings
GovernanceReducer.fold(events)        →  policy, tiers, factors
```

If `registry` says factor exists and `log` says `credential.revoked`, the log
wins — rebuild registry from log.

Implementation: `IdentityRegistryReducer.php`

## Invariant 8 — Historical compatibility

New reducer code MUST read old event types and preserve **semantic** continuity:

```text
events_v1  →  reducer_v2  →  same meaning
```

Byte-identical projection is not required. Identity continuity semantics are.

Legacy aliases (read path):

| Legacy (v0 docs) | Canonical (v1) |
|------------------|----------------|
| `recovery_policy.declared` | `policy.declared` |
| `root_authority.declared` | `authority.mode_changed` |
| `recovery.factor.verified` | `evidence.verified` |
| `recovery.completed` | `continuity.reestablished` |

Implementation: `GovernanceEventTypes::normalize()`

Provider → hybrid → user_root is a new log fact:

```text
authority.mode_changed  { "mode": "hybrid" }
```

not a table migration.

## Core vocabulary (minimal)

Six event types for the **identity governance log**. Resist adding more until a
reducer invariant cannot be expressed.

| Event | Meaning | Registry fold | Governance fold |
|-------|---------|---------------|-----------------|
| `identity.created` | `sl1e_` subject exists | `exists = true` | ignore |
| `identity.username_assigned` | `@username` projection | `username` | ignore |
| `policy.declared` | Recovery / continuity rules | ignore | `current_policy` |
| `credential.bound` | Factor registered | active binding | active factor | active WebAuthn material (if `webauthn` in payload) |
| `credential.revoked` | Factor removed | binding removed | factor revoked |
| `authority.mode_changed` | Governance mode | ignore | `root_authority_mode` |

If vocabulary grows quickly (per-screen, per-verifier, per-UI-step events), that
usually means implementation detail is leaking into the log.

## Session vocabulary (separate concern)

Not part of the six core events — session-scoped, never registry/governance fold:

| Event | Meaning |
|-------|---------|
| `evidence.verified` | Factor satisfied in session S (method stored for audit only) |
| `continuity.reestablished` | Session outcome: policy satisfied, re-bind allowed |

Verifiers (WebAuthn, recovery code, guardian) emit `evidence.verified` at the
edge. Governance core sees `factor_id` + `session_id` only (Invariant 6).

## Reference enrollment log (example)

```text
1  identity.created
2  identity.username_assigned   { username: "alice" }
3  authority.mode_changed       { mode: "provider" }
4  policy.declared              { required_factor_classes: [...] }
5  credential.bound             { factor_id, class: knowledge, ... }
6  credential.bound             { factor_id, class: possession, purpose: recovery, ... }
```

Replay:

```text
append → IdentityRegistryReducer.fold   → registry projection
       → GovernanceReducer.fold         → governance projection
       → IdentityCredentialReducer.fold → credential projection (verify-ready)
```

**Identity Continuity + Credential Continuity** from history requires Variant A:
`credential.bound.webauthn` in the log and runtime reading credential projection
after replay — not a parallel credential DB.

## What is NOT source of truth

| Field / row | Role |
|-------------|------|
| `vault_identities` row (today) | Legacy store — migrate toward projection |
| `protection_tier` column | UI cache — derive only |
| `recovery_complete` flag | Forbidden |
| Registry binding without log event | Forbidden |

## Next implementation step

1. Append-only **identity governance log** (single stream per `sl1e_`)
2. Dual reducers on replay (registry + governance)
3. Verifiers emit canonical events at the edge
4. Deprecate direct registry writes

Ship the log before recovery UI, passkey enrollment, guardian, or root key.

## Design freeze

**No new ADRs or domain concepts** until Phase B (dual replay on real persisted
stream). See `governance-reducer-invariants.md` — Phases A–D.

## Stream record (Phase A)

```text
stream_id     sl1e_...
version       1..N  (strictly monotonic, no gaps — Invariant 9)
event_type    canonical vocabulary
payload       JSON
created_at    audit metadata; fold uses version order only
```

Append boundary: `IdentityGovernanceStreamAppendRules.php` + `IdentityGovernanceStreamAppender.php`

```php
append(stream_id, expected_version, event_id, event_type, payload)
```

- `expected_version` = current head (`0` at genesis) — optimistic concurrency
- `event_id` = client-supplied UUID — retry idempotency
- same `event_id` → same result, no duplicate event

## Read consistency (Phase B)

**Strong** for v1 — production writers use `IdentityGovernanceStreamWriter`, not raw appender:

```text
append → replay → projection cache → 200 OK
```

Eventual (async catch-up) is not used for identity flows where immediate read
after bind must succeed (e.g. passkey bind → authorize).

Details: `governance-reducer-invariants.md` — Read consistency contract.

## Genesis

Empty stream → first append MUST be `identity.created` at `version: 1`.

Reject `credential.bound` (and all other types) before genesis. Prevents orphan
bindings during import, migration, or crash recovery.

## Duplicate / out-of-order reality

Real logs will contain messy sequences (double bind, revoke, re-bind, username
changes). Reducers handle history; **append rules** prevent invalid stream growth.
Ordering and idempotency lessons come from Phase A on real data — not from more ADRs.
