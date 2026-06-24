# Provider Interface — Independence Drills (backlog)

**Status:** Backlog — architectural checks before **Provider Interface v1**  
**Normative spec:** [Provider Interface v0](../spec/provider-interface-v0.md) (Phase 1–3)  
**Architecture:** [ADR 0027](../adr/0027-identity-attachments-and-provider-ownership.md)

These drills are **not** part of Provider Interface v0. They are manual or staging
acceptance scenarios that prove the identity ↔ custody boundary survives full
lifecycle — not only a correct `GET /api/identity/summary` JSON shape.

Passing both drills without model changes is a prerequisite for declaring
**Provider Interface v1** (multi-provider, production link/unlink).

---

## Why two drills

| Drill | What it stress-tests |
|-------|----------------------|
| **Provider Independence** | Identity survives **provider** change |
| **Storefront Independence** | Provider survives **storefront entry** change |

Summary API can look correct while attach / detach / reattach still leak
settlement semantics into identity. These drills target that gap.

---

## Invariants (both drills)

**Must remain true after every step:**

```text
Identity owns attachments only.
Providers own instruments.
Storefronts are entry points, not custody partitions.
```

**Forbidden outcomes (any step):**

- New `VaultIdentity` for the same `sl1e_` because the host changed
- New managed bindings or addresses because the user opened a regional domain
- New `provider_id` per region (e.g. `meanly.vault.ru`)
- `instrument_id`, `network`, or non-SL `balances` appearing on identity / attachment protocol records
- Protocol schema changes to “fix” the drill

**`client_id` ≠ `provider_id`**

| Concept | Examples | Layer |
|---------|----------|--------|
| `client_id` | `meanly.one`, `meanly.ru` | SL1 Connect / regional OAuth |
| `provider_id` | `meanly.vault` | Custody provider (one per product) |

Regional storefronts MAY differ in `client_id`. They MUST converge on the same
`provider_id` for Meanly Vault. Otherwise storefronts become disguised providers
and regional sites spawn regional wallets.

---

## Drill A — Provider Independence

**Goal:** `sl1e_` outlives provider swap without identity migration.

### Scenario

1. Authenticate → obtain `sl1e_abc`
2. **Link** Meanly Vault (`meanly.vault`) → attachment `active`
3. Create at least one managed instrument (e.g. Polygon) inside Vault
4. Record `attachment_id`, `VaultIdentity.id`, binding ids, managed address(es)
5. **Detach** Meanly Vault → protocol shows `status: revoked` only
6. **Link** a second provider (future: stub or test double) OR re-link Meanly after detach policy allows
7. Re-open identity summary on `simplelayer.one`

### Expected

| Check | Expected |
|-------|----------|
| `sl1e_` | Unchanged |
| Identity protocol record after detach | `{ attachment_id, provider_id, status: "revoked" }` — no instrument fields |
| Re-link same `sl1e_` to `meanly.vault` | Same `VaultIdentity` and instrument set (per provider re-link policy) |
| `/api/identity/summary` | No non-SL balances; no capability matrix |

### Detach boundary (protocol vs provider)

Protocol MUST know only:

```json
{
  "attachment_id": "att_…",
  "provider_id": "meanly.vault",
  "status": "revoked"
}
```

Provider policy (out of protocol scope) decides:

- whether keys are deleted or archived
- whether bindings stay in DB
- whether order history remains
- whether re-link revives the same vault

**Fail** if detach forces identity to reference instruments, networks, or addresses.

---

## Drill B — Storefront Independence

**Goal:** `meanly.one` and `meanly.ru` are entry surfaces — not custody graph nodes.

### Scenario

1. **Link** via `meanly.one` (`client_id=meanly.one`)
2. Create managed Polygon instrument (or full bootstrap)
3. Record `VaultIdentity.id`, binding ids, Polygon address
4. Open vault via **`meanly.ru`** (`client_id=meanly.ru`) with same `sl1e_`
5. Observe instrument set and addresses
6. **Detach** (from either storefront)
7. **Re-link** via `meanly.ru`
8. Observe instrument set again

### Expected

| Check | Expected |
|-------|----------|
| `VaultIdentity` | One row per `sl1e_` — no second vault for `.ru` |
| Bindings | Same set after step 4 and after step 8 |
| Managed Polygon address | Unchanged across `.one` ↔ `.ru` |
| `provider_id` | Always `meanly.vault` |
| Protocol | No migration, no new attachment per region |

**Fail** if switching `meanly.one → meanly.ru` creates a new Polygon address, new
`VaultIdentity`, or new attachment — that means the storefront leaked into the
custody graph.

### Architecture under test

```text
sl1e_abc
    ↓
attachment → meanly.vault
    ↓
VaultIdentity (one)
    ↓
Bindings / managed addresses

meanly.one  ─┐
meanly.ru   ─┴─ entry only (client_id), above this line
```

---

## Evidence capture (when run)

When executed in staging, record in `docs/evidence/` (copy template from
[`level-3-run-TEMPLATE.md`](./level-3-run-TEMPLATE.md) or a dedicated run file):

- `sl1e_` before / after
- `attachment_id` + status after link, detach, re-link
- `VaultIdentity.id` at each step
- binding ids + one managed address hash (not private keys)
- storefront host used per step
- PASS/FAIL per invariant row

Update [evidence README](./README.md) proof sequence when closed.

---

## Relation to other proof layers

| Layer | Document / test | Focus |
|-------|-----------------|-------|
| Level 1 CI | `StorefrontManagedWalletProvisioningTest` | Managed provision mechanics |
| Level 2 CI | `ManagedWalletAttachmentOperationalDrillTest` | Attachment operational |
| Level 3 staging | [managed-wallet-v0-level-3-staging-drill.md](../managed-wallet-v0-level-3-staging-drill.md) | End-to-end managed v0 |
| **Independence drills** | This document | Identity ↔ provider ↔ storefront boundaries |

Independence drills can run after Level 3 staging is green and after Provider
Interface **Phase 3** (real `link()` / `detach()`) is implemented.

---

## Gate for Provider Interface v1

Declare v1 only when:

- [ ] Provider Independence Drill — PASS
- [ ] Storefront Independence Drill — PASS
- [ ] No identity schema changes required to pass either drill
- [ ] Second real provider (or conformance double) exercised in Provider Independence

Until then, remain on **Provider Interface v0** and avoid new identity-summary fields.
