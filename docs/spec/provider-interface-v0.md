# Provider Interface v0

**Status:** Draft — normative for Phase 2+ implementation  
**Version:** `provider-interface.v0`  
**Architecture:** [ADR 0027: Identity Attachments and Provider Ownership](../adr/0027-identity-attachments-and-provider-ownership.md)

## Purpose

After ADR-0027, SL1 exposes a **real external contract** between the identity
layer and custody/settlement providers:

```text
SL1 Identity  ↔  Provider Interface  ↔  Wallet Provider (e.g. Meanly Vault)
```

Before this boundary, Meanly Vault was effectively an internal implementation
detail of the marketplace stack. This spec defines the **minimal protocol
surface** so Vault becomes the first `WalletProvider` implementation — not a
special case baked into simple-l1.

**Rule:** Implementations MUST follow this spec before `/api/identity/summary`
fields are extended. Do not freeze API shapes in code ahead of this document.

## Non-goals (v0)

- Provider capability matrices (`supports_bitcoin`, `supports_ton`, …)
- Instrument catalogs on the identity summary
- Network labels, addresses, or balances in attachment records
- Payment execution, signing, or key import on the identity layer
- Provider ranking or marketplace curation inside the protocol registry

Those belong to **providers** or **provider UIs**, not to SL1 identity schema.

## Ownership recap

```text
sl1e_
  ↓
Attachment           ← identity owns (link / unlink / enumerate)
  ↓
Provider Instrument  ← provider owns
  ↓
Settlement           ← provider owns
```

Identity treats each provider as a **black box**. The protocol knows *that* a
provider is linked, not *how* it settles.

## Rollout phases

| Phase | Deliverable | Notes |
|-------|-------------|-------|
| **1** | This spec | Registry, attachment, summary, link sequence |
| **2** | `GET /api/identity/summary` | `available_providers` + `linked_providers` only |
| **3** | Provider link flow | Audience-bound SL1E proof → attachment creation |

Phase 2 MUST NOT add fields beyond what this spec defines without a spec revision.

---

## 1. Provider registry

The registry answers: **which providers can be linked?** It does not answer
**which provider is best** or **what rails they support**.

### Registry entry (v0)

```json
{
  "provider_id": "meanly.vault",
  "label": "Meanly Vault",
  "link_url": "https://meanly.one",
  "status": "available"
}
```

| Field | Required | Description |
|-------|----------|-------------|
| `provider_id` | yes | Stable id (`<vendor>.<product>`), lowercase, dotted |
| `label` | yes | Human-readable name for identity UI |
| `link_url` | yes | Where the user starts link or opens the provider app |
| `status` | yes | `available` \| `unavailable` \| `deprecated` |

### Forbidden in registry (v0)

```json
{
  "supports_bitcoin": true,
  "supports_ton": true,
  "networks": ["polygon", "solana"]
}
```

That is **settlement knowledge**. Identity MUST NOT encode it in the registry.

### `available_providers` vs “recommended”

The identity summary exposes:

```json
{
  "available_providers": [ /* registry entries */ ]
}
```

The protocol registry is **neutral**. It lists providers that are configured
for this deployment — not a curated marketplace.

If a surface wants to highlight first-party options (e.g. Meanly Vault), that
is **UI policy** (badge, sort order, copy) — not a `recommended_providers`
field in the protocol contract. Multiple providers (Meanly, Coinbase, Wise)
coexist without the protocol choosing winners.

### Registry source (implementation note)

v0 MAY be a static file or env-driven list in simple-l1. Dynamic discovery is
out of scope. The registry MUST remain replaceable without changing attachment
or summary shapes.

---

## 2. Attachment

The attachment is the **central object** of the identity ↔ provider boundary.

An attachment means: *this `sl1e_` has an active relationship with this
`provider_id`.* It does **not** describe instruments, networks, or balances.

### Attachment record (v0)

```json
{
  "attachment_id": "att_01HXYZ…",
  "provider_id": "meanly.vault",
  "status": "active",
  "linked_at": "2026-06-24T12:00:00Z"
}
```

