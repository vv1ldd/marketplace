# Projection Rebuild Registry

## Purpose

The Projection Rebuild Registry makes rebuildability observable and testable.

It implements the invariant from ADR 0013:

> If a projection cannot be rebuilt, it is not a projection.

## Registry Contract

Each operational projection must declare:

- `projection_name`
- `classification`
- `source_transitions`
- `source_authority_decisions`
- `required_anchor_range`
- `rebuild_command`
- `verify_command`
- `last_rebuilt_at`
- `last_verified_at`
- `verification_result`
- `source_revision`
- `anchor_range`

## Initial Registry Candidates

```yaml
balances_projection:
  classification: class_b_rebuildable_projection
  source_transitions:
    - sovereign_ledger:FINANCE_TOPUP
    - sovereign_ledger:FINANCE_DEPOSIT
    - sovereign_ledger:FINANCE_CREDIT_GRANTED
    - sovereign_ledger:FINANCE_HOLD
    - sovereign_ledger:FINANCE_CAPTURE
    - sovereign_ledger:FINANCE_RELEASE_HOLD
  source_authority_decisions:
    - balance_authority_decisions
  required_anchor_range: balance_anchor_range
  rebuild_command: marketplace:rebuild-balances
  verify_command: marketplace:verify-balances
  last_rebuilt_at: null
  last_verified_at: null
  verification_result: unknown
  source_revision: null
  anchor_range: null

buyer_wallet_projection:
  classification: class_b_rebuildable_projection
  source_transitions:
    - wallet_ledger_entries
    - sovereign_ledger:BUYER_WALLET_TOPUP
  source_authority_decisions:
    - wallet_authority_decisions
  required_anchor_range: wallet_anchor_range
  rebuild_command: marketplace:rebuild-buyer-wallets
  verify_command: marketplace:verify-buyer-wallets
  last_rebuilt_at: null
  last_verified_at: null
  verification_result: unknown
  source_revision: null
  anchor_range: null

marketplace_orders_projection:
  classification: class_b_rebuildable_projection
  source_transitions:
    - order_transitions
    - sovereign_ledger:ORDER_CREATED
    - sovereign_ledger:ORDER_CAPTURED
    - sovereign_ledger:ORDER_FULFILLED
    - sovereign_ledger:ORDER_REFUNDED
  source_authority_decisions:
    - order_authority_decisions
  required_anchor_range: order_anchor_range
  rebuild_command: marketplace:rebuild-orders
  verify_command: marketplace:verify-orders
  last_rebuilt_at: null
  last_verified_at: null
  verification_result: unknown
  source_revision: null
  anchor_range: null

catalog_search_projection:
  classification: class_b_rebuildable_projection
  source_transitions:
    - provider_catalog_transitions
    - canonical_product_identity_transitions
  source_authority_decisions:
    - catalog_authority_decisions
  required_anchor_range: catalog_anchor_range
  rebuild_command: marketplace:rebuild-catalog-search
  verify_command: marketplace:verify-catalog-search
  last_rebuilt_at: null
  last_verified_at: null
  verification_result: unknown
  source_revision: null
  anchor_range: null
```

## Verification Rules

A projection is healthy only when:

- its source transition range is complete;
- required authority decisions are available;
- required anchor ranges verify;
- rebuild command completes successfully;
- verify command proves rebuilt state matches expected derived state;
- `last_verified_at` is fresh enough for the projection's continuity class.

## Failure States

- `unknown`: projection has no recorded verification.
- `stale`: verification exists but is older than the freshness target.
- `failed`: rebuild or verification failed.
- `source_gap`: required transitions are missing.
- `authority_gap`: required decisions are missing.
- `anchor_gap`: required anchors are missing or unverifiable.
- `healthy`: rebuild and verification recently succeeded.

## Continuity Readiness Impact

Projection rebuild readiness contributes to Recovery Confidence.

If a projection is stale or failed, infrastructure may still be healthy, but continuity is degraded.
