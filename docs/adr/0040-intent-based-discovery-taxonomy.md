# ADR 0040: Intent-Based Discovery Taxonomy

## Status

**Accepted** — 2026-07-02

Phase 1 dry-run: 0% `unclassified` on 7182-SKU local baseline. Phase 2: `discovery_intent`
column persisted, storefront API and frontend use seven intent corridors.

**Depends on:** [ADR 0037](0037-digital-entitlement-model.md) (Accepted),
[ADR 0016](0016-market-profile-separation.md) (Accepted),
[ADR 0017](0017-storefront-projection-contract.md) (Accepted).

## Context

The marketplace catalog (~7343 live SKUs on meanly.one) is classified by
**product-type taxonomy** (`console_payment_cards`, `game_wallet_topups`,
`subscriptions`, …). This mirrors warehouse semantics, not buyer intent.

Observed pain:

- **Gaming split across three categories** — Steam, PlayStation, Xbox Game Pass
  appear in different navigation buckets.
- **`subscriptions` as a catch-all** — Spotify, Game Pass, Norton, and iCloud+
  share one corridor despite different primary use.
- **`gift_cards` default sink** — unresolved items fall into `gift_cards`, creating
  a ~3300-SKU retail/gaming mix.
- **Frontend/backend mismatch** — `NEED_GROUPS` (games, food, learning) does not
  map to backend slugs.

ADR 0037 defines purchase **Intent** (`buy:steam-wallet:20:USD`). ADR 0039 defines
**routing** intent for offer selection. This ADR defines **discovery** intent for
browse navigation — a third isolated namespace.

## Decision

### Three intent namespaces (no collision)

| Namespace | Example | Plane | Purpose |
|-----------|---------|-------|---------|
| `discover:*` | `discover:play` | Storefront projection | Why the buyer arrived |
| `buy:*` | `buy:steam-wallet:20:USD` | Knowledge / checkout | What entitlement to resolve |
| `rank:*` / query `?intent=` | `best_offer` | Execution preference | How to rank offers |

Discovery intent MUST NOT be used for checkout resolution or offer routing.

### Intent corridors (Level 1 navigation)

Seven buyer-outcome corridors replace product-type categories on the storefront:

| Corridor | `intent_key` | Buyer question |
|----------|--------------|----------------|
| `play` | `discover:play` | I want to play / top up a game account |
| `stream` | `discover:stream` | I want a content subscription |
| `work` | `discover:work` | I need software, VPN, or a license |
| `shop` | `discover:shop` | I want a retail / e-commerce gift card |
| `pay` | `discover:pay` | I need a prepaid payment instrument |
| `mobile` | `discover:mobile` | I need phone balance or app-store credit |
| `go` | `discover:go` | I need travel, transport, or entertainment |

`local_vouchers` remains internal (not a discovery corridor).

**Subscriptions are not a corridor.** Subscription products route by **primary use**
via brand overrides (Spotify → `stream`, Game Pass → `play`, Office → `work`).

### Two-phase classification

`CanonicalCategoryResolver` performs:

1. **Phase 1 — Legacy product type** (`canonical_category`): unchanged semantics
   for Yandex Market, SEO schema, and channel mappings.
2. **Phase 2 — Discovery intent** (`discovery_intent`): corridor slug derived from
   brand overrides (priority) then legacy category mapping, with `exclude_brands`
   guards on `shop`.

Resolution priority order: `play` → `stream` → `work` → `pay` → `go` → `mobile` → `shop`.

### Cross-linking (App Store / Google Play)

App Store and Google Play cards map to `mobile` (primary use: platform credit).
The `play` corridor exposes a **brand shortcut cross-link** tile
("Оплата на iOS/Android") pointing to filtered `mobile` inventory — without
duplicating brand rows in `play`.

### Configuration contract

All rules live in `config/catalog_taxonomy.php`:

- `intent_corridors` — corridor metadata, `legacy_categories`, `brand_overrides`,
  `exclude_brands`
- `intent_resolution_priority` — override evaluation order
- `cross_links` — navigation shortcuts between corridors
- `discovery_default` — `unclassified` for audit visibility during migration

No hardcoded brand lists in resolver code.

### Legacy compatibility

- `canonical_category` column remains on catalog tables and identities.
- Yandex Market channel mappings continue to use legacy categories.
- Storefront API will expose `discovery_intent` alongside legacy fields during
  transition (Phase 2 of this ADR rollout).

## Consequences

### Positive

- Navigation matches buyer mental models.
- Subscription sprawl eliminated without losing legacy channel data.
- Brand override matrix cleans `gift_cards` gaming contamination (Steam, Razer Gold).
- Dry-run command quantifies migration risk before writes.

### Negative / trade-offs

- Two classification dimensions to maintain until legacy category is retired from UX.
- Brand matrix requires ongoing curation as new providers appear.
- Cross-links add navigation complexity; must stay presentation-only (ADR 0016).

## Implementation phases

| Phase | Scope | Status |
|-------|-------|--------|
| **1** | ADR, config, two-phase resolver, `catalog:reclassify-discovery-intent --dry-run` | Done |
| **2** | Persist `discovery_intent` column, backfill, storefront API + frontend | Done |
| **3** | Liquidity corridors `buy:discover:{slug}` | With ADR 0039 |

## Related

- [ADR 0037](0037-digital-entitlement-model.md) — Intent → Entitlement model
- [ADR 0039](0039-offer-routing-policy.md) — Offer routing (`selectOffer`)
- [ADR 0016](0016-market-profile-separation.md) — Category Priority is presentation
- `config/catalog_taxonomy.php` — taxonomy source of truth
- `CanonicalCategoryResolver` — two-phase resolver
- `catalog:reclassify-discovery-intent` — migration audit command
