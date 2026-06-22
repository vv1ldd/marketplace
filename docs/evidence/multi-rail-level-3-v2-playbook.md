# Multi-rail Level 3 v2 Playbook

**Separate hypothesis from v1 — distinct language, distinct PASS criteria.**

| | **v1 — Ownership durability** | **v2 — Economic durability** |
|---|--------------------------------|------------------------------|
| **Proves** | Alice **survives** (v1) | Alice's money survives **as Alice's money** |
| **Chain** | `A → V → B → S` | `A → V → B → S → U` |
| **System can say** | Alice has this graph | **this USDC belongs to Alice** |
| **Question** | Может ли субъект пережить потерю поверхности? | Может ли economic state вернуться к **тому же** субъекту? |
| **PASS** | same identity · vault · binding · endpoint | v1 **+** same observation path |

**v2 goal:** Prove **U is attached to B**, not merely observable on S.

```text
Observed State:  USDC on S
Owned State:     U belongs to durable graph through B
```

**Anti-test:** `B_new + same S + same U` — address-centric SUCCESS · ownership-centric **FAIL** (different ownership evidence).

**After v2:** full ownership model `Identity → Ownership → Economic Ownership` — base for Recipient Resolution → Payment Intent → Transfer.

**v3b** = operation on graphs: `Owner(Alice, U)` → `Owner(Bob, U)`; SettlementAdapter realizes the change externally.

**v2 is more fundamental than v3** — v3 makes payments useful; v2 makes the system one of **economic ownership**, not addresses and balances.

| Before v2 | After v2 |
|-----------|----------|
| Durable subject + instruments | **This economic state belongs to Alice** |
| «Where is the money?» | «**Whose** money is this?» — language of **property**, not storage |

**Anti-test `new B + same S + same U`:**

| Model | Reads as |
|-------|----------|
| Address-centric | SUCCESS (same address, same balance) |
| Ownership-centric | **FAIL** (same state, **different ownership evidence**) |

v2 proves continuity of **Owner → Economic State**, not balance on screen.

**Survival questions (proof chain):**

```text
v1  Can ownership survive?
v2  Can economic ownership survive?
v3a Can recipient identity survive instrument changes?
v3b Can economic ownership move between identities?
```

**v3b payment as ownership change:**

```text
Before: Alice owns 10 USDC
After:  Bob owns 10 USDC
```

not token move between network coordinates.

**Next most valuable artifact: v2 evidence** — prove economic state is part of durable ownership graph, not observed address state. Then v3b is transfer of economic ownership, not a send feature.

**Paradigm:** system object = subject with controlled settlement surfaces. Key/address = execution mechanism, not identity.

**Anti-test (FAIL despite green UI):**

```text
A → V → new B → same S → same U
```

**Correct:**

```text
A → V → same B → same S → same observation path
```

**v2 is the last foundational layer before payments.** v3 is the first human UX layer (`@alice`, not `0x7985…`).

**Prerequisite:** [multi-rail-level-3-run-2026-06-22.md](./multi-rail-level-3-run-2026-06-22.md) (v1 PASS). Reuse same vault / B7·B8·B9 — v2 adds only `U` continuity inside an already-proven graph.

**Artifacts:** [`multi-rail-level-3-v2-TEMPLATE.md`](./multi-rail-level-3-v2-TEMPLATE.md) → `multi-rail-level-3-v2-run-YYYY-MM-DD.md`

**Out of scope:** user send, alias payments, outbound settlement.

---

## Proof chain (do not conflate)

### v1 — Ownership durability

```text
A → V → B → S
```

**Answer:** «тот же субъект вернулся?»

**PASS:** same identity · same vault · same binding · same settlement endpoint

### v2 — Economic durability

```text
A → V → B → S → U
```

**Answer:** «тот же экономический объект снова наблюдается через тот же durable path?»

**PASS:** same identity · same vault · same binding · same settlement endpoint · **same observation path**

### v3 — Routing / execution (future)

```text
@alice → identity resolver → recipient vault → binding selection → settlement execution
```

First layer where payments meet human UX — not storage.

---

## address continuity ≠ economic continuity

```text
S = same
U = same
B = new        →  FAIL — different ownership subject
```

**Check order (always top-down, never U-first):**

```text
A → V → B → S → U
```

`U` confirms the path only after `B` and `S` are established.

---

## After v2 (product framing)

**Not:** «у пользователя есть три адреса»

