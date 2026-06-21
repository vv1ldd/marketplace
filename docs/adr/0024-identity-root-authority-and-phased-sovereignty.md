# ADR 0024: Identity Root Authority and Phased Sovereignty

**Scope:** This ADR defines the **identity governance layer** — who may re-establish
control of `sl1e_`, under what policy, and with what evidence. Recovery code and
recovery passkey are v1 reference factors, not the subject of the document.

## Status

Accepted

## Foundational principle

> **Identity continuity is determined by recovery policy, not by any individual
> credential.**

`sl1e_...` is the stable subject. Passkeys, recovery codes, guardians, and root
keys are **factors that satisfy policy** — they do not *own* identity. Any
factor can be replaced, rotated, or augmented without forking the entity, as
long as the recovery policy is updated and re-evaluated.

This is the architectural spine of the sovereign model:

```text
Identity Root Authority
        ↓
  Recovery Policy
        ↓
    Credentials
```

Not:

```text
Passkey → owns identity
```

But:

```text
Policy governs identity
Passkeys (and other factors) satisfy policy
```

A Root Recovery Key in Phase 3 is therefore not "the owner of identity" — it is
the strongest factor **within** policy. That distinction keeps future evolution
simple.

The same policy engine eventually answers one question for many flows:

> Is there enough evidence to satisfy policy?

Recovery, credential rotation, guardian approval, root authority transfer, and
ownership disputes are **policy evaluations**, not separate identity subsystems.

## Event log, materialized view, runtime decision

Three layers — **never collapse them**.

| Layer | Role | Persisted? |
|-------|------|------------|
| **Event log** | Source of truth | Yes — append-only |
| **Materialized view** | Projection for reads / UI | Yes — recomputable cache |
| **Runtime decision** | Allow rebind or deny | **No** — evaluate per request |

### Event log (source of truth)

Only facts that happened:

```text
recovery_policy.declared
root_authority.declared
credential.bound
credential.revoked
recovery.factor.verified
recovery.completed
passkey.rebound
```

Do **not** emit derived UI state as events:

```text
protection_tier.changed     ← never
recovery_state.changed      ← never
```

When policy weights or factor catalog change, re-run the reducer — do not migrate
millions of stored tier flags.

### Materialized view (projection)

Rebuilt from events + current policy engine version. Safe to cache; safe to discard
and rebuild.

```json
{
  "entity": "sl1e_xxx",
  "protection_tier": "gold",
  "recovery_state": "ready",
  "fulfilled_classes": ["knowledge", "possession"],
  "independent_dimensions_met": 2
}
```

`protection_tier` and `recovery_state` are **UI projections** — never write them
into the domain entity or event log as source of truth.

Anti-pattern on entity row:

```json
{
  "protection_tier": "gold",
  "recovery_complete": true
}
```

### Runtime decision (not stored)

Each recovery (or rotation, transfer, dispute) session:

```text
Policy  +  Active Factors  +  Session Evidence
              ↓
         Policy Engine
              ↓
      Allow rebind  |  Deny
```

The outcome may produce history (`recovery.completed`, `passkey.rebound`). The
evaluation itself is ephemeral.

**Implementation priority:** event log + materialized view reducer **before** new
recovery factor types. Schema v1 is sufficient; the reducer is the next architectural
risk if done wrong.

## Context

Simple L1 already separates identity continuity from credential mechanics:

```text
Passkey -> Identity (sl1e_) -> Proof -> Application Session
```

This is stronger than most web3 identity stacks because `sl1e_...` is not the
passkey, not the username, and not the application session. Credentials can
evolve without rewriting marketplace relying-party contracts.

Two mistakes must be avoided:

1. **Premature user-sovereignty in v1.** Full self-sovereign root from day one
   causes support collapse: most users lose access before they understand key
   custody.
2. **Provider-sovereign without an exit path.** If the model says "identity
   belongs to the provider", a later move to user root requires a painful
   migration of subject identifiers, proofs, and commerce history.

The hard design question is not daily login. It is:

> Who has the right to re-issue credential bindings after all everyday
> credentials are lost?

That answer defines whether the system is provider-trust, hybrid, or
user-sovereign. The answer is expressed as **recovery policy**, not as a fixed
credential checklist.

## Decision

Adopt a **phased sovereignty roadmap**. Ship **Provider Sovereign v1** now.
Design **Sovereign-ready** abstractions immediately. Defer **User Root** until
after network launch and real recovery incidents inform policy.

