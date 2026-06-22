# Level 3 Staging Evidence Record

| Field | Value |
|-------|-------|
| **Run ID** | |
| **Date (UTC)** | |
| **Environment** | staging |
| **Backend commit** | |
| **Frontend commit** | |
| **Operator** | |
| **Drill user** | `@` / entity |

Capture commands: `scripts/level3-evidence-capture.sh` (see header in script).

---

## BEFORE

### Identity anchor

| Field | Value (A) |
|-------|-----------|
| `entity_l1_address` | |
| `vault.id` | |

### Settlement (active Polygon binding)

| Field | Value (B / S) |
|-------|----------------|
| `binding.id` | |
| `binding_source` | `managed` \| `external` |
| `verification_method` | `vault_key` \| `signature` |
| `verification_state` | |
| `settlement_address` (`binding_value_normalized`) | |

### Observation

| Field | Value (U) |
|-------|-----------|
| `chain` | polygon |
| `token` | USDC |
| `api_observed_balance` | |
| `chain_balance_raw` | |
| `chain_balance_human` | |
| `observation_timestamp_utc` | |

### Accounting path (note)

| Check | yes/no |
|-------|--------|
| No `binding_source` branch observed in settlement/accounting | |

**Capture log (paste script output):**

```text

```

---

## DRILL EXECUTION

Do **not** delete durable rows (`vault_identities`, `identity_bindings`).

- [ ] logout
- [ ] clear application cache (`php artisan cache:clear` or env equivalent)
- [ ] clear browser local storage / session
- [ ] projection rebuild (if supported; note skip reason if N/A)
- [ ] frontend redeploy (optional surface attack)
- [ ] re-auth

**Drill notes:**

```text

```

---

## AFTER

### Identity anchor

| Field | Value (A') |
|-------|------------|
| `entity_l1_address` | |
| `vault.id` | |

### Settlement

| Field | Value (B' / S') |
|-------|------------------|
| `binding.id` | |
| `binding_source` | |
| `verification_method` | |
| `verification_state` | |
| `settlement_address` | |

### Observation

| Field | Value (U') |
|-------|------------|
| `chain` | polygon |
| `token` | USDC |
| `api_observed_balance` | |
| `chain_balance_raw` | |
| `chain_balance_human` | |
| `observation_timestamp_utc` | |

**Capture log (paste script output):**

```text

```

---

## MATCH CHECK

Facts only — no interpretation.

| Dimension | A == A' / rule | PASS | FAIL |
|-----------|----------------|------|------|
| **Identity** (`entity_l1_address`) | required | [ ] | [ ] |
| **Vault** (`vault.id`) | required | [ ] | [ ] |
| **Binding** (`binding.id`) | required | [ ] | [ ] |
| **Address** (`settlement_address`) | required | [ ] | [ ] |
| **Observation** | U' valid for S' through B' (see U rule) | [ ] | [ ] |

**U rule:** If no on-chain tx during DRILL → `api` and `chain` balances should match BEFORE. If drill included USDC movement → balances may differ; PASS if same S, same B, and U' matches current chain state for S.

### Degradation signals (any = auto-FAIL)

- [ ] new vault created (V ≠ V')
- [ ] new binding created (B ≠ B')
- [ ] manual admin repair required
- [ ] stale observation (API ≠ chain for same S)

---

## RESULT

| | |
|-|-|
| **Result** | PASS / FAIL |
| **Manual repair** | NO / YES (YES = FAIL) |

**Notes:**

```text

```

---

## Final sign-off

```text
Architecture:        PASS (pre-closed)
Presentation:        PASS (pre-closed)
Level 3 Recovery:    PASS | FAIL
Evidence file:       docs/evidence/level-3-run-YYYY-MM-DD.md
Backend commit:      
Frontend commit:     
Approved by:         
Date (UTC):          
```

On PASS:

```text
Staging Proof:       CLOSED
Ready For Rollout:   YES

Proven: Different provisioning → same IdentityBinding → same observation → same accounting
        Vault abstraction survived recovery, not only happy path.
```
