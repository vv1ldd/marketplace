# Meanly Release Pipeline

Launch-critical local checks:

```bash
php artisan meanly:llm-health
php artisan meanly:seo-readiness
php artisan meanly:launch-readiness --quick --run-tests
```

Cloud LLM providers:

```bash
LLM_PROVIDER=openai
LLM_FALLBACK_PROVIDERS=anthropic,local
LLM_CLOUD_REQUIRED=true
OPENAI_API_KEY=...
ANTHROPIC_API_KEY=...
```

Post-deployment Gate 2 checks:

```bash
php artisan meanly:deployment-readiness --domain=https://meanly.com
php artisan meanly:launch-readiness --quick --domain=https://meanly.com
```

## Immutable Docker deploy

Production API images are published as `ghcr.io/vv1ldd/marketplace:<git-short-sha>` only
(no floating `:latest`). Base images are digest-pinned in Dockerfiles.

See [docker-immutable-deploy.md](./docker-immutable-deploy.md) for the full pin/rollback
procedure and Coolify host commands.

Admin Passkey enrollment pill:

```bash
php artisan meanly:admin-passkey-pill admin@meanly.com --domain=https://meanly.com
```

`demand_gaps=0` is a warning, not a first-day launch blocker. Demand intelligence is expected to fill after traffic starts producing search logs.
