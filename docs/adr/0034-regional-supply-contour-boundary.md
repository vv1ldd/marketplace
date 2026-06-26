# ADR 0034: Regional Supply Contour Boundary

**Scope:** Catalog and fulfillment upstream for the RU commerce contour after
[ADR 0032](0032-sovereign-identity-infrastructure-split.md).

## Status

Accepted — **2026-06**

## Context

Identity and ceremony are already split per contour (ADR 0032). Product supply
was still modeled as if every API instance could talk to EZPin/Fazer directly.

For RU sovereignty and operational clarity:

- **ONE (GCP)** remains the **direct supply authority** — EZPin, Fazer, embedded
  kernel, partner API (`/api/v1`).
- **RU (lena)** is a **remote kernel consumer** — it buys from ONE and syncs
  catalogs from ONE. No direct vendor credentials on lena.

## Decision

| Contour | Supply role | Upstream | Catalog sync | Order fulfillment |
|---------|-------------|----------|--------------|-------------------|
| **ONE** | `direct_supply_authority` | EZPin + Fazer | Embedded local aggregation | Local kernel → vendor |
| **RU** | `remote_kernel_consumer` | `api.meanly.one` | HTTP `providers/{provider}/unified-catalog` | HTTP `providers/{provider}/order` |

### RU env (lena, `api.meanly.ru`)

```env
DIGITAL_GOODS_SOURCE_URL=https://api.meanly.one/api/v1
WILDFLOW_KERNEL_MODE=http
```

Partner `api_key`, `client_id`, and `financial_secret` live on the RU
`wildflow` provider record (issued when RU legal entity registers on ONE via
`POST /api/v1/partners/sync`).

### ONE env (GCP, `api.meanly.one`)

Keep `WILDFLOW_KERNEL_MODE=local` (default). Configure EZPin/Fazer credentials
only here. Register RU shop legal entity as kernel partner.

### Guards

When `DIGITAL_GOODS_SOURCE_URL` is set and `WILDFLOW_KERNEL_MODE=http`:

- `app:sync-catalogs --pull-upstream` is rejected (no direct EZPin on RU).
- Ops "Refresh EZPin" action returns 422.
- Ops kernel panel shows `remote_kernel_consumer`, not local supply authorities.

Runtime detection: `App\Support\SupplyContour`.

## Non-decisions

- PlayStation parsed catalog sources remain contour-local (not routed through ONE).
- Pricing, FX, and storefront copy stay market-scoped as today.

## Consequences

- RU outage of ONE blocks RU digital-goods fulfillment (expected coupling).
- Partner balance and credit live on ONE; RU reserves against that partner row.
- ONE must stay the single place EZPin/Fazer secrets are stored.
