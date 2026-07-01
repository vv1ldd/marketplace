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
- **Pre-GCP transitional:** while meanly.one still runs on lena with a sterile edge
  DGS, set `WILDFLOW_FULFILLMENT_MODE=http` on the Meanly API to avoid split → Node
  `503 EDGE_FULFILLMENT_DELEGATED_TO_ONE` noise. Keep `DGS_SHADOW_INGEST_URL` for parity
  radar. Live vendor redeem resumes when ONE Authority moves to GCP with EzPin.

## GCP Authority cutover checklist

Operational sequence when ONE moves from lena to GCP. Catalog sync is the deepest
heartbeat — a successful pull with matching item counts proves auth, connectivity,
and serialization parity.

### Phase A — GCP Authority setup

- [x] Deploy PHP DGS + Node sidecar on GCP with `EZPIN_*` (authority only).
- [x] Wire Meanly API to `dgs-authority` (no `EZPIN_*` on API container).
- [x] Align `DIGITAL_GOODS_SOURCE_FINANCIAL_SECRET` and platform token across API + DGS.
- [ ] Point `api.meanly.one` DNS to GCP load balancer (deferred — authority on `lena-1-gcl` for now).
- [x] Authority catalog populated (7343 SKU baseline; EzPin direct pull — follow-up).
- [x] Record baseline SKU count (~7343) for Radar parity.

**Phase A deploy (lena-1-gcl):**

```bash
bash ops/digital-goods-sidecar/deploy-gcp-authority.sh
```

Two stacks on host: `~/digital-goods-authority` (EzPin) + `~/digital-goods-sidecar` (sterile edge).

### Phase B — Auth handshake (ONE ↔ lena edge)

- [ ] Issue or rotate shared `X-Auth-Token` (`APP_WILDFLOW_TOKEN` / `CATALOG_SOURCE_AUTH_TOKEN`).
- [ ] Update lena edge `.env`: `CATALOG_SOURCE_URL=https://api.meanly.one/api/v1/providers/ezpin/unified-catalog?include_inactive=1`.
- [ ] Verify `GET unified-catalog` returns `200` with token (not `401`/`403`).
- [ ] Run lena edge sync: `docker exec digital-goods-source php artisan wildflow:sync-catalogs wildflow`.
- [ ] Confirm edge SKU count matches authority baseline (±0 tolerance for full sync).

### Phase C — Fulfillment cutover

- [x] **Dry-run:** authority Node `:8091` accepts fulfillment (not `503`; EzPin path active).
- [x] **Split enabled:** `WILDFLOW_FULFILLMENT_MODE=split` on meanly.one → `dgs-authority-node:8091`.
- [x] **Canary scope:** `WILDFLOW_SPLIT_FULFILLMENT_PROVIDERS=ezpin-sandbox` (expand to `ezpin` after funded redeem tests).
- [x] Deploy-gate `READY` with authority sidecar healthchecks.
- [ ] Live `ezpin` in allowlist + funded redeem smoke (pre-prod scope).
- [ ] **meanly.ru (lena):** remain `http` + sterile DGS; no `DGS_FULFILLMENT_URL` traffic.
- [ ] **lena Node:** `DGS_EDGE_MODE=true` — fulfillment stays `503 EDGE_FULFILLMENT_DELEGATED_TO_ONE`.

```bash
# Phase C dry-run (sandbox allowlist)
bash ops/digital-goods-sidecar/phase-c-split-dry-run.sh
```

### Phase D — Post-cutover verify (one command)

From dev machine:

```bash
bash ops/digital-goods-sidecar/deploy-lena-1-gcl.sh
```

Or on lena after any edge deploy — see `post-sync authority-link verify` in that script.

### Rollback

| Failure | Action |
|---------|--------|
| GCP catalog pull fails | Revert DNS to lena authority; re-sync edge from lena ONE |
| Split redeem fails on GCP | `WILDFLOW_FULFILLMENT_MODE=http` on meanly.one (instant, no sidecar redeploy) |
| Edge token mismatch | Re-align `CATALOG_SOURCE_AUTH_TOKEN` with `APP_WILDFLOW_TOKEN` on ONE |

### Monitoring (no synthetic pings during stabilization)

- **SKU count drift** on edge vs authority baseline → upstream authority issue.
- **`401`/`403`** on `api.meanly.one` unified-catalog → auth handshake break.
- **Shadow ingest silence** on lena `:8092` → `DgsShadowIngestService` or http-fulfillment path change.
