# Projection Inventory and Rebuild Matrix

## Purpose

This inventory turns projection readiness from an inferred health signal into an explicit continuity contract.

The current DR exercise proved that the marketplace can boot from a restored database copy and reach a continuity preflight `GO` after rebuilding the balance projection and publishing writer authority heartbeat. The remaining unknown is the projection layer: which tables are deterministic projections, which are operational facts, and which are not rebuildable from a durable source of truth.

## DR Stage 2 Evidence

Observed on the isolated `marketplace-dr` application against `marketplace_dr_test`:

```yaml
dr_stage_2:
  database_seed: VERIFIED
  application_boot: VERIFIED
  continuity_recovery: VERIFIED
  isolated_failover: VERIFIED
  production_failover: NOT_TESTED
  app:
    name: marketplace-dr
    db: marketplace_dr_test
    public_domains: none
    status: running:healthy
  continuity:
    ledger_continuity: healthy
    balance_projection: healthy_after_rebuild
    writer_authority: healthy_after_heartbeat
    failover_preflight: GO
    recovery_confidence: 83
```

The DR run discovered and repaired one projection mismatch in the isolated copy:

```yaml
incident_discovered:
  component: balance_projection
  symptom:
    legal_entity_id: 3
    stored_available_balance: 1000000
    expected_available_balance: 0
  recovery:
    action: marketplace:rebuild-balances
    scope: marketplace_dr_test only
    result: resolved
```

## Classification

```yaml
classes:
  class_a_authoritative_state:
    meaning: Source-of-truth state that must not be discarded or treated as rebuildable without a higher authority.
  class_b_rebuildable_projection:
    meaning: Derived state with declared source, rebuild command, and verification command.
  class_c_runtime_projection:
    meaning: Derived at request/runtime or cacheable, but not an authoritative table.
  class_d_observability_log:
    meaning: Append-only telemetry/audit/event data. Useful for analysis, not required for serving critical continuity.
  class_e_unknown_or_non_rebuildable:
    meaning: Materialized state whose source or deterministic rebuild path is missing.
```

## Registered Projections

These projections are currently known to `projection_rebuild_registry`.

| Projection | Tables / Columns | Source Of Truth | Rebuild Command | Verify Command | Deterministic | Readiness Weight | Current Evidence | Classification |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `balances_projection` | `legal_entities.available_balance`, `reserved_balance`, `balance`, `native_token_balance`, `native_token_reserved` | `sovereign_ledger` plus `L1StateService` reconstruction | `marketplace:rebuild-balances` | `marketplace:verify-balances` | Yes | Critical | Verified healthy in DR after rebuild | `class_b_rebuildable_projection` |
| `buyer_wallet_projection` | `wallet_accounts.available_minor`, `reserved_minor` | `wallet_ledger_entries` replay by user/asset | `marketplace:rebuild-buyer-wallets` | `marketplace:verify-buyer-wallets` | Yes for ledger-covered directions | Critical | Commands implemented; registry evidence is written on verify | `class_b_rebuildable_projection` |
| `marketplace_orders_projection` | Denormalized order financial fields and terminal progress materialization | `orders`, `order_items`, `currencies` | `marketplace:rebuild-orders` | `marketplace:verify-orders` | Yes for financial/progress projection fields; order rows remain authoritative | Critical | Commands implemented; registry evidence is written on verify | `class_b_rebuildable_projection` |
| `canonical_product_identity_projection` | `canonical_product_identities`, `canonical_product_identity_sources` | `provider_products`, seller `products`, approved `canonical_product_identity_overrides` | `catalog:rebuild-identities` | `catalog:verify-identities` | Mostly yes; override policy affects output | High | Registered as concrete catalog projection | `class_b_rebuildable_projection` |
| `canonical_product_search_profile_projection` | `canonical_product_search_profiles` | `canonical_product_identities`, approved curation overrides, taxonomy/category resolver | `search-profile:rebuild` | `search-profile:verify` | Yes for current builder version | High | Registered as concrete catalog projection | `class_b_rebuildable_projection` |
| `catalog_search_projection` | Compatibility aggregate for catalog search readiness | `canonical_product_identity_projection`, `canonical_product_search_profile_projection` | `marketplace:rebuild-catalog-search` | `marketplace:verify-catalog-search` | Delegates to concrete projections | High | Split aggregate alias; no longer the only catalog readiness row | `class_b_aggregate_projection` |

