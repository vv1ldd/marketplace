# ADR 0036: Meanly API / DGS Sidecar Supply Boundary

**Status:** Accepted — **2026-07**

## Context

Phase 4 split-mode colocates PHP financial kernel and Node fulfillment sidecar on
lena-1-gcl next to Meanly API. EzPin vendor credentials must not live in the
commerce API process — only inside the DGS sidecar stack.

ADR 0034 defines RU as `remote_kernel_consumer` to `api.meanly.one`. ADR 0036
refines ONE on lena: colocated kernel URL does **not** mean remote consumer.

## Decision

### Trust zones

| Zone | Knows EzPin? | Role |
|------|--------------|------|
| Storefront (Next.js) | No | Catalog UX, checkout, vault |
| Meanly API | No | Orchestration, ledger, split routing |
| `digital-goods-source` (PHP) | Yes | Financial kernel, catalog projection |
| `dgs-node-sidecar` (Node) | Yes | Fulfillment authority (split/node) |

### Meanly API env (ONE colocated on lena)

```env
DIGITAL_GOODS_SOURCE_URL=http://digital-goods-source:8080/api/v1
DGS_FULFILLMENT_URL=http://dgs-node-sidecar:8091
DGS_SHADOW_INGEST_URL=http://dgs-node-sidecar:8092/shadow/ingest
WILDFLOW_FULFILLMENT_MODE=split
WILDFLOW_SPLIT_FULFILLMENT_PROVIDERS=ezpin-sandbox,ezpin
WILDFLOW_FORCE_DIRECT_SUPPLY=true
WILDFLOW_KERNEL_MODE=http
```

Do **not** set `EZPIN_*` on Meanly API when sidecar holds vendor keys.

### RU API env (remote consumer)

```env
DIGITAL_GOODS_SOURCE_URL=https://api.meanly.one/api/v1
WILDFLOW_KERNEL_MODE=http
# no EZPIN_*, no DGS_FULFILLMENT_URL
```

### Catalog sync (no EzPin on API)

1. **Refresh vendor catalog** — inside sidecar only:
   `docker exec digital-goods-source php artisan wildflow:sync-catalogs wildflow`
2. **Mirror into Meanly DB** — HTTP via kernel URL (no `--pull-upstream` on API):
   `php artisan wildflow:sync-catalogs wildflow --force`

`SupplyContour::usesKernelHttpCatalog()` drives HTTP catalog pull whenever
`DIGITAL_GOODS_SOURCE_URL` + `WILDFLOW_KERNEL_MODE=http`.

`WILDFLOW_FORCE_DIRECT_SUPPLY=true` marks ONE as `direct_supply_authority` in
Ops/readiness even when a colocated kernel URL is configured.

### Storefront fulfillment path

```
Storefront → Meanly API → grant-credit (PHP DGS :8080)
                       → fulfillment/issue (Node :8091) when split allowlist matches
                       → shadow ingest (:8092)
```

Buyer never contacts EzPin or sidecar directly.

## Consequences

- EzPin API changes require sidecar image/env updates only.
- Meanly API compromise does not expose vendor wallet credentials.
- RU outage of ONE blocks RU digital goods (unchanged from ADR 0034).
