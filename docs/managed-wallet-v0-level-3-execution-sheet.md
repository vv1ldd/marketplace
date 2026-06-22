# Managed Wallet v0 — Level 3 Staging Execution Sheet

**Format:** Action → Observable fact → PASS / FAIL  
**Companion:** [`managed-wallet-v0-level-3-staging-drill.md`](managed-wallet-v0-level-3-staging-drill.md)

Fill one row per step. Any FAIL blocks Level 3 sign-off.

**Level 3 question (one sentence):**

> After destroying temporary state, does the system still know this is the **same subject**
> with the **same settlement capability**?

This is not “does Safe work?” — it is **prove continuity of ownership across failure surfaces**.

---

## Rollout phase map

```text
Phase A   Identity correctness           ✓  (CI Level 1)
Phase B   Binding durability             ✓  (CI Level 1 replay)
Phase C   Managed settlement surface     ✓  (implementation + presentation)
Level 3   Recovery proof                 pending → PASS | FAIL
```

On Level 3 PASS, managed Safe is proven as an **identity-controlled settlement instrument**,
not a “new wallet product”.

---

## Continuity evidence capture (primary artifact)

Record **BEFORE** after Scenario B + D (binding + USDC observed). Re-check **AFTER** each
destructive step and at final sign-off.

```text
BEFORE (baseline)
─────────────────
identity.entity_l1_address = A
vault.id                   = V
active Polygon binding.id  = B
settlement address         = S
observed USDC              = U
accounting path            = (note: no binding_source branch)

DRILL (attack surfaces)
───────────────────────
□ logout
□ cache clear (application)
□ browser clear (local storage / session)
□ projection rebuild (if supported)
□ frontend redeploy
□ re-auth

AFTER (must match BEFORE)
─────────────────────────
identity.entity_l1_address = A   ☐
vault.id                   = V   ☐
active Polygon binding.id  = B   ☐
settlement address         = S   ☐
observed USDC              = U   ☐  (U' may differ if drill includes on-chain tx; must match chain for S)
accounting path            = same ☐

Level 3 = PASS only if A/V/B/S match and U shows observation continuity (see U rule in Evidence record).
```

### Degradation signals (auto-FAIL)

These often look “green” in UI but break continuity:

```text
identity A  →  new vault V2         ❌
identity A  →  new binding B2       ❌
identity A  →  admin repair required  ❌
```

Requirement: **recover existing truth** — not recreate something similar.

---

## Sign-off header

| Field | Value |
|-------|-------|
| Environment | staging URL |
| Operator | |
| Date (UTC) | |
| Git / deploy ref (backend) | |
| Git / deploy ref (frontend) | |
| Polygon RPC provider | |
| USDC tx hash (drill) | |

---

## Preconditions

| Check | Expected | PASS | FAIL |
|-------|----------|------|------|
| Environment is staging (not prod) | isolated drill tenant | ☐ | ☐ |
| `MANAGED_WALLETS_ENABLED=true` | config / capabilities | ☐ | ☐ |
| `COMMERCE_CRYPTO_RAILS_ENABLED=true` | config / capabilities | ☐ | ☐ |
| `SETTLEMENT_ADAPTER_POLYGON_ENABLED=true` | adapter on | ☐ | ☐ |
| `POLYGON_RPC_ENABLED=true` + valid RPC URL | observation works | ☐ | ☐ |
| Observer has DB read access | `identity_bindings`, `vault_identities` | ☐ | ☐ |
| Observer has API log access | storefront vault routes | ☐ | ☐ |
| Chain explorer / RPC available | Polygon mainnet | ☐ | ☐ |
| Drill user has **no** active Polygon binding | `GET …/wallet/bindings` → `items: []` | ☐ | ☐ |

**Capabilities check (API):**

```http
GET /api/storefront/v1/wallet
Authorization: Bearer <vault_token>
```