## Discovered Rebuildable Projections Outside Registry

These projections have rebuild code or deterministic derivation, but are not yet registered as first-class projection readiness entries.

| Projection | Tables | Source Of Truth | Rebuild Command | Verification Command | Deterministic | Readiness Weight | Failover Required | Gap |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `commerce_entity_graph_projection` | `commerce_entities`, `commerce_entity_links`, `commerce_entity_metrics` | `canonical_product_identities`, identity sources, `demand_gaps`, `opportunity_cases` | `commerce:rebuild-entities` | None | Mostly yes, depends on upstream demand/opportunity data | Medium | Not required for basic checkout, required for discovery/ops intelligence | Add verify command checking entity/source/link coverage and metric freshness |
| `intent_liquidity_graph_projection` | `intent_liquidity_nodes`, `intent_liquidity_corridors` | `commerce_entities`, `commerce_entity_metrics`, `currencies` | `intent-liquidity:rebuild` | None | Mostly yes | Medium | Not required for basic checkout, required for intent routing/readiness intelligence | Add verify command checking node/corridor coverage and route readiness |
| `demand_gap_projection` | `demand_gaps` | `catalog_search_logs`, `order_search_attributions`, legacy `orders.search_log_id` fallback | `demand:calculate-gaps` | Covered indirectly by `meanly:launch-readiness`; no dedicated verify command | Mostly yes for current analytics inputs | Medium | Not required for basic checkout, required for discovery/opportunity intelligence | Add stale-row pruning and `demand:verify-gaps --json` |
| `warehouse_stock_projection` | `warehouse_stocks` | `product_inventory` availability/status grouped by warehouse/product, plus external stock sync jobs | Event-driven recalculation from `ProductInventory::saved`; no full command | None | Partially; external channel stock rows complicate source authority | High for stock-sensitive fulfillment | Required for inventory-backed checkout and ops stock views | Add full reconciliation command and separate local vs external stock authority |
| `writer_authority_readiness_projection` | `writer_authority_readiness` | Runtime writer heartbeat/promote command and configured writer region/epoch | `marketplace:writer-authority:heartbeat`, `marketplace:writer-authority:promote` | `marketplace:db-continuity-readiness --json` | Yes for heartbeat freshness | Critical | Yes | Keep as its own readiness check, not a rebuildable data projection |

## Runtime Projections

These derive output at request time or through cache. They should not block DR rebuild unless their sources are missing.

| Projection | Surface | Source Of Truth | Rebuild Command | Verification | Classification |
| --- | --- | --- | --- | --- | --- |
| `public_pricing_projection` | Display prices and labels | `products.price_rub`, currency configuration/rates, `FinanceService` | None; runtime calculation | Covered by app boot/smoke and pricing unit/feature tests | `class_c_runtime_projection` |
| `storefront_homepage_projection` | Homepage categories, featured blocks, grouped product cards | Canonical identities, products, search profiles, runtime cache | Cache clear via `php artisan optimize:clear`; underlying sources rebuild separately | HTTP smoke plus catalog/search projection verification | `class_c_runtime_projection` |
| `seller_offer_ranking_projection` | Ranked seller offers | Products, warehouse stock, order history, sales channel flags | None; runtime calculation | Add targeted feature test/smoke query | `class_c_runtime_projection` |

## Observability And Event Logs

These are not projections in the strict rebuild sense. They are durable event/telemetry stores or outbox surfaces.

| Surface | Tables | Source Of Truth | Rebuildability | Readiness Impact |
| --- | --- | --- | --- | --- |
| `sovereign_ledger` | `sovereign_ledger` | Ledger itself | Not rebuildable from projection state; must be preserved | Critical; hash chain verified in DR |
| `marketplace_transition_outbox` | `marketplace_transition_outbox` | Accepted transition/outbox records | Not rebuildable unless regenerated from ledger and transition metadata | High; anchors pending currently keep readiness degraded |
| `meanly_analytics_events` | `meanly_analytics_events` | Event stream itself | Not required for serving checkout; may mirror business checkpoints to ledger | Low/medium; preserve for analytics and audit |
| `mutation_guard_entries` | `mutation_guard_entries` | Runtime mutation guard attempts | Operational telemetry/idempotency surface | Medium during rollout; not a business projection |
| `catalog_search_logs`, `order_search_attributions`, `token_metering_events` | Analytics and attribution tables | Event capture at runtime | Not fully rebuildable | Low/medium depending on analytics requirements |

