# Level 3 Staging Run Playbook

**One controlled scenario.** The operator records state before/after — does not hunt bugs or interpret architecture.

**Artifacts:** copy [`level-3-run-TEMPLATE.md`](level-3-run-TEMPLATE.md) → `level-3-run-YYYY-MM-DD.md` · capture [`scripts/level3-evidence-capture.sh`](../../scripts/level3-evidence-capture.sh)

**Duration:** ~15–30 minutes total.

| Phase | Time |
|-------|------|
| Identity + Safe | ~5 min |
| USDC observation | ~5 min |
| Destructive drill + re-auth | ~5 min |
| Compare + sign-off | ~5 min |

**What Level 3 tests:** recovery after surface destruction — **not** “does Create Safe work?” (already closed in CI).

---

## 0. Preparation

On staging host or operator machine:

```bash
export LEVEL3_API_URL="https://meanly.one"   # capture hits /backend/api/...
export LEVEL3_VAULT_TOKEN="..."              # localStorage: meanly:storefront-token
export LEVEL3_POLYGON_RPC_URL="..."          # optional chain cross-check
```

Verify:

| Check | Expected |
|-------|----------|
| `MANAGED_WALLETS_ENABLED=true` | `GET …/wallet` → `capabilities.managed_wallets_enabled: true` |
| Safe-first frontend deployed | provisioning shell, not 6× Connect wall |
| Staging DB reachable | `php artisan tinker` or DB client |
| Capture script runs | `./scripts/level3-evidence-capture.sh before` prints without error |

Copy template:

```bash
cp docs/evidence/level-3-run-TEMPLATE.md docs/evidence/level-3-run-$(date -u +%Y-%m-%d).md
```

---

## 1. Create test identity

**Do not** reuse an old account.

Need clean state:

```text
identity A
vault V
no Polygon binding
```

| Action | Observable fact |
|--------|-----------------|
| Create new identity | fresh user |
| Open `/vault` | Safe-first provisioning shell |

**PASS UI:** `Your Vault` · `Create Safe` · `Connect existing →`

**FAIL UI:** six network cards each with `Connect` on landing

---

## 2. Create Safe

| Action | Observable fact |
|--------|-----------------|
| Click **Create Safe** | `POST …/wallet/bindings/managed` → `201` |

**API:**

| Field | Expected |
|-------|----------|
| `binding_source` | `managed` |
| `verification_method` | `vault_key` |
| `verification_state` | `verified` |

**DB:** one active Polygon row in `identity_bindings` for this `vault_id`.

**UI:** `My Safe` · address `0x…` · Connected · Identity bound

Record `V`, `B`, `S` from API or script.

---

## 3. Real USDC evidence

| Action | Observable fact |
|--------|-----------------|
| Send **~0.01 USDC** on Polygon to settlement address `S` | tx hash recorded in evidence file |
| Wait for observation pipeline | `GET …/wallet/assets` shows USDC |

Now baseline exists:

```text
A  entity_l1_address
V  vault.id
B  binding.id
S  settlement address
U  observed USDC (+ chain cross-check if RPC set)
```

---

## 4. Capture BEFORE

```bash
./scripts/level3-evidence-capture.sh before | tee /tmp/level3-before.log
```

Paste output into **BEFORE** section of `docs/evidence/level-3-run-YYYY-MM-DD.md`.

Sources captured:

- **API** — product view
- **DB** — durable state (if `LEVEL3_VAULT_ID` set / from API)
- **Chain** — `balanceOf` for `S` (if `LEVEL3_POLYGON_RPC_URL` set)

---

## 5. Destructive drill (surface only)

Attack **temporary** state. **Do not** delete or edit:

- `vault_identities`
- `identity_bindings`
- identity stream / durable rows

### Browser

- [ ] Logout
- [ ] Clear site data (local storage, cookies for vault)
- [ ] Close browser

### Session

- [ ] New browser session / incognito

### Backend (operator)

- [ ] `php artisan cache:clear` (or env-equivalent)

### Optional (if supported on staging)

- [ ] Projection rebuild per ops runbook
- [ ] Frontend redeploy (presentation-only attack)

---

## 6. Recovery

| Action | Observable fact |
|--------|-----------------|
| Login again (same identity) | same credentials / passkey flow |
| Open `/vault` | **dashboard**, not provisioning shell |

**PASS:** `My Safe` · same address · USDC still visible

**FAIL:** `Create Safe` again · forced `Connect wallet` · new address without user action

---

## 7. Capture AFTER

Re-export token if session changed:

```bash
export LEVEL3_VAULT_TOKEN="..."   # fresh bearer after re-login
./scripts/level3-evidence-capture.sh after | tee /tmp/level3-after.log
```

Paste into **AFTER** section of evidence file → `A'`, `V'`, `B'`, `S'`, `U'`.

---

## 8. Comparison (facts only)

Fill **MATCH CHECK** in evidence file:

| Dimension | Rule | PASS |
|-----------|------|------|
| Identity | `A == A'` | ☐ |
| Vault | `V == V'` | ☐ |
| Binding | `B == B'` | ☐ |
| Address | `S == S'` | ☐ |
| Observation | U' valid for S' through B' | ☐ |

**U rule:** balances may differ if on-chain tx occurred during drill; PASS if same `S`, same `B`, and U' matches chain for `S`.

**Auto-FAIL:** new vault · new binding · manual admin repair · stale observation

---

## 9. DB + chain cross-check

On staging host:

```bash
export LEVEL3_VAULT_ID="<from BEFORE>"
./scripts/level3-evidence-capture.sh db
```

Triangulation must agree:

```text
DB settlement_address  ==  API address  ==  Chain balanceOf target
```

Chain confirms economic object exists; **identity + binding continuity** still required for PASS.

---

## 10. Sign-off

Complete **RESULT** and **Final sign-off** in `docs/evidence/level-3-run-YYYY-MM-DD.md`:

```text
Result: PASS | FAIL
Manual repair: NO | YES (YES = FAIL)
```

On **PASS**:

```text
Architecture:        PASS (pre-closed)
Presentation:        PASS (pre-closed)
Level 3 Recovery:    PASS

Different provisioning
        ↓
Same IdentityBinding
        ↓
Same observation
        ↓
Same accounting path
```

Commit evidence file (redact tokens). Update staging drill status:

```text
Staging Proof:       CLOSED
Ready For Rollout:   YES
```

---

## Quick reference

```text
Create Safe → USDC → capture BEFORE → destroy surface → re-auth → capture AFTER → compare → sign-off
```

Operator records facts. Script does not assign PASS/FAIL. Template does not fetch data.