| Field | Required | Description |
|-------|----------|-------------|
| `attachment_id` | yes | Opaque id unique within identity scope |
| `provider_id` | yes | Matches registry `provider_id` |
| `status` | yes | `active` \| `suspended` \| `revoked` |
| `linked_at` | yes | ISO 8601 UTC |

Optional (v0, still identity-safe):

| Field | Description |
|-------|-------------|
| `unlinked_at` | Set when `status` is `revoked` |
| `label` | Provider-supplied display name for this link (e.g. “Personal vault”) |

### Forbidden on attachment

```json
{
  "wallet_address": "0x…",
  "network": "polygon",
  "instrument_id": "inst_123",
  "balances": []
}
```

The identity layer MUST NOT store settlement identifiers on the attachment.
Provider-side `IdentityBinding` rows map to attachments internally; that mapping
is **provider implementation**, not protocol schema.

### Identity operations (v0)

| Operation | Owner | Description |
|-----------|-------|-------------|
| `link` | identity + provider | Create attachment after mutual trust |
| `unlink` | identity or provider | Set `status` to `revoked` |
| `enumerate` | identity | List attachments for `sl1e_` |

`link` is as fundamental as `authenticate` in the user lifecycle:

```text
authenticate()  →  obtain sl1e_
link(provider)  →  attachment
enumerate()     →  linked_providers[]
open(provider)  →  provider UI (custody)
```

---

## 3. Provider summary (thin)

Summary is a **cached headline** for the identity dashboard — not a dump of the
Vault. Detailed instruments MUST be fetched from the provider after the user
opens it.

### Summary object (v0)

```json
{
  "provider_id": "meanly.vault",
  "attachment_id": "att_01HXYZ…",
  "status": "active",
  "instrument_count": 3,
  "display_balance": "USDC 120.40 + 2 more"
}
```

| Field | Required | Description |
|-------|----------|-------------|
| `provider_id` | yes | Provider id |
| `attachment_id` | yes | Links summary to attachment |
| `status` | yes | Mirrors attachment or provider-reported health |
| `instrument_count` | yes | Non-negative integer; provider-defined what counts as an instrument |
| `display_balance` | no | Opaque human string for dashboard; not parseable by protocol |

### Forbidden in summary (v0)

- Per-asset breakdowns
- Network names
- Addresses
- Capability flags
- Transaction history

### Layering (do not collapse)

```text
identity summary     →  linked_providers[] + thin summary per link
provider API         →  instruments, balances, send/receive
provider UI          →  full custody experience
```

If simple-l1 starts returning instrument catalogs or chain balances in
identity summary, **Vault leaks back into the protocol** — treat as spec violation.

### Fetch model

1. Identity node stores attachments locally (or syncs attachment index).
2. Optionally, identity node calls provider `summary(sl1e, attachment_id)` with
   an audience-bound proof — rate-limited, cacheable.
3. User taps **Open** → browser navigates to `link_url` / deep link; provider
   loads full state with its own session.

Separate provider endpoints for instruments (out of v0 identity spec):

```text
GET provider/instruments   (provider-authenticated)
GET provider/balances      (provider-authenticated)
```

---

## 4. Link sequence (Phase 3)

Linking MUST bind the attachment to **this identity** and **this provider
audience** — not a generic OAuth session.

### Sequence

```text
User          Identity UI       pass.simplelayer.one       Provider (Vault)
  |                |                    |                        |
  |-- Sign in ---->|                    |                        |
  |                |-- Connect -------->|                        |
  |                |<-- SL1E proof -----|                        |
  |                |   (aud: sl1e_,      |                        |
  |                |    provider_id)    |                        |
  |-- Link Vault ->|                    |                        |
  |                |-- redirect link_url + identity_assertion ---->|
  |                |                    |                        |
  |                |                    |     Provider verifies  |
  |                |                    |     proof, creates     |
  |                |                    |     vault session      |
  |                |<-- callback or poll attachment_id ----------|
  |                | stores attachment  |                        |
  |<-- dashboard --|                    |                        |
```

### Requirements

- **Audience-bound proof:** SL1E (or Connect identity proof) MUST include
  `provider_id` and intended `link_url` host in audience or custom claims.
