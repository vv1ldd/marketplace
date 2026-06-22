# Meanly production on Sovereign Coolify (VPS)

One git repo (`marketplace`). One Laravel API. Regional storefronts are separate Coolify apps with different build env.

Mac dev stays on `scripts/dev-tunnel.sh` — not on the Coolify installer.

## Domain map

| Service | Domain | Coolify app | Notes |
|---------|--------|-------------|-------|
| Control plane | `ops.meanly.one` | (bundled with Sovereign install) | SL1 login for deploy |
| Simple L1 identity | `identity.meanly.one` | bundled `simple-l1` container | SL1e issuer + runtime |
| API | `api.meanly.one` | `meanly-api` | Laravel + queue worker |
| Storefront global | `meanly.one` | `meanly-frontend-global` | `NEXT_PUBLIC_MARKETPLACE_REGION=global` |
| Storefront RU | `meanly.ru` | `meanly-frontend-ru` | `NEXT_PUBLIC_MARKETPLACE_REGION=ru` |

DNS: all public hostnames → VPS public IP (or Cloudflare proxied A records).

## Step 1 — Sovereign Coolify + Simple L1

On a fresh Linux VPS (as root):

```bash
export SOVEREIGN_HOST_DOMAIN=ops.meanly.one
export SOVEREIGN_IDENTITY_DOMAIN=identity.meanly.one
export SOVEREIGN_APP_SCHEME=https
export SIMPLE_L1_CLOUDFLARE_API_TOKEN='...'   # optional, for DNS steering
export SIMPLE_L1_PUBLIC_IP='YOUR_VPS_IP'
export SL1_CONNECT_CLIENT_ID=meanly.ops
curl -fsSL https://raw.githubusercontent.com/vv1ldd/coolify/sovereign/scripts/install-sovereign.sh | bash
```

After install:

1. Open `https://ops.meanly.one` and complete SL1 admin claim.
2. Verify `https://identity.meanly.one/healthcheck`.
3. Verify `https://identity.meanly.one/api/sl1e/connect/status` (from VPS: `docker exec simple-l1 curl -s http://127.0.0.1:3000/api/sl1e/connect/status`).

Panel domain and identity domain are intentionally split:

- **ops** — deploy / ops login
- **identity** — passkey RP ID, SL1 Connect issuer, protocol runtime

## Step 2 — Marketplace API

Create a Coolify application from `vv1ldd/meanly-marketplace` (or your fork), Dockerfile at repo root.

Env template: `deploy/regional/env/backend-shared.env.example`

Key identity vars for production:

```env
SIMPLE_L1_IDENTITY_PROVIDER_URL=https://identity.meanly.one
SIMPLE_L1_PROTOCOL_GATEWAY_URL=https://identity.meanly.one
SIMPLE_L1_RUNTIME_URL=http://simple-l1:3000
SIMPLE_L1_CLIENT_ID=meanly.one
SIMPLE_L1_IDENTITY_BROWSER_URL=https://meanly.one
```

`SIMPLE_L1_RUNTIME_URL` uses the internal Docker network hostname when API and Sovereign stack share the same Coolify network (`coolify`). Adjust if runtime is reached differently.

Post-deploy command: `bash deploy.sh` (migrate, cache, bitcoin binding readiness).

Add a second process or service for the queue:

```bash
php artisan queue:work --queue=default --tries=1 --timeout=1200
```

## Step 3 — Global + RU storefronts

Two Coolify apps, same repo, `frontend/Dockerfile`.

| Build env file | Domain |
|----------------|--------|
| `deploy/regional/env/frontend-global.env.example` | `meanly.one` |
| `deploy/regional/env/frontend-ru.env.example` | `meanly.ru` |

RU build highlights:

```env
NEXT_PUBLIC_MARKETPLACE_API_URL=https://api.meanly.one
NEXT_PUBLIC_STOREFRONT_URL=https://meanly.ru
NEXT_PUBLIC_SIMPLE_L1_URL=https://meanly.one
NEXT_PUBLIC_MARKETPLACE_REGION=ru
```

Browser OAuth stays on `meanly.one` / `meanly.ru` (storefront proxies `/authorize` to API). Protocol authority is `identity.meanly.one`.

## Post-deploy checklist

```bash
curl -sI https://meanly.one/ | grep -i x-market
# expect: X-Market: global

curl -sI https://meanly.ru/ | grep -i x-market
# expect: X-Market: ru

curl -s https://api.meanly.one/api/storefront/v1/context \
  -H 'X-Forwarded-Host: meanly.ru' | jq .market.key
# expect: "ru"

curl -s https://identity.meanly.one/healthcheck
```

## RU storefront — what may still need work

- Legal copy / acquiring fields in `frontend-ru.env` (INN, OGRN, legal name)
- RU-specific payment acquiring env if different from global build
- Smoke: register/login via SL1, catalog, checkout path for RU market
- Ops Finance Control reachable from `ops.meanly.one` staff accounts

## Local dev (unchanged)

```bash
./scripts/regional-frontend-env.sh global
MEANLY_DEV_PROFILE=global ./scripts/dev-tunnel.sh setup
cd frontend && npm run dev -- -H 127.0.0.1 -p 3001
./scripts/dev-tunnel.sh run
```

See `deploy/regional/README.md`.