| Observable fact | Expected value |
|-----------------|----------------|
| `capabilities.managed_wallets_enabled` | `true` |
| `capabilities.can_provision_managed_wallet` | `true` |
| `capabilities.crypto_rails_enabled` | `true` |

---

## Capture template (use for all scenarios)

Record once after first binding, reuse for destructive drill:

| Field | Step 1 value | After drill value | Match? |
|-------|--------------|-------------------|--------|
| `entity_l1_address` | | | ☐ |
| `vault.id` | | | ☐ |
| `binding.id` | | | ☐ |
| `binding.binding_source` | | | ☐ |
| `binding.binding_key` | `polygon` | | ☐ |
| `binding.binding_value` (address) | | | ☐ |
| `binding.verification_method` | | | ☐ |
| Observed USDC (`wallet/assets`) | | | ☐ |
| On-chain USDC (explorer) | | | ☐ |

---

## Scenario A — New identity → Safe-first

### Action

1. Create a **new** identity (fresh user / vault).
2. Open `/vault`.

### Observable facts

**UI must show:**

- `Your Vault` / provisioning shell
- Identity line (e.g. `@username`)
- `Identity: Durable`
- `Create Safe` (primary CTA)
- `Connect existing →` (secondary)

**UI must NOT show on landing:**

- Wall of network cards each with `Connect`
- MetaMask / WalletConnect / Tonkeeper as primary onboarding

**API:**

```http
GET /api/storefront/v1/wallet/bindings
```

| Observable fact | Expected |
|-----------------|----------|
| `items` length | `0` (no Polygon binding yet) |

**DB:**

```sql
-- vault_id from GET /wallet → vault.id
SELECT id, binding_key, binding_source, verification_state
FROM identity_bindings
WHERE vault_id = '<vault_id>' AND binding_key = 'polygon'
  AND verification_state != 'revoked';
```

| Observable fact | Expected |
|-----------------|----------|
| Row count | `0` |

### Verdict

| | |
|-|-|
| **PASS** | Safe-first shell; no primary settlement binding in API/DB |
| **FAIL** | Network-first connect wall; or binding exists before user action |

---

## Scenario B — Managed Safe provisioning

### Action

1. From Scenario A state, click **Create Safe**.

### Capture — API

```http
POST /api/storefront/v1/wallet/bindings/managed
Authorization: Bearer <vault_token>
Content-Type: application/json

{ "binding_key": "polygon" }
```

**Expected response `201`:**

```json
{
  "success": true,
  "binding": {
    "id": "<binding_id>",
    "vault_id": "<vault_id>",
    "binding_type": "wallet",
    "binding_key": "polygon",
    "binding_source": "managed",
    "binding_value": "0x...",
    "verification_state": "verified",
    "verification_method": "vault_key"
  }
}
```

| Observable fact | Expected |
|-----------------|----------|
| HTTP status | `201` |
| `binding.binding_source` | `managed` |
| `binding.verification_method` | `vault_key` |
| `binding.verification_state` | `verified` |
| `binding.binding_key` | `polygon` |

**DB:**

| Observable fact | Expected |
|-----------------|----------|
| `identity_bindings.binding_source` | `managed` |
| `identity_bindings.verification_method` | `vault_key` |
| `vault_managed_wallet_keys` row | exists for `vault_id` + `identity_binding_id` |

**Chain (optional sanity):**

| Observable fact | Expected |
|-----------------|----------|
| Address format | valid `0x` EVM address |
| Explorer | address exists (may be empty balance) |

### Observable facts — UI

| Observable fact | Expected |
|-----------------|----------|
| Screen mode | Vault **dashboard** (`My Safe`), not provisioning shell |
| Address shown | same as `binding.binding_value` |
| Status | Connected · Identity bound |
| Receive / QR | available for Polygon address |

### Verdict

| | |
|-|-|
| **PASS** | Same identity + new `binding.id` + managed source + dashboard |
| **FAIL** | Wrong `binding_source`; duplicate binding; UI still provisioning shell |

**Record capture template** — all fields from this scenario.

---