**But:** один identity graph контролирует несколько settlement surfaces; economic state восстанавливается через **тот же graph**.

**Wallet semantics:** Safe/address = settlement **instrument** under identity — not the account. v2 closes the last “wallet” layer; v3 opens payments between people.

**Alias ordering:** `@alice` routing is valid only **after** ownership + economic durability are proven — not `username → address → hope`.

---

## Proof object

```text
A
│
V
│
├── B7 → S7 → U7
├── B8 → S8 → U8
└── B9 → S9 → U9
```

| Symbol | Role |
|--------|------|
| **A** | who owns |
| **V** | state container |
| **B** | durable binding (ownership object) |
| **S** | settlement endpoint |
| **U** | observed economic fact (not source of truth) |

**Key invariant:** `network ≠ identity` · `network = attachment`

**What v2 tests:** not «проверка баланса», but **связь economic state с durable ownership graph**.

### U is not source of truth

**Wrong:** «вижу 0.01 USDC → всё восстановилось»

**Right:** `B` → `S` → chain/API observe `U` again

`U` confirms path only **after** `A/V/B/S` pass. `S`+`U` match with `B` new = FAIL (different ownership subject).

---

## Run order

```text
CREATE (v1 graph — already done)
        ↓
FUND (~0.01 USDC per rail)
        ↓
OBSERVE (API + chain settle)
        ↓
BEFORE capture
        ↓
GRAPH + OBSERVATION GATE   ← must PASS before destruction
        ↓
surface destruction
        ↓
RECOVER (re-auth)
        ↓
AFTER capture
        ↓
compare (A/V/B/S/U per rail)
        ↓
sign-off
```

---

## Staging v2 (five steps)

```text
1. FUND     B7→S7, B8→S8, B9→S9  (~0.01 USDC per rail)
2. BEFORE   capture A, V, B/S/U per rail
3. GATE     GRAPH + OBSERVATION GATE: PASS  (then only → drill)
4. DRILL    surface only
5. AFTER    B7'=B7, S7'=S7, U7'=U7, … → compare → sign-off
```

---

## 0. Preparation

```bash
export LEVEL3_API_URL="https://meanly.one"
export LEVEL3_VAULT_TOKEN="..."              # localStorage: meanly:storefront-token
export LEVEL3_POLYGON_RPC_URL="..."
export LEVEL3_ETHEREUM_RPC_URL="..."
export LEVEL3_BASE_RPC_URL="..."
```

| Check | Expected |
|-------|----------|
| Multi-rail managed enabled | polygon, ethereum, base in `managed_wallet_networks` |
| v1 graph exists | 3 managed bindings on test vault |
| RPC URLs set | chain cross-check in capture (non-fatal if 403; note in evidence) |

Copy template:

```bash
cp docs/evidence/multi-rail-level-3-v2-TEMPLATE.md \
   docs/evidence/multi-rail-level-3-v2-run-$(date -u +%Y-%m-%d).md
```

**Subject:** reuse v1 multi-rail identity **or** fresh identity with Create Safe ×3 (same as v1 steps 1–2).

---

## 1. Fund each rail

Send **~0.01 USDC** on each rail to the settlement address `S` for that rail.

| Rail | Send to | Record |
|------|---------|--------|
| polygon | `S7` | tx hash |
| ethereum | `S8` | tx hash |
| base | `S9` | tx hash |

Wait until observation pipeline shows USDC on all three rails in UI and `GET …/wallet/assets`.

**Do not** proceed to BEFORE until all three rails show funded observation (or document explicit exclusion with FAIL).

---

## 2. Capture BEFORE

```bash
./scripts/level3-evidence-capture.sh before | tee /tmp/multi-rail-v2-before.log
```

Pre-drill gate — **only after `GRAPH + OBSERVATION GATE: PASS`** proceed to destruction:

```bash
LEVEL3_REQUIRE_OBSERVATION=1 \
  ./scripts/level3-validate-graph.sh gate /tmp/multi-rail-v2-before.log
```

Expected checklist:

```text
A ✓
V ✓
polygon:   B7 ✓  S7 ✓  U7 ✓
ethereum:  B8 ✓  S8 ✓  U8 ✓
base:      B9 ✓  S9 ✓  U9 ✓

GRAPH + OBSERVATION GATE: PASS
```

Record per rail in evidence file:

```text
polygon:  B7 → S7 → U7 (API + chain)
ethereum: B8 → S8 → U8 (API + chain)
base:     B9 → S9 → U9 (API + chain)
```

