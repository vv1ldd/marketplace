# Multi-rail Level 3 Recovery Evidence (v1)

| Field | Value |
|-------|-------|
| **Run ID** | multi-rail-level3-2026-06-22-001 |
| **Date (UTC)** | 2026-06-22 |
| **Environment** | staging (`https://meanly.one`) |
| **Backend commit** | workspace (`f5cfb55` baseline + multi-EVM managed rails) |
| **Operator** | `@w1ld` |
| **Drill subject** | new identity / clean vault (incognito passkey session) |

**Status:** `CLOSED` — **Multi-rail L3 v1: PASS** (Ownership durability)

**Hypothesis (v1):** `A → V → B → S` — «тот же субъект вернулся?»

**PASS:** same identity · same vault · same binding · same settlement endpoint (per rail).

**Not v2:** no `U` / economic observation requirement. See [multi-rail-level-3-v2-playbook.md](./multi-rail-level-3-v2-playbook.md).

**Logs:** `/tmp/multi-rail-before.log`, `/tmp/multi-rail-after.log`

---

## Objective

Prove **settlement graph continuity** across **three managed EVM rails** under one identity and one vault — not three wallet products.

```
One identity → one vault → many settlement instruments → durable recovery
```

**v1 scope:** provision → bind → recover graph. **No** funded USDC observation requirement. **No** outbound settlement requirement.

**Anti-pattern under test:** `delete B → create B' → reuse S` must **FAIL** compare (binding_id continuity required).

---

## Graph BEFORE

**Captured:** `2026-06-22T08:50:02Z`

### Identity anchor

| Field | Value (A) |
|-------|-----------|
| `entity_l1_address` | `sl1e_d0f13387574b496aae03ccb433f20ab2b4b9a35` |
| `vault.id` | `d962bcad-590e-4293-a01b-ebbbee5df6d7` |

### Settlement graph

```
A
└── V
    ├── B7  polygon  → 0x08f32fe1f13730afe2e91c7abe459eca2f65df9d
    ├── B8  ethereum → 0x58db9d7514970b76047d3ecef86bafb233f42310
    └── B9  base     → 0x7985c4a8161ea4c15452b42d0778da90eab6a73a
```

| Rail | B | source | verification | state | S |
|------|---|--------|--------------|-------|---|
| polygon | 7 | managed | vault_key | verified | `0x08f32fe1f13730afe2e91c7abe459eca2f65df9d` |
| ethereum | 8 | managed | vault_key | verified | `0x58db9d7514970b76047d3ecef86bafb233f42310` |
| base | 9 | managed | vault_key | verified | `0x7985c4a8161ea4c15452b42d0778da90eab6a73a` |

**Note:** `S1 ≠ S2 ≠ S3` is expected (different settlement endpoints per rail).

### bindings[] (API capture)

```json
[
  {
    "network": "base",
    "binding_id": 9,
    "binding_source": "managed",
    "verification_method": "vault_key",
    "verification_state": "verified",
    "address": "0x7985c4a8161ea4c15452b42d0778da90eab6a73a"
  },
  {
    "network": "ethereum",
    "binding_id": 8,
    "binding_source": "managed",
    "verification_method": "vault_key",
    "verification_state": "verified",
    "address": "0x58db9d7514970b76047d3ecef86bafb233f42310"
  },
  {
    "network": "polygon",
    "binding_id": 7,
    "binding_source": "managed",
    "verification_method": "vault_key",
    "verification_state": "verified",
    "address": "0x08f32fe1f13730afe2e91c7abe459eca2f65df9d"
  }
]
```

---

## Drill execution

Surface-only destruction. **No** durable layer mutation.

- [x] logout
- [x] clear browser local storage / session (`meanly.one`)
- [x] re-auth (same identity)
- [x] reload `/vault`

**Excluded (intentionally not performed):**

- [ ] DB changes
- [ ] binding delete / revoke
- [ ] managed key rotation
- [ ] new Create Safe on any rail

---

## Graph AFTER

