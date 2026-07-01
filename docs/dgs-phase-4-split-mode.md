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

## CI/CD baking (Phase 4.0)

Push to `master` runs `.github/workflows/docker-publish.yml`:

1. Full Pest suite
2. Build root `Dockerfile` and push to GHCR

```text
ghcr.io/vv1ldd/marketplace:latest
ghcr.io/vv1ldd/marketplace:<git-sha>
```

### Coolify switch (lena-1-gcl)

Replace git-build with a pinned GHCR image so Coolify recreate no longer wipes
hot-deployed Phase 4 wiring:

1. Coolify → Meanly API → **Docker Image** (not Git build)
2. Image: `ghcr.io/vv1ldd/marketplace:<sha-from-master-merge>`
3. Post-deploy command: `bash deploy.sh`
4. Keep Phase 4 env from `deploy/regional/env/backend-shared.env.example`

`deploy.sh` runs `php artisan meanly:production-readiness --deploy-gate` after
cache warm-up. This gate checks Providers, DGS Sidecar (when `split|node`), DB,
Queue, and Cache — without blocking on SEO/LLM/Ops gates.

Rollback image tag: previous GHCR SHA. Rollback fulfillment mode:
`WILDFLOW_FULFILLMENT_MODE=http`.
