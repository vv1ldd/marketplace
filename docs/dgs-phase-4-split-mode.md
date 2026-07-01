# Phase 4: Split-Mode Fulfillment Cutover

Phase 4 separates **financial authority** (PHP `digital-goods-source`) from
**fulfillment authority** (Node `dgs-node-sidecar:8091`).

## Modes

| `WILDFLOW_FULFILLMENT_MODE` | Financial (grant-credit) | Vendor fulfillment |
| :--- | :--- | :--- |
| `http` (default) | PHP | PHP `providers/{p}/order` |
| `split` (Phase 4.0 canary) | PHP | Node for **sandbox only** |
| `node` (Phase 4.2 target) | PHP | Node for all EzPin providers |

Instant rollback: set `WILDFLOW_FULFILLMENT_MODE=http` in Coolify and recreate
Meanly API — no sidecar redeploy required.

## Coolify env (Phase 4.0 sandbox canary)

```env
WILDFLOW_FULFILLMENT_MODE=split
DGS_FULFILLMENT_URL=http://dgs-node-sidecar:8091
DGS_SHADOW_INGEST_URL=http://dgs-node-sidecar:8092/shadow/ingest
DIGITAL_GOODS_SOURCE_URL=http://digital-goods-source:8080/api/v1
WILDFLOW_KERNEL_MODE=http
```

Financial HMAC secret must remain aligned on Meanly API and PHP kernel.

## Readiness gate

```bash
php artisan meanly:production-readiness
```

When `split` or `node` is active, the **DGS Sidecar** gate probes:

- `GET {DGS_FULFILLMENT_URL}/healthcheck` (`:8091`)
- `GET {DGS_SHADOW_INGEST_URL}/healthcheck` (`:8092`)

## Code path

1. `WildflowDriver::createOrder()` always calls PHP `grant-credit`.
2. In `split` mode with sandbox upstream, builds Series 5 issue payload via
   `DgsFulfillmentPayloadBuilder` and POSTs to Node
   `/api/v1/fulfillment/issue`.
3. `getCodes()` reads PIN from the Node response cache on the driver instance
   (no PHP `normalized-cards` poll).

Callers must pass `user_l1_address` in driver `meta` (Gate 8). Redeem and
storefront fulfillment jobs populate this automatically.

## Rollout ladder

- **4.0** — `split`, sandbox providers only (`ezpin-sandbox`, `wildflow-sandbox`)
- **4.1** — `split`, production EzPin after full catalog parity
- **4.2** — `node`, shadow ingest sampled or disabled
- **4.3** — revoke cutover via `POST /api/v1/fulfillment/revoke`