### Stable objects (do not change across phases)

| Object | Role |
|--------|------|
| `sl1e_...` | Stable identity subject / entity continuity |
| `sl1_...` | Passkey or other control key proof |
| `@username` | User-facing projection; never WebAuthn primary label |
| SL1 proof | Authority artifact consumed by relying parties |
| Marketplace | Relying party only; never credential or recovery authority |

### Protection tiers (policy fulfillment, not credential checklist)

User-facing tiers are derived from **how much recovery policy is satisfied**,
not from a fixed set of credential types. UI labels stay stable as the factor
catalog grows.

| Tier | Policy fulfillment | User meaning |
|------|-------------------|--------------|
| **Bronze** | Recovery unavailable | Daily login only; no self-service recovery |
| **Silver** | Recovery available (single factor) | One recovery factor registered; policy **incomplete** |
| **Gold** | Recovery available (independent multi-factor) | Full recovery policy satisfied |

Copy example: *"Your vault protection: Gold"*.

**Why not tie Gold to "code + recovery passkey"?**

That pairing is convenient for v1, but becomes a ceiling within a year. These
are equally valid Gold policies once the engine supports them:

```text
Daily passkey + recovery code + recovery passkey   ← v1 default ceremony
Daily passkey + hardware security key                ← possession + possession*
Daily passkey + Root Recovery Key                    ← Phase 3
Daily passkey + recovery code + guardian             ← hybrid N-of-M
```

\* Two possession factors count only if they satisfy **independence dimensions**
(below) — not merely because they are different WebAuthn credentials.

Bronze and Silver are valid launch states. Marketplace may nudge upgrade without
blocking basic Safe use at Bronze.

### Phase 1 — Provider Sovereign (v1, now)

**Root authority:** Identity Provider — expressed as a **Recovery Policy**, not a
special "forgot password" flow. Identity Root Authority is the **rule set** that
decides which factors must be satisfied before credentials are re-bound.

**Core rule:** recovery requires **N independent factors** from distinct factor
classes (and, for possession-heavy policies, sufficient **independence
dimensions**). Adding Guardian, Root Recovery Key, or hardware token later means
registering another factor in the same policy engine — not a new recovery
mechanism.

#### Factor classes

| Class | Meaning | v1 examples | Future examples |
|-------|---------|-------------|-----------------|
| `knowledge` | Something the user knows or stores offline | Recovery code | — |
| `possession` | Something the user holds | Recovery passkey | Hardware security key |
| `social` | Third-party attestation | — (deferred) | Guardian |
| `root` | User-held ultimate re-bind authority | — (Phase 3) | Root Recovery Key |

Two factors count as independent only if they belong to **different classes**
**and** (when both are possession) satisfy **independence dimensions** (below).

#### Independence dimensions (not binary)

"Different device" is necessary but not sufficient. Independence is evaluated
across **dimensions**; a factor binding records which dimensions it contributes.

| Dimension | Question | Example |
|-----------|----------|---------|
| **Device** | Different physical device? | iPhone vs MacBook |
| **Ecosystem** | Different platform / sync boundary? | Apple vs Android |
| **Custody** | Different holder or trust boundary? | User device vs trusted person |

**Gold multi-factor policy (v1 rule of thumb):** recovery requires factors that
together span **≥ 2 independent dimensions**, not merely "another passkey on
another device."

Counter-example — looks independent, is not:

```text
Primary:   iPhone (iCloud)
Recovery:  MacBook (iCloud)
```

Physically different devices (**device** ✓), but one Apple ID loss destroys
both (**ecosystem** ✗). Policy SHOULD warn or reject unless a third dimension
(e.g. **knowledge** recovery code, or **custody** guardian) is also satisfied.

Possession factors MUST record binding metadata:

```json
{
  "purpose": "daily | recovery",
  "device_id": "...",
  "ecosystem": "apple | google | microsoft | ...",
  "custody": "self | delegated"
}
```

Independence evaluation (conceptual):

```text
independent_dimensions(factor_a, factor_b) =
  count of { device, ecosystem, custody } where values differ

Gold possession pair requires independent_dimensions >= 2
  OR possession + knowledge (different classes)
  OR policy-specific N-of-M rule
```

If independence checks fail, the credential MUST NOT count toward recovery
policy completion. The provider MAY warn and refuse to mark the factor as
satisfied.

