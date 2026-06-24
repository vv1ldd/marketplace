# Managed Wallet v0 — Level 3 Staging Drill

Operational runbook only. This document is **not** a new layer of truth — it describes how to
prove managed wallet provisioning in a real environment.

**Architecture (read first):** [ADR 0026](adr/0026-settlement-instrument-sovereignty.md) and [ADR 0027](adr/0027-identity-attachments-and-provider-ownership.md) — managed wallet v0 implements **provider-owned instruments** attached to `sl1e_`, not protocol custody on simplelayer.one.

**CI companion (Level 2):** `tests/Feature/ManagedWalletAttachmentOperationalDrillTest.php`  
**Identity companion (Level 1):** `tests/Feature/StorefrontManagedWalletProvisioningTest.php`  
**Execution sheet:** [`managed-wallet-v0-level-3-execution-sheet.md`](managed-wallet-v0-level-3-execution-sheet.md) — action → observable fact → PASS/FAIL  
**Evidence artifact:** [`docs/evidence/level-3-run-playbook.md`](docs/evidence/level-3-run-playbook.md) (operator scenario) · copy [`docs/evidence/level-3-run-TEMPLATE.md`](docs/evidence/level-3-run-TEMPLATE.md) → `docs/evidence/level-3-run-YYYY-MM-DD.md` · capture: `scripts/level3-evidence-capture.sh`  
**Post–Phase 3 backlog:** [Provider / Storefront Independence Drills](docs/evidence/provider-interface-independence-drills.md) — gate before Provider Interface v1 (not normative)

## Current status

```text
Managed Wallet v0
Architecture:        CLOSED
Implementation:      CLOSED
CI Proof:            CLOSED   (Level 1 + Level 2)
Staging Proof:       OPEN     (this runbook)
Production Rollout:  BLOCKED BY STAGING
```

After this drill passes without manual repair, update status to:

```text
Staging Proof:       CLOSED
Ready For Rollout:   YES
```

---

## UI feature matrix (presentation invariant)

Vault shell must not infer wallet state from UI chrome alone — use the same binding
facts as the API. Presentation helpers live in `frontend/lib/identity-wallets.js`.

| Primary settlement binding | `managed_wallets_enabled` | Vault UI mode |
|----------------------------|---------------------------|---------------|
| absent | `false` | Provisioning shell (identity-first; legacy connect behind **Connect existing**) |
| absent | `true` | Provisioning shell (`Create Safe` primary) |
| present (managed or external) | `false` / `true` | Safe dashboard (`My Safe` + collapsed networks) |

**Presentation helpers:**

- `hasPrimarySettlementBinding(model)` — any connected or pending settlement instrument (any rail)
- `shouldShowSafeProvisioningShell(model, 'vault')` — no primary binding (always Safe-first, never a wall of Connect cards)
- `shouldShowSafeDashboard(model, 'vault')` — primary binding exists

**Forbidden drift:** binding exists in API but UI shows welcome (or vice versa). If that
happens, fix presentation logic — not settlement or accounting.

---

## Presentation verification (post-deploy)

After frontend deploy and `MANAGED_WALLETS_ENABLED` toggle, confirm three paths share
one dashboard — no separate accounting or settlement branch:

| # | Scenario | Expected UI |
|---|----------|-------------|
| 1 | New identity, no binding | Safe-first provisioning shell (not a wall of Connect cards) |
| 2 | Managed enabled → Create Safe | `IdentityBinding(managed)` → vault dashboard (`My Safe`) |
| 3 | Connect existing (external) | Same dashboard; `binding_source=external` invisible to user balances |

State machine (presentation):

```text
IDENTITY_CREATED
        |
        v
NO_SETTLEMENT_BINDING  →  provisioning shell
        |
        +------------------+------------------+
        |                                     |
        v                                     v
   CREATE_SAFE (managed)              CONNECT_EXISTING (external)
        |                                     |
        +------------------+------------------+
                           |
                           v
              PRIMARY_SETTLEMENT_BINDING
                           |
                           v
                    VAULT DASHBOARD
```

Networks are vault **capabilities**, not onboarding steps.

Presentation verification proves **user-facing contract correctness**. Level 3 (below) proves
**durability contract correctness** — a separate gate.

---

## Objective (Level 3 — durability)

Prove one statement:

> **Managed wallet provisioning preserves observations across a real durability cycle.**