## Scenario C — External wallet ingress

Use a **separate** clean identity (no Polygon binding).

### Action

1. Open vault → **Connect existing →** → Polygon.
2. Complete challenge / signature flow.

### Capture — API sequence

```http
POST /api/storefront/v1/wallet/bindings/challenge
POST /api/storefront/v1/wallet/bindings/verify
```

**Expected final binding (from verify response or `GET …/bindings`):**

| Observable fact | Expected |
|-----------------|----------|
| `binding.binding_source` | `external` |
| `binding.verification_method` | `signature` |
| `binding.verification_state` | `verified` |
| `binding.binding_key` | `polygon` |

### Observable facts — UI convergence

| Observable fact | Expected |
|-----------------|----------|
| Dashboard | **Same** vault dashboard as Scenario B (`My Safe`) |
| Layout | Receive + Assets + collapsed Networks |
| Must NOT show | Separate “wallet app” dashboard or binding-source-specific balance UI |

### Verdict

| | |
|-|-|
| **PASS** | `binding_source=external` but same dashboard + same observation API path |
| **FAIL** | Different dashboard shell; settlement/accounting branches on `binding_source` |

---

## Scenario D — USDC observation (real)

Continue from Scenario B (or C) after binding exists.

### Action

1. Send small real USDC on Polygon to the bound address.
2. Wait for observation pipeline.
3. Refresh assets.

```http
GET /api/storefront/v1/wallet/assets
```

| Observable fact | Expected |
|-----------------|----------|
| `network_wallets` | includes Polygon entry for bound address |
| USDC `display_amount` | matches sent amount (± fees / rounding) |
| Explorer balance | matches observed balance |

Record: tx hash, amount sent, first observed balance, timestamp.

### Verdict

| | |
|-|-|
| **PASS** | Observed balance present and matches chain |
| **FAIL** | Missing observation; wrong address; stale zero with non-zero chain |

---

## Level 3 — Destructive durability drill

Use **Scenario B** identity (managed) with USDC sent (Scenario D).  
Re-check capture template after **each** step.

### Step 1 — Logout

| Action | Observable fact | PASS | FAIL |
|--------|-----------------|------|------|
| Sign out completely | Session cleared | ☐ | ☐ |
| Re-authenticate | `entity_l1_address` unchanged | ☐ | ☐ |
| `GET …/wallet/bindings` | same `binding.id`, same address | ☐ | ☐ |
| `GET …/wallet/assets` | USDC observation still present | ☐ | ☐ |

### Step 2 — Cache flush (application)

| Action | Observable fact | PASS | FAIL |
|--------|-----------------|------|------|
| Operator: `php artisan cache:clear` (or env-equivalent) | No manual DB edits | ☐ | ☐ |
| Re-auth + `GET …/bindings` | same `binding.id` | ☐ | ☐ |
| `GET …/assets` | same observed balance | ☐ | ☐ |

### Step 3 — Browser surface cleared

| Action | Observable fact | PASS | FAIL |
|--------|-----------------|------|------|
| Clear local storage / session cookies for vault | User must re-login | ☐ | ☐ |
| Re-auth | same identity + binding restored from backend | ☐ | ☐ |
| UI | dashboard (not provisioning shell) | ☐ | ☐ |

### Step 4 — Projection rebuild (if available in staging)

> Skip only if staging has no supported projection rebuild path. Document skip reason.

| Action | Observable fact | PASS | FAIL |
|--------|-----------------|------|------|
| Destroy disposable projections per ops runbook | Stream / `identity_bindings` untouched | ☐ | ☐ |
| Replay / rebuild | same `binding.id`, same address | ☐ | ☐ |
| Observation | same USDC evidence | ☐ | ☐ |

### Step 5 — Frontend redeploy

| Action | Observable fact | PASS | FAIL |
|--------|-----------------|------|------|
| Deploy new frontend build | Presentation may change | ☐ | ☐ |
| Re-auth | domain facts unchanged (capture template match) | ☐ | ☐ |
| No reprovisioning | `POST …/managed` not required | ☐ | ☐ |