## Authoritative State, Not Projections

These tables may contain derived columns, but the rows themselves are authoritative operational state. Do not classify the whole table as rebuildable.

| Table | Reason |
| --- | --- |
| `orders`, `order_items` | Core transaction records. Individual status/progress fields may be projection-like, but the order records are business facts. |
| `products`, `provider_products`, `wildflow_catalogs` | Catalog facts from sellers/providers. Canonical identity/search projections derive from them. |
| `wallet_ledger_entries` | Buyer wallet source ledger. |
| `wallet_accounts` | Contains account identity plus projected balances; needs split treatment. |
| `product_inventory`, `warehouse_stocks`, `tokenized_vouchers` | Inventory/voucher state is operational state unless and until every mutation is captured as replayable inventory events. |
| `legal_entities` | Legal entity rows are authoritative; balance columns are rebuildable projections. |

## Projection Readiness Formula

The current readiness logic counts registry rows as `healthy`, `unknown`, `stale`, or `failed`. That is useful but too coarse because not every projection has the same continuity weight.

Target formula:

```yaml
projection_readiness:
  critical:
    required_verified_percent: 100
    projections:
      - balances_projection
      - buyer_wallet_projection
      - marketplace_orders_projection
      - writer_authority_readiness_projection
  high:
    required_verified_percent: 100
    projections:
      - canonical_product_identity_projection
      - canonical_product_search_profile_projection
  medium:
    required_verified_percent: 80
    projections:
      - demand_gap_projection
      - commerce_entity_graph_projection
      - intent_liquidity_graph_projection
  high_inventory:
    required_verified_percent: 100
    projections:
      - warehouse_stock_projection
  low:
    required_verified_percent: 0
    projections:
      - analytics_and_attribution_surfaces
```

Initial readiness interpretation after DR Stage 2:

```yaml
projection_readiness_current_after_registry_completion_patch:
  status: DEGRADED until all registered commands have been run on the target environment
  verified:
    - balances_projection
    - writer_authority_readiness_projection
  registry_entries_now_verifiable:
    - buyer_wallet_projection
    - marketplace_orders_projection
    - canonical_product_identity_projection
    - canonical_product_search_profile_projection
    - catalog_search_projection
  discovered_not_registered:
    - demand_gap_projection
    - commerce_entity_graph_projection
    - intent_liquidity_graph_projection
    - warehouse_stock_projection
  blocking_reason:
    - target environment must execute the new rebuild/verify commands to write registry evidence
    - outbox anchors are pending
```

## Required Implementation Work

1. Completed in registry contract patch:
   - `marketplace:rebuild-buyer-wallets`
   - `marketplace:verify-buyer-wallets`
   - `marketplace:rebuild-orders`
   - `marketplace:verify-orders`
   - `catalog:verify-identities`
   - `search-profile:verify`
   - `marketplace:rebuild-catalog-search`
   - `marketplace:verify-catalog-search`

2. Still open for weighted formula:
   - optionally register `commerce_entity_graph_projection` and `intent_liquidity_graph_projection`
   - add verify commands for medium/high inventory projections
   - define exact critical/high/medium weights in code

3. Remaining rebuild/verify candidates:
   - `commerce:rebuild-entities`
   - new `commerce:verify-entities --json`
   - `intent-liquidity:rebuild`
   - new `intent-liquidity:verify --json`
   - `demand:calculate-gaps`
   - new `demand:verify-gaps --json`
   - new `inventory:reconcile-warehouse-stocks`
   - new `inventory:verify-warehouse-stocks --json`

4. Replace unweighted registry scoring with weighted readiness:
   - critical unknown or failed means `UNHEALTHY`;
   - high unknown keeps `DEGRADED`;
   - medium unknown lowers confidence but does not block basic checkout failover;
   - low observability gaps should not block failover unless explicitly required.

5. Add outbox anchor recovery policy:
   - distinguish `anchor_pending_but_replayable` from `anchor_missing_or_failed`;
   - make anchor gap severity depend on transition class.

## Next Gate

Projection readiness becomes `VERIFIED` when:

```yaml
projection_readiness_verified_gate:
  critical_verified: 100%
  high_verified: 100%
  medium_verified: >=80%
  no_failed_projection: true
  no_source_gap_for_critical: true
  no_authority_gap_for_critical: true
  anchor_policy_evaluated: true
```

Only after this gate should the system move to a controlled production failover rehearsal with measured RTO.