#### Do not bundle recovery passkey into Step 1

**Do not** issue recovery passkey automatically in the same enrollment moment as
the daily passkey. That creates a UX trap:

```text
iPhone
 ├─ daily passkey
 └─ recovery passkey   ← looks like 2 factors, behaves like 1
```

#### v1 reference ceremony (one path to Gold, not the only path)

The recommended v1 onboarding sequence — **a ceremony, not the tier definition**:

```text
Step 1  Create daily passkey          → Bronze (recovery unavailable)
Step 2  Generate recovery code        → Silver (single knowledge factor)
Step 3  Register recovery passkey     → Gold (knowledge + independent possession)
        (independence dimensions enforced)
```

Until multi-factor policy is satisfied — **materialized view** (not entity row):

```json
{
  "entity": "sl1e_...",
  "recovery_state": "incomplete",
  "protection_tier": "silver",
  "fulfilled_classes": ["knowledge"],
  "recovery_policy": {
    "version": 1,
    "rule": "all",
    "required_factor_classes": ["knowledge", "possession"],
    "minimum_independent_dimensions": 2
  },
  "factors": [
    { "id": "...", "class": "knowledge", "type": "recovery_code", "status": "active" }
  ]
}
```

After policy fully satisfied — **materialized view**:

```json
{
  "entity": "sl1e_...",
  "recovery_state": "ready",
  "protection_tier": "gold",
  "fulfilled_classes": ["knowledge", "possession"],
  "independent_dimensions_met": 2,
  "recovery_policy": {
    "version": 1,
    "rule": "all",
    "required_factor_classes": ["knowledge", "possession"],
    "minimum_independent_dimensions": 2
  },
  "factors": [
    { "id": "...", "class": "knowledge", "type": "recovery_code", "status": "active" },
    { "id": "...", "class": "possession", "type": "passkey", "purpose": "recovery", "status": "active" }
  ]
}
```

Canonical schema: `docs/recovery-policy-schema.md` and
`docs/schemas/recovery-policy-v1.schema.json`.

Steps MAY be separated in time and UI. Step 3 MUST NOT be silently bundled into
Step 1. A future user MAY reach Gold via a different factor set if policy
allows it.

#### Recovery code (knowledge factor — v1 default)

- Human-readable, e.g. `MEANLY-R7F4-29KP-...`
- Shown **once** at Step 2
- Server stores **hash only**
- Verifiable alone for factor proof, but **insufficient** to complete recovery
  until full multi-factor policy is satisfied

#### Recovery passkey (possession factor — v1 default for Step 3)

- Registered in **Step 3**, not auto-created at Step 1
- Tagged `purpose: recovery` with device / ecosystem / custody metadata
- User instruction: *Save this key on another device or ecosystem*
- WebAuthn label e.g. `Meanly Recovery · @username`
- Independence dimensions enforced before factor counts toward Gold

#### Daily passkeys

- Primary login; may sync within one ecosystem
- Cross-device QR handoff = login/register **transport**, not recovery authority

#### WebAuthn presentation

- Daily credentials: `@username`, `Meanly · @username` — never `sl1e_...`

#### v1 recovery session (Gold only)

When `recovery_state = ready`, re-bind after loss of daily passkeys requires
**policy satisfaction in one recovery session**. For the default v1 policy,
evidence for every `required_factor_class` must be collected; verification
creates **evidence**, not policy mutation:

```text
recovery.factor.verified  → evidence (knowledge)
recovery.factor.verified  → evidence (possession)
recovery.completed        → engine: sufficient evidence + independence
passkey.rebound
```

Stealing only the paper or only the recovery device is insufficient.

**Explicitly deferred from v1:**

- Guardian / social recovery (`social` class)
- Root Recovery Key (`root` class)
- Seed-phrase UX
- Provider-less recovery
- Auto-issuing recovery passkey during Step 1
- Hardware security key as alternate Gold path (engine-ready; UX later)

### Phase 2 — Sovereign-ready (parallel design, no user-facing launch)

Introduce **Identity Root Authority** as an explicit abstraction in the
Identity Layer. Do not encode "identity belongs to provider" in schemas,
events, or public copy.

Use governance language:

```text
identity currently governed by provider authority
```

not:

```text
identity belongs to provider
```

Each `sl1e_` entity is governed by events; marketplace reads a **materialized
view** projection (example):