---

## Final PASS statement

Level 3 **PASS** only if **all** hold:

```text
Identity (anchor)
   |
   v
same IdentityBinding (id, binding_source, address)
   |
   v
same settlement address
   |
   v
same observation (USDC)
   |
   v
same accounting path (no binding_source branch)
```

And **no** destructive step required:

- new identity
- new binding
- new address
- admin repair
- manual DB fix

## Final FAIL (anti-tests)

Immediate FAIL if any occur:

| Anti-test | Meaning |
|-----------|---------|
| New `binding.id` after re-auth | Identity / replay broken |
| New address after cache flush | Provisioning / custody leak |
| Binding missing after re-login | Durability contract broken |
| Observation missing with on-chain USDC | RPC / adapter broken |
| Different dashboard for managed vs external | Presentation / domain branch leak |
| Admin repaired state | Drill invalid |

---

## Evidence record (fill after run — sole new artifact)

Copy this block into the drill log. No design doc required on PASS — only these facts.

```text
Managed Wallet v0 — Level 3 Evidence Record
Environment:
Operator:
Date (UTC):

BEFORE
  A  identity.entity_l1_address =
  V  vault.id                   =
  B  active Polygon binding.id  =
  S  settlement address         =
  U  observed USDC              =
     accounting path note       = (no binding_source branch observed: yes/no)

DRILL completed
  logout            yes/no
  cache clear       yes/no
  browser clear     yes/no
  projection rebuild yes/no/skipped
  frontend redeploy yes/no

AFTER (must equal BEFORE)
  A  =
  V  =
  B  =
  S  =
  U  =                    (see U rule below)
     accounting path = same yes/no

U rule (do not misread as “same number only”):
  - If NO new on-chain settlement during DRILL:  U' must equal U
  - If drill includes real USDC send/receive:    U' may differ numerically
  - PASS requires: same subject owns S, observation pipeline still resolves
    the same binding/address, and U' matches on-chain state for S (not a stale
    or reassigned observation)

Equality check (facts only — no interpretation):
  A  == A'   required
  V  == V'   required
  B  == B'   required
  S  == S'   required
  U  ~ U'    U' is valid observation of S' through B' (not balance freeze)

Formal PASS:
  A == A' AND V == V' AND B == B' AND S == S'
  AND U' is valid observation of S' through B'
  (same economic object; U' may differ if on-chain settlement occurred)

Formal FAIL (any):
  new identity | new vault | new binding | manual repair | stale observation

Provisioning answers: "Can we create it?"     ✓ closed
Recovery answers:     "Can we trust it after failure?"  ← Level 3

Degradation signals (any yes = auto-FAIL)
  new vault V2?        yes/no
  new binding B2?      yes/no
  manual admin repair? yes/no

identity:     A matched AFTER  yes/no
vault:        V matched AFTER  yes/no
binding:      B matched AFTER  yes/no
address:      S matched AFTER  yes/no
observation:  U matched AFTER  yes/no
accounting:   same path        yes/no

result:       PASS | FAIL
```

On PASS, the proven statement:

```text
Different provisioning
        ↓
Same identity anchor (A)
        ↓
Same binding continuity (B)
        ↓
Same settlement surface (S)
        ↓
Same observation pipeline (U ~ U')
        ↓
Same accounting path

Vault abstraction survived recovery, not only happy path.
```

Level 3 FAIL means: **system cannot recover existing truth** — not “cannot create a new truth”.

---

## Sign-off

```text
Managed Wallet v0 — Level 3
Result:          PASS | FAIL
Scenarios A/B/C: PASS | FAIL
Scenario D USDC: PASS | FAIL | SKIPPED (reason)
Destructive drill: PASS | FAIL
Manual repair:   NO | YES (auto-FAIL)
Notes:
```

On **PASS** without manual repair:

```text
Staging Proof:       CLOSED
Ready For Rollout:   YES
```