Level 3 is **not** a UI test. Architecture and presentation are already closed. This drill
asks whether the identity → Safe → settlement chain survives reality.

### Primary durability invariant

```text
Safe address is NOT the source of identity.
Identity survives the settlement surface.
```

The test is **not** “did the Safe UI persist?” It is:

> After logout, cache flush, and projection rebuild, can staging still resolve the **same
> primary settlement binding** and the **same observation**?

Different provisioning (managed vs external) must converge on:

```text
Different provisioning
        |
        v
Same settlement identity (IdentityBinding)
        |
        v
Same observation + accounting path
```

### Verification chain

```text
identity (VaultIdentity / sl1e anchor)
        ↓
IdentityBinding(managed)   ← durable record, not UI state
        ↓
Safe address (settlement surface)
        ↓
balance observation (RPC / adapter)
        ↓
USDC evidence (observed balance matches on-chain)
```

After re-login and cache flush, every layer above must reconcile to the values recorded at
Step 1 — without manual admin repair.

The question is no longer *“Can managed wallets fit the architecture?”* but *“Can staging
demonstrate the architecture under a real durability cycle?”*

---

## Environment

### Required flags

| Variable | Value |
|----------|-------|
| `COMMERCE_CRYPTO_RAILS_ENABLED` | `true` |
| `MANAGED_WALLETS_ENABLED` | `true` |
| `MANAGED_WALLET_POLYGON_ENABLED` | `true` (default when unset) |
| `SETTLEMENT_ADAPTER_POLYGON_ENABLED` | `true` |
| `SETTLEMENT_ADAPTER_POLYGON_MODE` | `read_only` (minimum for observation) |
| `POLYGON_RPC_ENABLED` | `true` |
| `POLYGON_RPC_URL` | valid Polygon mainnet RPC endpoint |

Fill before drill:

| Parameter | Staging value |
|-----------|---------------|
| Environment URL | |
| Operator | |
| Drill date (UTC) | |
| Polygon RPC provider | |
| USDC contract (Polygon) | `0x3c499c542cef5e3811e1192ce70d8cc03d5c3359` (native USDC) |

### API surface (storefront vault token required)

| Endpoint | Purpose |
|----------|---------|
| `GET /api/storefront/v1/wallet` | identity, vault id, capabilities |
| `GET /api/storefront/v1/wallet/bindings` | binding list |
| `POST /api/storefront/v1/wallet/bindings/managed` | Create Safe ingress |
| `GET /api/storefront/v1/wallet/assets` | USDC observation (network wallets) |

---

## Preconditions

- [ ] User account exists and can complete normal identity / vault auth flow.
- [ ] User has **no** active Polygon wallet binding (`GET …/wallet/bindings` → no `polygon` item).
- [ ] Wallet summary reports `capabilities.managed_wallets_enabled = true`.
- [ ] Wallet summary reports `capabilities.can_provision_managed_wallet = true`.
- [ ] No USDC balance observation exists yet for the target address (fresh provision).
- [ ] Small real USDC amount available for transfer (recommend ≥ 0.01 USDC).

---

## Drill record (fill during run)

| Field | Step 1 | After Step 8 |
|-------|--------|--------------|
| `entity_l1_address` (identity) | | |
| `vault.id` | | |
| `binding.id` | | |
| `binding.binding_source` | `managed` | `managed` |
| `binding.verification_method` | `vault_key` | `vault_key` |
| Polygon address | | |
| USDC tx hash | | |
| USDC amount sent | | |
| Observed balance (first) | | |
| Observed balance (final) | | |
| Observation timestamp (first) | | |
| Observation timestamp (final) | | |

---

## Procedure

### Step 1 — Create Safe

**Action:** User clicks **Create Safe** (Polygon) in vault wallet UI.

**Or API:**

```http
POST /api/storefront/v1/wallet/bindings/managed
Authorization: Bearer <vault_token>
Content-Type: application/json

{ "binding_key": "polygon" }
```

**Expected:**

- Managed Polygon address provisioned
- `IdentityBinding` created — **not** a `ManagedWallet` root entity
- `binding_source = managed`
- `verification_method = vault_key`
- `verification_state = verified`

**Record:** `entity_l1_address`, `vault.id`, `binding.id`, address

---

### Step 2 — Receive real USDC

