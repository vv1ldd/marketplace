# ADR 0036: Meanly API / DGS Sidecar Supply Boundary

**Status:** Accepted — **2026-07** (rev. 2 — RU edge sterilization)

## Context

Phase 4 split-mode separates financial authority (PHP kernel) from fulfillment
authority (Node sidecar). ADR 0034 defines RU as `remote_kernel_consumer` to
ONE. ADR 0036 refines trust zones and adds **lena-1-gcl as RU sovereign edge**
without EzPin vendor credentials.

## Decision

### Topology

```
ONE Authority (meanly.one)          lena-1-gcl RU Edge (meanly.ru)
├─ DGS + EzPin (GCP / authority)      ├─ DGS sterile (no EzPin)
├─ Node fulfillment + EzPin           ├─ Node shadow-only (DGS_EDGE_MODE)
└─ api.meanly.one                     └─ api.meanly.ru → local DGS → ONE
```

### Trust zones

| Zone | EzPin vendor keys? | Role |
|------|-------------------|------|
| Storefront | No | Catalog UX, checkout, vault |
| Meanly API | No | Orchestration; inter-service HMAC only |
| ONE DGS (authority) | Yes | Financial kernel + vendor fulfillment |
| lena DGS (edge) | **No** | Catalog mirror + grant-credit relay for RU |
| lena Node (edge) | **No** | Shadow ingest; `EDGE_FULFILLMENT_DELEGATED_TO_ONE` |

**Inter-service auth** (`DIGITAL_GOODS_SOURCE_FINANCIAL_SECRET`, platform token)
is not EzPin — it stays on Meanly API and edge DGS for HMAC kernel access.

### Meanly API env (ONE authority)

```env
DIGITAL_GOODS_SOURCE_URL=http://digital-goods-source:8080/api/v1
DGS_FULFILLMENT_URL=http://dgs-node-sidecar:8091
WILDFLOW_FULFILLMENT_MODE=split
WILDFLOW_FORCE_DIRECT_SUPPLY=true
# EZPIN_* on authority DGS/sidecar only — not on Meanly API
```

### Meanly API env (RU edge — meanly.ru)

```env
DIGITAL_GOODS_SOURCE_URL=http://digital-goods-source:8080/api/v1
WILDFLOW_KERNEL_MODE=http
WILDFLOW_FORCE_DIRECT_SUPPLY=true
# no EZPIN_*, no DGS_FULFILLMENT_URL (fulfillment via ONE)
```

### lena sterile sidecar `.env`

```env
DGS_EDGE_MODE=true
CATALOG_SOURCE_URL=https://api.meanly.one/api/v1/providers/ezpin/unified-catalog?include_inactive=1
CATALOG_SOURCE_AUTH_TOKEN=<APP_WILDFLOW_TOKEN from ONE>
DIGITAL_GOODS_SOURCE_FINANCIAL_SECRET=<aligned with RU API>
```

No `EZPIN_*` on lena.

### Catalog sync sequence

1. **ONE Authority** — `php artisan wildflow:sync-catalogs wildflow --force` (with EzPin on authority DGS).
2. **lena edge DGS** — `docker exec digital-goods-source php artisan wildflow:sync-catalogs wildflow` (HTTP pull from ONE unified-catalog).
3. **Meanly RU API** — `php artisan wildflow:sync-catalogs wildflow --force` (HTTP via local sterile kernel).
4. **Meanly ONE API** — sync from authority DGS (not from lena edge).

### Edge Node behavior

When `DGS_EDGE_MODE=true`, `POST /api/v1/fulfillment/issue` returns `503`
`EDGE_FULFILLMENT_DELEGATED_TO_ONE`. Healthcheck and shadow ingest (`:8092`)
remain available.

## Consequences

- EzPin wallet keys exist in one place only (ONE authority).
- lena compromise does not expose vendor credentials.
- RU catalog is a replica of ONE; monitor `401/403` on catalog pull.
- Split fulfillment on lena edge is intentionally disabled; RU orders fulfill via ONE.
