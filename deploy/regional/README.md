# Regional deployment

One git repo. One Laravel API. Regional behavior comes from **domain → market**, not from separate codebases.

## Mac dev (what we use now)

No Coolify. No VPS. Cloudflare tunnel to your Mac:

```text
https://meanly.one  ──cloudflared──► 127.0.0.1:3001  (Next dev)
                                           │
                                           └── /backend/* ──► api.meanly.test (Valet Laravel)
```

```bash
./scripts/regional-frontend-env.sh global
MEANLY_DEV_PROFILE=global ./scripts/dev-tunnel.sh setup
cd frontend && npm run dev -- -H 127.0.0.1 -p 3001
./scripts/dev-tunnel.sh run
```

Optional:

- `MEANLY_DEV_PROFILE=ru` — tunnel `meanly.ru` for RU surface checks
- `MEANLY_DEV_PROFILE=all` — `meanly.one` + `meanly.ru` on one Next port; market follows request `Host`

API stays local (`api.meanly.test`). Next proxies it via `/backend/*` (`next.config.js`).

Laravel root `.env` for tunnel auth:

```bash
./scripts/regional-backend-env.sh tunnel-global
php artisan config:clear
```

Or copy from `deploy/regional/env/backend-mac-tunnel.env.example`. Keeps `SIMPLE_L1_CLIENT_ID=meanly.test` (registered client) while public URLs use `meanly.one`.

### Beta Telegram ping

When your Mac dev stack is up, notify the beta test group automatically:

```bash
cp scripts/.beta-notify.env.example scripts/.beta-notify.env
# fill MEANLY_BETA_TELEGRAM_BOT_TOKEN + MEANLY_BETA_TELEGRAM_CHAT_ID
./scripts/dev-tunnel-notify.sh test
./scripts/dev-tunnel-notify.sh watch   # second terminal, or:
MEANLY_BETA_NOTIFY=1 ./scripts/dev-tunnel.sh run
```

The watcher waits until **Next + backend proxy + Simple L1 (:3000) + public https://meanly.one** are all healthy, then posts once. When something drops, it posts offline.

Catalog sync from Ops (`Refresh EZPin`) runs in the **queue**. Keep a worker running while testing:

```bash
php artisan queue:work --queue=default --tries=1
```

## Architecture (same repo, any environment)

```text
meanly.one  ──► Next (global EN)
meanly.ru   ──► Next (RU copy / env)
                    │
                    ▼
            Laravel API (one backend)
                    │
            MarketContextResolver(host)
```

Market selection is runtime (`Host` / `X-Forwarded-Host`). Legal copy follows `market.key` in code. Separate production **builds** per region matter only when `NEXT_PUBLIC_ACQUIRING_*` env must differ at build time.

## Later: production on a VPS (Coolify optional)

When you move off the Mac tunnel, you can run the same repo as separate services (API + 2 frontends). Coolify is one way to do that — not required for local dev.

| Service | Domain | Env template |
|---------|--------|--------------|
| API | `api.meanly.one` | `deploy/regional/env/backend-shared.env.example` |
| Storefront global | `meanly.one` | `deploy/regional/env/frontend-global.env.example` |
| Storefront RU | `meanly.ru` | `deploy/regional/env/frontend-ru.env.example` |

Do **not** fork the repo.

## Local Docker smoke test (optional)

```bash
docker compose -f deploy/regional/docker-compose.frontends.yml build
docker compose -f deploy/regional/docker-compose.frontends.yml up -d
open http://127.0.0.1:3101   # global build
open http://127.0.0.1:3102   # RU build
```

## Local dev (Valet + tunnel)

```bash
# Global only
./scripts/regional-frontend-env.sh global
./scripts/dev-tunnel.sh setup

# RU only
./scripts/regional-frontend-env.sh ru
MEANLY_DEV_PROFILE=ru ./scripts/dev-tunnel.sh setup

# One Next dev, both domains on the same port (Host header selects market)
./scripts/regional-frontend-env.sh global
MEANLY_DEV_PROFILE=all ./scripts/dev-tunnel.sh setup
cd frontend && npm run dev -- -H 127.0.0.1 -p 3001
./scripts/dev-tunnel.sh run
```

## Checklist after deploy

```bash
curl -sI https://meanly.one/ | grep -i x-market
# expect: X-Market: global

curl -sI https://meanly.ru/ | grep -i x-market
# expect: X-Market: ru

curl -s https://api.meanly.one/api/storefront/v1/context \
  -H 'X-Forwarded-Host: meanly.one' | jq .market.key
# expect: "global"
```
