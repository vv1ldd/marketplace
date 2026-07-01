# Meanly production on Sovereign Coolify (VPS)

One git repo (`marketplace`). One Laravel API. Regional storefronts are separate Coolify apps with different build env.

Mac dev stays on `scripts/dev-tunnel.sh` — not on the Coolify installer.

## Domain map

| Service | Domain | Coolify app | Notes |
|---------|--------|-------------|-------|
| Control plane | `ops.meanly.one` | (bundled with Sovereign install) | SL1 login for deploy |
| Simple L1 identity | `pass.simplelayer.one` | bundled `simple-l1` container | SL1e issuer + protocol runtime |
| API | `api.meanly.one` | `meanly-api` | Laravel + queue worker |
| Storefront global | `meanly.one` | `meanly-frontend-global` | `NEXT_PUBLIC_MARKETPLACE_REGION=global` |
| Storefront RU | `meanly.ru` | `meanly-frontend-ru` | `NEXT_PUBLIC_MARKETPLACE_REGION=ru` |

DNS: all public hostnames → VPS public IP (or Cloudflare proxied A records).

## ADR-0028 identity rollout gate

ADR-0028 fixes the production identity trust topology:

```text
pass.meanly.one             = issuer endpoint
connect.identity.meanly.one = ceremony origin
identity.meanly.one         = RP boundary
```

Current live rollout state on `lena`:

```text
pass.meanly.one                 online, DNS-only, healthcheck green
connect.identity.meanly.one     online, DNS-only, healthcheck green
identity.meanly.one             online, DNS-only, healthcheck green
connect.identity.meanly.one     /api/sl1e/connect/status -> rp_id identity.meanly.one
```

The browser authorize flow MUST redirect from issuer to ceremony before any
production registration:

```text
POST https://pass.meanly.one/api/sl1e/authorize/requests
  -> authorize_url=https://pass.meanly.one/r/...

GET https://pass.meanly.one/r/...
  -> 302 https://connect.identity.meanly.one/r/...

GET https://connect.identity.meanly.one/r/...
  -> WebAuthn ceremony with rp_id identity.meanly.one
```

Registration cutover is blocked until the ADR-0028 runtime behavior is merged
into the `simple-l1` source tree / official image. The live `lena` runtime now
runs an upstream-built image tagged:

```text
ghcr.io/vv1ldd/simple-l1:lena-local
```

built from `vv1ldd/simple-l1@f1a0001` with:

```env
SL1_ISSUER_CEREMONY_MAP=pass.meanly.one=connect.identity.meanly.one
```

Rollback image kept on `lena`:

```text
ghcr.io/vv1ldd/simple-l1:lena-local-pre-adr0028-20260624215037
```

Do not run production registrations from `pass.meanly.one` after a rebuild,
upgrade, or `docker pull` unless the following remains true:

```env
SL1_ISSUER_CEREMONY_MAP=pass.meanly.one=connect.identity.meanly.one
```

```bash
curl -fsS https://connect.identity.meanly.one/api/sl1e/connect/status | jq .rp_id
# "identity.meanly.one"

curl -sSI https://pass.meanly.one/r/notfound | grep -i '^location:'
# location: https://connect.identity.meanly.one/r/notfound
```

Phase 2 (registration cutover) starts only after the deployed image is built
from upstream `simple-l1` and the verification curls below pass.

## Step 1 — Sovereign Coolify + Simple L1

### One curl + reboot (LUKS via kexec autoinstall)

On a **plain** Selectel/cloud image (no rescue toggle):

```bash
umask 077
printf '%s' 'YOUR_LONG_PASSPHRASE' > /root/.sovereign-luks-passphrase

export SOVEREIGN_DISK_ENCRYPT=auto
export SOVEREIGN_LUKS_PASSPHRASE_FILE=/root/.sovereign-luks-passphrase
export SOVEREIGN_ASSUME_YES=true
export SOVEREIGN_AUTOCONVERGE_AFTER_ENCRYPT=true
export SOVEREIGN_RUNTIME_CONVERGE_OWNER=true
export SOVEREIGN_HOST_DOMAIN=ops.meanly.one
export SIMPLE_L1_DOMAIN=pass.simplelayer.one
export SIMPLE_L1_ISSUER_URL=https://pass.simplelayer.one/sl1
export SL1_CONNECT_ISSUER=https://pass.simplelayer.one
export SL1_CONNECT_CLIENT_ID=meanly.ops
export SIMPLE_L1_PUBLIC_IP='YOUR_VPS_IP'

git clone --depth 1 -b sovereign https://github.com/vv1ldd/coolify.git /tmp/coolify-sovereign
bash /tmp/coolify-sovereign/scripts/install-sovereign.sh
```

What happens:

1. Script downloads Ubuntu netboot, embeds autoinstall (LUKS LVM) + your SSH key.
2. **kexec** — machine reboots into installer (SSH drops).
3. Installer wipes disk, installs encrypted Ubuntu, reboots.
4. **Provider console** — enter LUKS passphrase if prompted (often once).
5. SSH back — `sovereign-firstboot` runs Coolify converge automatically.

### Optional: LUKS via rescue (manual provider toggle)

```bash
umask 077
printf '%s' 'YOUR_LONG_PASSPHRASE' > /root/.sovereign-luks-passphrase

export SOVEREIGN_DISK_ENCRYPT=true
export SOVEREIGN_RESCUE_PREPARE=true
export SOVEREIGN_LUKS_PASSPHRASE_FILE=/root/.sovereign-luks-passphrase
export SOVEREIGN_ASSUME_YES=true
export SOVEREIGN_RUNTIME_CONVERGE_OWNER=true

git clone --depth 1 -b sovereign https://github.com/vv1ldd/coolify.git /tmp/coolify-sovereign
bash /tmp/coolify-sovereign/scripts/install-sovereign.sh
```

