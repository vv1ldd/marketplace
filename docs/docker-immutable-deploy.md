# Docker Immutable Deploy (SHA Pinning)

Production images must be pinned to an immutable git commit tag, never a floating
`:latest` or `:stable` tag. Upstream **base** images are pinned by digest in
Dockerfiles; application images are pinned by **git short SHA** at deploy time.

## Two layers of immutability

| Layer | What is pinned | Where |
| ----- | ---------------- | ----- |
| Base images | `image@sha256:…` digest | `Dockerfile`, `docker/base-images.lock.json` |
| App images | `ghcr.io/vv1ldd/marketplace:<git-sha>` | Coolify compose / host deploy |

## CI publish contract

On every push to `master`, GitHub Actions builds and pushes:

- `ghcr.io/vv1ldd/marketplace:<7-char-sha>` (deploy tag)
- `ghcr.io/vv1ldd/marketplace:<full-sha>` (audit trail)

The workflow **does not** publish `:latest` on master. The job summary prints the
exact pull command and image digest for the build.

## Production deploy (Coolify / lena-1-gcl)

After CI succeeds:

```bash
SHORT_SHA=<commit from gh run summary>
ssh lena-1-gcl '
  docker pull ghcr.io/vv1ldd/marketplace:'"$SHORT_SHA"'
  cd /data/coolify/applications/t1740k2sqm3ryyguobvfju4a
  sed -i "s|ghcr.io/vv1ldd/marketplace:.*|ghcr.io/vv1ldd/marketplace:'"$SHORT_SHA"'\''|" docker-compose.yaml
  docker compose up -d --force-recreate
'
```

Also update `meanly-queue-worker` to the same tag (or let `meanly-queue-guard.sh`
pick it up from the running API container image).

Rollback: redeploy the previous known-good short SHA (keep one prior tag on disk).

## Refreshing upstream base digests

When intentionally upgrading PHP/Node/Alpine bases:

```bash
bash scripts/docker/refresh-base-image-digests.sh
php artisan test tests/Unit/DockerBaseImagePinTest.php
# rebuild locally, run smoke tests, then commit lock + Dockerfile changes
```

## Storefront (Next.js)

The storefront image is built on-host from `frontend/Dockerfile` (also digest-pinned
for `node:22-alpine`). Tag the built image with the same git SHA as the API commit
it was built from, e.g. `s7ndb7kcd90789hsaljmkvus:<sha>`.

## Digital Goods Source

PHP kernel: `coolify/services/digital-goods-source/Dockerfile` (digest-pinned bases).
Node sidecar: `simple-l1/node/Dockerfile.dgs`.

Compose references use env vars — set explicit SHAs, not `:latest`:

- `DIGITAL_GOODS_SOURCE_IMAGE=ghcr.io/vv1ldd/digital-goods-source:<sha>`
- `DGS_NODE_SIDECAR_IMAGE=ghcr.io/vv1ldd/dgs-node-sidecar:<sha>`

See also: `docs/dgs-ownership-truth-table.md`.
