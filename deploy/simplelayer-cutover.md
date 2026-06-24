# Simple Layer protocol cutover (`pass.simplelayer.one`)

Protocol identity moves off `identity.meanly.one` to the neutral Simple Layer host.
Commerce OAuth stays on regional storefronts (`meanly.one`, `meanly.ru`).

## Domain roles

| Host | Role |
|------|------|
| `pass.simplelayer.one` | SL1e issuer, protocol runtime, Coolify ops passkey RP |
| `meanly.one` / `meanly.ru` | Buyer Digital Safe (`/authorize`), regional `client_id` / `rpId` |
| `api.meanly.one` | Laravel API (calls runtime at `http://simple-l1:3000`) |
| `ops.meanly.one` | Coolify panel (SL1 Connect issuer → `pass.simplelayer.one` after cutover) |
| `identity.meanly.one` | Legacy — remove or 301 → `pass.simplelayer.one` after cutover |

## 1. DNS (do this first)

At the `simplelayer.one` registrar / Cloudflare zone:

```text
pass.simplelayer.one   A   135.106.162.147
```

Optional (recommended for the public protocol site):

```text
simplelayer.one        A   135.106.162.147   (marketing / docs)
```

`pass.simplelayer.one` stays the issuer; `simplelayer.one` is the public site. Both can point to the same host — routing is by hostname inside `simple-l1`.

Wait until:

```bash
dig +short pass.simplelayer.one A
# must return 135.106.162.147 (or your proxied CNAME chain)
```

## 2. Sovereign Coolify (`lena`)

Edit `/data/coolify/source/.env`:

```env
SIMPLE_L1_DOMAIN=pass.simplelayer.one
SIMPLE_L1_ISSUER_URL=https://pass.simplelayer.one/sl1
SL1_CONNECT_ISSUER=https://pass.simplelayer.one
```

Recreate protocol stack (from host):

```bash
cd /data/coolify/source
docker compose up -d --force-recreate simple-l1 coolify-proxy
# or restart the full coolify stack if labels did not refresh
```

Verify:

```bash
curl -fsS https://pass.simplelayer.one/healthcheck
curl -fsS https://pass.simplelayer.one/api/sl1e/connect/status
```

**Note:** Coolify ops passkeys are per `rpId`. Admins re-register passkey on the new domain once.

## 3. Marketplace API (`meanly-api`)

Coolify app env (`l14g1swrbqb6g1omdpsrj67p`):

```env
SIMPLE_L1_IDENTITY_PROVIDER_URL=https://pass.simplelayer.one
SIMPLE_L1_PROTOCOL_GATEWAY_URL=https://pass.simplelayer.one
SIMPLE_L1_RUNTIME_URL=http://simple-l1:3000
SIMPLE_L1_IDENTITY_BROWSER_URL=https://meanly.one
```

Redeploy API. Smoke:

```bash
curl -s https://api.meanly.one/api/storefront/v1/context \
  -H 'X-Forwarded-Host: meanly.one' | jq .simple_l1
```

Storefront builds **do not** change `NEXT_PUBLIC_SIMPLE_L1_URL` — keep `https://meanly.one` / `https://meanly.ru`.

## 4. Post-cutover

- [ ] `https://ops.meanly.one` login works with passkey on `pass.simplelayer.one`
- [ ] `meanly.one/authorize` Create Safe completes
- [ ] `meanly.ru/authorize` still regional (`client_id=meanly.ru`)
- [ ] Retire or redirect `identity.meanly.one`

## Rollback

Restore `identity.meanly.one` in `/data/coolify/source/.env` and API env, recreate `simple-l1`, redeploy API.