---

## 3. Destructive drill (surface only)

Attack **temporary** state only. v2 PASS is about restoring **ownership relation**, not redisplaying a balance.

**Recommended surface stack (strongest v2 test):**

- [ ] logout
- [ ] browser local storage / session wipe
- [ ] projection / cache rebuild (`php artisan cache:clear` or ops equivalent)
- [ ] frontend redeploy (presentation-only attack)
- [ ] re-auth

Funded USDC on chain must be untouched during drill — no sends.

**Do not touch:**

- vault rows
- `identity_bindings`
- managed keys
- on-chain balances

**After recovery, expect:**

```text
same identity · same vault · same binding · same endpoint · same economic observation
```

per rail — not «UI shows 0.01 again» without `B` continuity.

---

## 4. Recovery + capture AFTER

Re-auth same identity → `/vault` shows dashboard (not Create Safe ×3).

```bash
export LEVEL3_VAULT_TOKEN="..."   # fresh token after re-login
./scripts/level3-evidence-capture.sh after | tee /tmp/multi-rail-v2-after.log
```

---

## 5. Compare (v2)

```bash
LEVEL3_REQUIRE_OBSERVATION=1 \
  ./scripts/level3-validate-graph.sh compare \
    /tmp/multi-rail-v2-before.log \
    /tmp/multi-rail-v2-after.log
```

**PASS criteria (all rails):**

```text
A == A'
V == V'
B7 == B7'   S7 == S7'   U7 == U7'
B8 == B8'   S8 == S8'   U8 == U8'
B9 == B9'   S9 == S9'   U9 == U9'
```

| Order | Check | Rule |
|-------|-------|------|
| 1 | Identity | `A == A'` |
| 2 | Vault | `V == V'` |
| 3 | Binding | `B == B'` (same `binding_id`) — **primary** |
| 4 | Settlement | `S == S'` |
| 5 | Observation | `U == U'` **through same B→S** — confirms path, not standalone truth |

During drill window: no on-chain sends. If `B`/`S` match but `U` differs without documented tx → investigate observation path, not «accept balance».

---

## 6. Anti-test (must FAIL compare)

**Looks perfect externally** — same address, same money, green UI — but **wrong binding**:

```text
A → V → new B → same S → same U     FAIL
```

Simulate on non-production subject only. Required for PASS:

```text
A → V → same B → same S → same observation path
```

`same money ≠ same ownership continuity`. v1 compare flags `binding_id` recreation; v2 adds U must attach to **same B**, not coincident balance on new binding.

---

## 7. After v2 — architectural outcome

**Transition:** wallet-centric → **identity-centric settlement**. Trust anchor moved:

```text
private key → address → funds     →     identity → binding → instrument → evidence
```

```text
address = coordinate (where)     identity = owner (whom)
```

**Strongest v2 proof:** user exists **independently of any settlement surface** — not «three Safes created».

**v3 constraint:** `alias → identity → ownership graph → instruments` — never `alias → address`.

**Next frontier:** identity-to-identity value transfer (L4 Routing → L5 Settlement).

---

## 8. Sign-off

Fill **RESULT** in `multi-rail-level-3-v2-run-YYYY-MM-DD.md`:

```text
Multi-rail L3 v1:  PASS (graph)     — prerequisite
Multi-rail L3 v2:  PASS | FAIL      — graph + U continuity
Manual repair:     NO | YES (YES = FAIL)
```

On PASS:

```text
One identity → one vault → three settlement instruments → same economic observation after recovery
```

Three rails = **one economic subject**, not three unrelated addresses.

---

## What v2 does not prove

| Topic | Deferred to |
|-------|-------------|
| Outbound send / user withdrawal | v3 |
| `@alice` alias → recipient resolution → routing | v3 |
| Cross-rail aggregation | product scope |
| «Balance alone proves recovery» | anti-pattern — see U section |

**v3 framing (future):** not storage — **routing**:

```text
alias → identity → vault → binding selection → settlement execution
```

---

## Quick reference

```text
CREATE (done) → FUND → OBSERVE → BEFORE → GATE → destroy surface → RECOVER → AFTER → compare → sign-off
```

**Related:** [multi-rail-level-3-run-2026-06-22.md](./multi-rail-level-3-run-2026-06-22.md) (v1) · [level-3-run-playbook.md](./level-3-run-playbook.md) (single-rail)