```json
{
  "entity": "sl1e_...",
  "root_authority": {
    "mode": "provider",
    "governed_by": "simple-layer-identity",
    "since": "2026-06-19T00:00:00Z"
  },
  "protection_tier": "gold",
  "recovery_state": "ready",
  "fulfilled_classes": ["knowledge", "possession"],
  "independent_dimensions_met": 2,
  "computed_at": "2026-06-19T00:00:00Z",
  "engine_version": "policy-v1"
}
```

Current policy (from latest `recovery_policy.declared` event):

```json
{
  "version": 1,
  "rule": "all",
  "required_factor_classes": ["knowledge", "possession"],
  "minimum_independent_dimensions": 2,
  "independence_dimensions": ["device", "ecosystem", "custody"]
}
```

Future modes: `provider`, `hybrid`, `user_root`.

Future policy shapes (same engine, richer factor set — tiers unchanged):

```text
require 2 of 3 factors
require recovery_key OR (2 guardians + recovery_code)
require daily_passkey + hardware_key   → Gold via possession + possession*
require daily_passkey + root_key       → Gold via root class
```

Guardian, Root Recovery Key, and hardware token register as additional factors
in the **same policy engine** — not replacements for the v1 reference ceremony.

Relying parties and marketplace **read** authority mode and protection tier for
policy and support routing. They do not implement recovery or evaluate policy.

**Event vocabulary (Identity Layer, future-proof):**

| Event | Meaning |
|-------|---------|
| `credential.bound` | Passkey or factor bound (`purpose`, device/ecosystem/custody metadata) |
| `credential.revoked` | Binding removed |
| `recovery_policy.declared` | Policy rules recorded for entity |
| `recovery_code.issued` | Knowledge factor registered |
| `recovery.factor.rejected` | Factor failed independence policy (e.g. insufficient dimensions) |
| `recovery.factor.verified` | One factor proved in session → **evidence** appended |
| `recovery.completed` | Session outcome: policy satisfied; re-bind permitted |
| `recovery_code.consumed` | Knowledge factor spent as part of `recovery.completed` |
| `passkey.rebound` | New daily passkey bound after successful recovery |
| `root_authority.declared` | Authority mode set or migrated |

**Not events** (derive in materialized view): `protection_tier`,
`recovery_state`, `fulfilled_classes`, `independent_dimensions_met`.

Recovery sessions MUST NOT emit `recovery.completed` until the runtime policy
engine passes against **session evidence** and enrolled factors (every
`required_factor_class` verified, independence dimensions satisfied).

These align with the claims model and `docs/recovery-policy-schema.md`:

```text
Node     → Identity (sl1e_)
Edge     → Policy (recovery_policy)
Factor   → Credential binding
Evidence → Session proof
History  → Events (append-only)
```

Recovery is Identity Layer history, not marketplace session state.

### Phase 3 — User Root (post-launch, optional per user)

Extend the factor catalog without breaking tiers or `sl1e_`:

```text
Identity (sl1e_)
 ├─ Recovery Policy          ← governs all factors below
 ├─ Daily passkey(s)         ← everyday possession
 ├─ Recovery code            ← knowledge (v1 default)
 ├─ Recovery passkey         ← possession (v1 default)
 ├─ Hardware security key    ← alternate possession path to Gold
 ├─ Guardian(s)              ← social class; hybrid mode
 └─ Root Recovery Key        ← root class; strongest factor, catastrophic use
```

When `root_authority.mode = hybrid`, recovery policy may become **N-of-M**
(e.g. any 2 of: recovery code, recovery passkey, guardian attestation, root
key signature). Guardian and Root Recovery Key are **new factors in policy**,
not replacements for knowledge + possession v1.

**Root Recovery Key properties (Phase 3):**

- Not for daily login.
- Not shown in Touch ID / Passwords as primary identity.
- Cold, rare-use factor to authorize re-bind after total credential loss.
- **Strongest factor in recovery policy** — not owner of `sl1e_`.
- Cryptographic form: e.g. Ed25519 key pair, or a future native SL1 credential
  type — **not** a BIP-39 seed phrase UX.
- User holds private material; provider stores only public key / policy hash.

A user with daily passkey + Root Recovery Key alone may reach **Gold** if
policy declares two independent factors across classes/dimensions — without
ever enrolling a recovery code.

**Authority migration:**

