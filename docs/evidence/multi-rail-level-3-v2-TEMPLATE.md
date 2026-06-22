# Multi-rail Level 3 v2 Evidence Record

**Hypothesis:** Economic durability — `A → V → B → S → U`

**Question:** Тот же экономический объект снова наблюдается через тот же durable path?

**PASS:** same identity · vault · binding · settlement endpoint · **observation path** (per rail).

**Check order:** `A → V → B → S → U` — never U-first.

**Prerequisite:** v1 Ownership durability PASS (link). Same vault / B7·B8·B9 intentional.

**Last foundational layer before payments.** v3 = `@alice` routing (out of scope).

| Field | Value |
|-------|-------|
| **Run ID** | |
| **Date (UTC)** | |
| **Environment** | staging |
| **Backend commit** | |
| **Operator** | |
| **Drill subject** | vault from v1 PASS (link) |
| **Prerequisite** | multi-rail L3 v1 Ownership durability PASS |

**New fact this run establishes:** `U` survives the same surface drill that `B`/`S` already survived.

### Proof object

```text
A  — who owns
V  — state container
B  — durable binding
S  — settlement endpoint
U  — observed economic fact (NOT source of truth)
```

**U rule:** PASS requires `B == B'` and `S == S'` **first**; then chain/API observe `U == U'` through that path. Never: «вижу 0.01 USDC → всё восстановилось».

---

## Graph BEFORE

**Captured:**

### Identity anchor

| Field | Value (A) |
|-------|-----------|
| `entity_l1_address` | |
| `vault.id` | |

### Per-rail graph + observation

| Rail | B | S | U (API) | U (chain) | Fund tx |
|------|---|---|---------|-----------|---------|
| polygon | | | | | |
| ethereum | | | | | |
| base | | | | | |

**Target:** ~0.01 USDC per rail (adjust if documented).

### bindings[] capture (paste)

```json

```

### GRAPH + OBSERVATION GATE (pre-drill; must PASS before destruction)

```text
LEVEL3_REQUIRE_OBSERVATION=1 ./scripts/level3-validate-graph.sh gate /tmp/multi-rail-v2-before.log

(paste output — expect GRAPH + OBSERVATION GATE: PASS)
```

---

## Drill execution

Surface-only. v2 tests **ownership relation recovery**, not balance redisplay.

- [ ] logout
- [ ] clear browser storage / session
- [ ] projection / cache rebuild
- [ ] frontend redeploy (recommended for strongest v2)
- [ ] re-auth (same identity)

No vault / binding / key / on-chain changes during drill.

**Drill notes:**

```text

```

---

## Graph AFTER

**Captured:**

### Identity anchor

| Field | Value (A') |
|-------|------------|
| `entity_l1_address` | |
| `vault.id` | |

### Per-rail continuity

| Rail | B' | S' | U' (API) | U' (chain) | Match |
|------|----|----|----------|------------|-------|
| polygon | | | | | ☐ |
| ethereum | | | | | ☐ |
| base | | | | | ☐ |

### bindings[] capture (paste)

```json

```

---

## compare-graph output (v2)

```text
LEVEL3_REQUIRE_OBSERVATION=1 ./scripts/level3-validate-graph.sh compare \
  /tmp/multi-rail-v2-before.log \
  /tmp/multi-rail-v2-after.log

(paste output)
```

---

## PASS criteria

```text
A == A'    V == V'
B7==B7'  S7==S7'  U7==U7'
B8==B8'  S8==S8'  U8==U8'
B9==B9'  S9==S9'  U9==U9'
```

| Dimension | Rule | PASS |
|-----------|------|------|
| Identity | `A == A'` | ☐ |
| Vault | `V == V'` | ☐ |
| Binding (per rail) | `B == B'` — primary | ☐ |
| Settlement (per rail) | `S == S'` | ☐ |
| Observation (per rail) | `U == U'` via **same B→S** (confirms path) | ☐ |
| Anti-pattern | no `B'` + matching `S` + matching `U` | ☐ |
| Manual repair | NO | ☐ |

**U rule:** `U` is observed fact through durable `B` and `S`. If `B`/`S` diverge, FAIL regardless of balance. Balances may differ only if on-chain tx occurred **during** drill (document tx; no sends during drill).

---

## Exclusions

| Topic | Status |
|-------|--------|
| User outbound send | Out of scope (v3) |
| Alias / `@handle` payments | Out of scope (v3) |
| Cross-rail net worth | Not required |

---

## Anti-test note (reference)

```text
FAIL (green UI):  A → V → new B → same S → same U
PASS:             A → V → same B → same S → same observation path

same money ≠ same ownership continuity
```

---

## RESULT

```text
Multi-rail L3 v1:  PASS (prerequisite)
Multi-rail L3 v2:  PASS | FAIL
Manual repair:     NO | YES
```

**Approved by:**  
**Date (UTC):**