3. Reboot → **provider console** → LUKS passphrase → SSH.
4. Normal install (encrypted root, no rescue flag):

```bash
export SOVEREIGN_DISK_ENCRYPT=true
export SOVEREIGN_RUNTIME_CONVERGE_OWNER=true
export SOVEREIGN_ASSUME_YES=true
export SOVEREIGN_HOST_DOMAIN=ops.meanly.one
export SIMPLE_L1_DOMAIN=pass.simplelayer.one
export SIMPLE_L1_ISSUER_URL=https://pass.simplelayer.one/sl1
export SL1_CONNECT_ISSUER=https://pass.simplelayer.one
export SL1_CONNECT_CLIENT_ID=meanly.ops
export SIMPLE_L1_PUBLIC_IP='YOUR_VPS_IP'

git clone --depth 1 -b sovereign https://github.com/vv1ldd/coolify.git /tmp/coolify-sovereign
bash /tmp/coolify-sovereign/scripts/install-sovereign.sh
```

Scripts: `vv1ldd/coolify` → `scripts/sovereign-disk/` (wired into `scripts/install-sovereign.sh`).

### Standard install (no LUKS)

On a fresh Linux VPS (as root):

```bash
export SOVEREIGN_RUNTIME_CONVERGE_OWNER=true
export SOVEREIGN_ASSUME_YES=true
export SOVEREIGN_DISK_ENCRYPT=false
export SOVEREIGN_HOST_DOMAIN=ops.meanly.one
export SIMPLE_L1_DOMAIN=pass.simplelayer.one
export SIMPLE_L1_ISSUER_URL=https://pass.simplelayer.one/sl1
export SL1_CONNECT_ISSUER=https://pass.simplelayer.one
export SL1_CONNECT_CLIENT_ID=meanly.ops
export SIMPLE_L1_PUBLIC_IP='YOUR_VPS_IP'

git clone --depth 1 -b sovereign https://github.com/vv1ldd/coolify.git /tmp/coolify-sovereign
bash /tmp/coolify-sovereign/scripts/install-sovereign.sh
```

Or raw curl (disk scripts downloaded on demand when `SOVEREIGN_DISK_ENCRYPT` is set):

```bash
curl -fsSL https://raw.githubusercontent.com/vv1ldd/coolify/sovereign/scripts/bootstrap-sovereign-from-git.sh \
  -o /tmp/bootstrap-sovereign-from-git.sh
bash /tmp/bootstrap-sovereign-from-git.sh
```

After install:

1. Open `https://ops.meanly.one` and complete SL1 admin claim.
2. Verify `https://pass.simplelayer.one/healthcheck`.
3. Verify `https://pass.simplelayer.one/api/sl1e/connect/status` (from VPS: `docker exec simple-l1 curl -s http://127.0.0.1:3000/api/sl1e/connect/status`).

Panel domain and identity domain are intentionally split:

- **ops** — deploy / ops login
- **identity** — passkey RP ID, SL1 Connect issuer, protocol runtime (`pass.simplelayer.one`)

## Step 2 — Marketplace API

### GHCR image (recommended — Phase 4 baking)

Every merge to `master` builds and pushes the API image via
`.github/workflows/docker-publish.yml`:

```text
ghcr.io/vv1ldd/marketplace:latest
ghcr.io/vv1ldd/marketplace:<git-sha>
```

In Coolify, prefer **Docker Image** over git-build so recreate pulls baked
Phase 4 wiring instead of stale hot-deploy files. Pin a SHA tag for production;
use `:latest` only on staging.

Post-deploy command: `bash deploy.sh` (migrate, cache, bitcoin binding readiness,
**deploy-gate** via `meanly:production-readiness --deploy-gate`).

Env template: `deploy/regional/env/backend-shared.env.example` (includes Phase 4
DGS sidecar vars). See `docs/dgs-phase-4-split-mode.md` for split-mode rollout.

### Git build (legacy)

Create a Coolify application from `vv1ldd/marketplace`, Dockerfile at repo root.
Same env and post-deploy as above.

Key identity vars for production:

```env
SIMPLE_L1_IDENTITY_PROVIDER_URL=https://pass.simplelayer.one
SIMPLE_L1_PROTOCOL_GATEWAY_URL=https://pass.simplelayer.one
SIMPLE_L1_RUNTIME_URL=http://simple-l1:3000
SIMPLE_L1_CLIENT_ID=meanly.one
SIMPLE_L1_IDENTITY_BROWSER_URL=https://meanly.one
```

`SIMPLE_L1_RUNTIME_URL` uses the internal Docker network hostname when API and Sovereign stack share the same Coolify network (`coolify`). Adjust if runtime is reached differently.

Post-deploy command: `bash deploy.sh` (migrate, cache, bitcoin binding readiness, deploy-gate).

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

Browser OAuth stays on `meanly.one` / `meanly.ru` (storefront proxies `/authorize` to API). Protocol authority is `pass.simplelayer.one`.

See `deploy/simplelayer-cutover.md` when migrating from `identity.meanly.one`.

## Post-deploy checklist

```bash
curl -sI https://meanly.one/ | grep -i x-market
# expect: X-Market: global

curl -sI https://meanly.ru/ | grep -i x-market
# expect: X-Market: ru

curl -s https://api.meanly.one/api/storefront/v1/context \
  -H 'X-Forwarded-Host: meanly.ru' | jq .market.key
# expect: "ru"

curl -fsS https://pass.simplelayer.one/healthcheck
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