**Action:** Transfer a small amount of real USDC on Polygon to the provisioned address.

**Record:** tx hash, amount, address (must match Step 1)

---

### Step 3 — Observe balance

**Action:** Wait for observation pipeline; refresh wallet assets.

```http
GET /api/storefront/v1/wallet/assets
Authorization: Bearer <vault_token>
```

**Expected:**

- Address detected in `network_wallets` for Polygon
- USDC balance observed (`coins` entry with `symbol: USDC`)
- Balance visible in UI and/or API

**Record:** observed balance, observation timestamp

---

### Step 4 — Logout

**Action:** Terminate session completely (clear storefront session / vault token).

**Expected:**

- No identity loss
- No binding mutation in database

---

### Step 5 — Re-login

**Action:** Authenticate again using the normal identity flow.

**Verify:**

```http
GET /api/storefront/v1/wallet
GET /api/storefront/v1/wallet/bindings
```

**Expected — unchanged:**

- `entity_l1_address`
- `binding.id`
- Polygon address

---

### Step 6 — Cache flush

**Action:** Flush application caches on staging (operator action — not user-facing).

```bash
php artisan cache:clear
```

Use deployment-appropriate cache flush if `cache:clear` is restricted. Do **not** mutate
`identity_bindings` or `vault_managed_wallet_keys` manually.

**Expected:** No data regeneration; bindings remain in database.

---

### Step 7 — Re-authenticate

**Action:** Obtain a new vault token after cache flush; call wallet endpoints again.

**Expected — unchanged:**

- `entity_l1_address`
- `binding.id`
- Polygon address

---

### Step 8 — Re-observe

**Action:** Query wallet summary, bindings, and assets.

**Expected — unchanged:**

- address
- binding (`id`, `binding_source`, `verification_method`)
- observed USDC balance (consistent with on-chain state after Step 2)

---

## PASS criteria

All must hold:

- [ ] **Identity unchanged** (`entity_l1_address`, `vault.id`) — identity is the anchor
- [ ] **Primary settlement binding unchanged** (`binding.id`, `binding_source`, address) — not a new binding or address
- [ ] **Observed balance present** after re-login and after cache flush
- [ ] **Observed balance matches** on-chain USDC for that address
- [ ] **No duplicate bindings**, no reprovisioning, no manual admin repair

The Safe address is a settlement surface derived from identity — not a second root. PASS means
the binding and observation survive the cycle; FAIL means durability contract broke somewhere in
the verification chain above.

**Success statement:**

> Managed wallet provisioning preserves observations across a real durability cycle.

---

## FAIL criteria

Any of the following is an immediate FAIL:

| Failure | |
|---------|---|
| New binding created | |
| New address created | |
| Binding missing after re-login | |
| Binding missing after cache flush | |
| Observed balance missing | |
| Observed balance inconsistent with on-chain state | |
| Unexpected reprovisioning | |
| Manual admin repair required to restore state | |

---

## Diagnostic mapping

If CI (Level 2) passes but staging fails, use this triage — blame is likely **outside** the
domain model:

| Symptom | Probable layer |
|---------|----------------|
| New binding | Identity / replay |
| New address | Provisioning / custody |
| Missing balance | RPC / observation |
| Missing settlement proof | Settlement adapter |
| Missing accounting effect | CreditDecision / accounting |

Settlement and accounting must **not** branch on `binding_source`. If failure appears only after
real RPC or custody, inspect infrastructure and env — not `VaultIdentity`, `IdentityBinding`,
`VaultSettlementProof`, or `CreditDecision`.

---

## Sign-off

```text
Managed Wallet v0 — Level 3 Staging Drill
Environment:     <staging URL>
Operator:        <name>
Date (UTC):      <YYYY-MM-DD>
Result:          PASS | FAIL
Manual repair:   NO | YES (FAIL if YES)
Notes:
```

On **PASS** without manual repair:

```text
Architecture:        CLOSED
Implementation:      CLOSED
CI Proof:            CLOSED
Staging Proof:       CLOSED
Ready For Rollout:   YES
```

---

## After Level 3

Do **not** open a new architectural ADR automatically.

The next open question becomes product/UX:

> Do users understand the difference between **Create Safe** and **Connect existing wallet**?

Technically, managed wallet is proven as ingress into the existing identity → observation →
settlement → accounting chain. Architectural risk is low; activation and comprehension risk
remains.