```text
2026  provider authority
2027  hybrid (provider + root key)
2028+ user authority (root key) for opted-in identities
```

`sl1e_`, existing passkeys, proofs, and marketplace contracts remain valid.
Only `root_authority.mode` and recovery policy change.

## Identity Root Authority (abstract contract)

```text
Identity Root Authority
  = governance over who may re-establish control of sl1e_
    after loss of everyday credentials

Recovery Policy
  = the concrete rule set evaluated by that authority:
    which factor classes, how many, independence dimensions,
    N-of-M logic, and strength thresholds

Credentials
  = passkeys, codes, guardians, root keys — factors that satisfy policy

Policy / Factors / Evidence (schema v1)
  = Policy: what is required
    Factors: what exists on the entity (with strength_weight)
    Evidence: what was verified in a recovery session

Materialized view
  = projection (protection_tier, recovery_state, fulfilled_classes)
    rebuilt from event log; never source of truth
```

Identity Root Authority is **not** a single key. It is the policy engine.
Factors are inputs; session evidence is accumulated;
`recovery.completed` records a session outcome. **Protection tier** is computed
for UI only — Gold = policy satisfied + independence ≥ 2 (for default v1 policy).

| Mode | Root authority | Recovery policy shape |
|------|----------------|------------------------|
| `provider` | Identity Provider evaluates policy | Gold: multi-factor with ≥ 2 independence dimensions |
| `hybrid` | Provider + optional user factors | N-of-M; guardian, root key as extra factors |
| `user_root` | Root Recovery Key may override or co-sign policy | Provider verifies; cannot unilaterally re-bind |

Marketplace and commerce layers MUST NOT become root authority.

## Relationship to ADR 0023

ADR 0023 assigns **credential ownership** to the Identity Layer and makes
applications relying parties. This ADR assigns **root re-bind authority** within
the Identity Layer and schedules its evolution.

```text
Recovery Policy           = rule set; governs identity continuity
Factor class              = knowledge | possession | social | root
Independence dimension    = device | ecosystem | custody
protection_tier           = bronze | silver | gold (materialized view only)
recovery_state            = unavailable | incomplete | ready (materialized view only)
Daily passkey             = everyday possession; Bronze baseline
Recovery code             = knowledge factor (v1 default Silver→Gold path)
Recovery passkey          = possession factor (v1 default Gold path)
Hardware key / Root key   = alternate factors; same tier engine
Guardian                  = future social factor (hybrid)
Proof                     = authority artifact for relying parties
Application               = session consumer only; may show protection tier
```

## Consequences

**Positive**

- v1 ships with safe UX and explicit recovery story.
- Tiers stay stable while factor catalog and policies evolve.
- Independence model matches real-world correlated loss (shared Apple ID, etc.).
- Sovereign evolution does not fork `sl1e_` or rewrite marketplace auth.
- Root Recovery Key slots in as policy factor, not identity owner.
- Governance layer enables provider → user-root without forking `sl1e_`.
- Materialized view can be rebuilt when policy engine or weights change.
- Event log stays small and permanent; no tier migration campaigns.

**Costs**

- Identity Provider must implement policy engine, dimension checks, and audit events.
- Tier derivation is computed, not hard-coded to credential names.
- Hybrid and user-root modes require key ceremony UX and support playbooks later.
- `root_authority` must be introspectable for compliance and user transparency.

## Follow-up

**Design freeze** — no new ADRs or domain concepts until Phase B (dual replay on
real stream). See `docs/governance-reducer-invariants.md` (Phases A–D,
Invariant 9, genesis rule).

1. **Phase A** — append-only stream + `IdentityGovernanceStreamAppendRules`
2. **Phase B** — dual replay on persisted stream
3. **Phase C** — snapshot persistence on real log
4. **Phase D** — recovery enrollment as event producers only

## References

- ADR 0023: Identity Authority and Credential Ownership
- `docs/governance-reducer-invariants.md` — replay determinism, snapshot property tests
- `docs/identity-governance-event-vocabulary.md` — canonical log vocabulary, registry boundary
- `docs/recovery-policy-schema.md` — Policy / Factors / Evidence JSON schema (v1)
- `docs/schemas/recovery-policy-v1.schema.json` — machine-readable schema
- `docs/identity-claims-model.md` — recovery listed as unverified until events
  force the model; Phase 1 events satisfy that gate for provider recovery only