**Captured:** `2026-06-22T08:54:12Z`

### Identity anchor

| Field | Value (A') |
|-------|------------|
| `entity_l1_address` | `sl1e_d0f13387574b496aae03ccb433f20ab2b4b9a35` |
| `vault.id` | `d962bcad-590e-4293-a01b-ebbbee5df6d7` |

### bindings[] (API capture)

Same graph as BEFORE — binding ids unchanged:

| Rail | B' | S' |
|------|----|----|
| polygon | 7 | `0x08f32fe1f13730afe2e91c7abe459eca2f65df9d` |
| ethereum | 8 | `0x58db9d7514970b76047d3ecef86bafb233f42310` |
| base | 9 | `0x7985c4a8161ea4c15452b42d0778da90eab6a73a` |

---

## validate-graph output (pre-drill gate)

```text
./scripts/level3-validate-graph.sh gate /tmp/multi-rail-before.log

GRAPH GATE: PASS
  identity: captured
  vault: captured
  rails: base ethereum polygon
  bindings: 3
  managed / vault_key / verified: ok
  graph: VALID
```

---

## compare-graph output (post-recovery)

```text
./scripts/level3-validate-graph.sh compare \
  /tmp/multi-rail-before.log \
  /tmp/multi-rail-after.log

--- 1. Identity (A) ---
PASS: A == A' (sl1e_d0f13387574b496aae03ccb433f20ab2b4b9a35)
--- 2. Vault (V) ---
PASS: V == V' (d962bcad-590e-4293-a01b-ebbbee5df6d7)

--- 3. Binding objects (B) + 4. Settlement endpoints (S) ---
Per-rail continuity:
  base: B=9 S=0x7985c4a8161ea4c15452b42d0778da90eab6a73a (managed/vault_key) == AFTER
  ethereum: B=8 S=0x58db9d7514970b76047d3ecef86bafb233f42310 (managed/vault_key) == AFTER
  polygon: B=7 S=0x08f32fe1f13730afe2e91c7abe459eca2f65df9d (managed/vault_key) == AFTER

GRAPH COMPARE: PASS
Multi-rail Level 3 Recovery = PASS
```

---

## PASS criteria

| Check | Result |
|-------|--------|
| `A == A'` | PASS |
| `V == V'` | PASS |
| `B7 == B7'`, `B8 == B8'`, `B9 == B9'` | PASS |
| `S1 == S1'`, `S2 == S2'`, `S3 == S3'` | PASS |
| All rails `managed` / `vault_key` / `verified` | PASS |
| Unique `binding_id` per rail (anti-recreation) | PASS |
| Manual repair | NO |

**Primary invariant:** recover **existing** graph — not recreate equivalent graph.

---

## Exclusions (out of v1 scope)

| Topic | Status in this run |
|-------|-------------------|
| USDC funding / chain observation | Not required for v1 PASS (API showed `0 USDC`; chain cross-check skipped/403) |
| Outbound settlement / send | Not tested (see single-rail operational note in level-3-run-2026-06-22.md) |
| Bitcoin / Solana / TON Connect | Not in scope (non-EVM attachment semantics) |
| Storefront session tokens | Not stored in this file |

---

## Roadmap (not closed by this artifact)

| Phase | Proves | Artifact |
|-------|--------|----------|
| **Multi-rail L3 v2** | fund → observe → recover → same `U` per rail | [`multi-rail-level-3-v2-playbook.md`](./multi-rail-level-3-v2-playbook.md) |
| **Multi-rail L3 v3** | alias → recipient resolution → settlement execution | TBD |

---

## Closure status

```text
Architecture:         CLOSED ✓
Presentation:         CLOSED ✓
Verification:         CLOSED ✓
Single-rail Level 3:  PASS ✓  (separate evidence file)
Multi-rail Level 3 v1: PASS ✓

Proven: One identity → one vault → many managed settlement instruments → durable recovery
```

**Approved by:** `@w1ld`  
**Date (UTC):** 2026-06-22