- **One attachment per provider per identity (v0):** Relinking updates the same
  logical attachment or revokes and creates new — provider defines; identity
  exposes one `active` row per `provider_id`.
- **No secrets on identity node:** Private keys, seeds, and provider API keys
  never pass through simple-l1.

### Unlink

- User or provider MAY revoke; identity sets `status: revoked`.
- Provider MUST stop honoring stale proofs for new sessions; existing
  instruments remain provider-owned until user deletes them in provider UI.

---

## 5. Target: `GET /api/identity/summary` (Phase 2)

Phase 2 replaces ad-hoc fields (`default_provider`, multi-asset `balances`) with:

```json
{
  "schema": "simple-l1.identity.summary.v1",
  "protocol": "simple-l1",
  "status": "active",
  "identity": {
    "entity_l1_address": "sl1e_…",
    "handle": "@user",
    "active_keys": 1
  },
  "native": {
    "asset": "SL",
    "amount": "1000.00",
    "kind": "native_protocol_ledger"
  },
  "available_providers": [
    {
      "provider_id": "meanly.vault",
      "label": "Meanly Vault",
      "link_url": "https://meanly.one",
      "status": "available"
    }
  ],
  "linked_providers": [
    {
      "attachment": {
        "attachment_id": "att_01…",
        "provider_id": "meanly.vault",
        "status": "active",
        "linked_at": "2026-06-24T12:00:00Z"
      },
      "summary": {
        "provider_id": "meanly.vault",
        "attachment_id": "att_01…",
        "status": "active",
        "instrument_count": 3,
        "display_balance": "USDC 120.40 + 2 more"
      }
    }
  ],
  "operations": []
}
```

### Native SL only

`native` / protocol ledger balances MUST NOT include BTC, ETH, or other
provider instruments. Only SL (or future **protocol-native** assets explicitly
added by ADR), never `external_projection`.

### Deprecations (Phase 2)

| Remove / deprecate | Replace with |
|--------------------|--------------|
| `default_provider` | `available_providers` |
| `providers` (flat) | `linked_providers` |
| `identity_surface.default_provider` | registry entry in `available_providers` |
| Multi-asset `balances` | `native` + provider summaries |

---

## 6. First implementation: Meanly Vault

| Concern | Owner |
|---------|--------|
| `provider_id` | `meanly.vault` |
| Registry `link_url` | `https://meanly.one` (or market-specific vault entry) |
| Attachment persistence | Vault / marketplace DB (maps to `VaultIdentity` + SL1E subject) |
| Thin summary | Marketplace API behind provider auth |
| Instruments & execution | Existing `StorefrontWalletService`, settlement adapters |

Meanly MUST NOT require protocol-specific fields beyond attachment + thin
summary. Any Meanly-only logic stays in the provider adapter, not in
simple-l1 registry code paths.

---

## 7. Anti-patterns (spec violations)

| Anti-pattern | Why |
|--------------|-----|
| `supports_*` in registry | Settlement leakage into identity |
| BTC/ETH in identity `balances` | Implies protocol custody |
| `recommended_providers` in protocol API | Protocol becomes provider marketplace |
| Full instrument list in identity summary | Vault UI duplicated in simple-l1 |
| `sendTransaction` on identity API | Execution belongs to provider |
| Hard-coded Meanly branches in simple-l1 | Use `provider_id` + registry |

---

## 8. Acceptance criteria (v0 complete)

- [ ] Spec approved (this document)
- [ ] Phase 2: `/api/identity/summary` matches §5
- [ ] Phase 2: UI reads `available_providers` + `linked_providers` only
- [ ] Phase 3: Link flow creates attachment with audience-bound proof
- [ ] Meanly Vault link/unlink/summary without simple-l1 knowing networks
- [ ] Second provider can be added to registry without schema change

---

## Related documents

- [ADR 0026: Settlement Instrument Sovereignty](../adr/0026-settlement-instrument-sovereignty.md)
- [ADR 0027: Identity Attachments and Provider Ownership](../adr/0027-identity-attachments-and-provider-ownership.md)
- Marketplace implementation: `StorefrontWalletService`, `ManagedWalletProvisioner`, settlement adapters (provider side only)
