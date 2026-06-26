# Sovereign Secret Migration Checklist

Operational companion to
[ADR 0035](adr/0035-sovereign-secret-and-host-encryption-posture.md).

This document contains contour-specific inventory and rollout detail. It is not
normative architecture; it may change as services and hosts evolve.

## Phase 1 audit rules

Phase 1 is read-only. It must not change deployment metadata, containers,
provider credentials, DNS, volumes, or runtime behavior.

Inventory records secret **names and locations only**. Secret values must not be
written to this document, pasted into chat, committed to Git, or exported into
shared artifacts.

## Inventory schema

Use this schema for every discovered secret or configuration value:

| Field | Meaning |
|-------|---------|
| **Secret contract** | Stable logical name used by the application/infrastructure boundary |
| **Current name** | Current env/key/field name (`APP_KEY`, `DATABASE_URL`, etc.) |
| **Where now** | Current authoritative storage (`Coolify env`, compose, DB row, file, provider record) |
| **Type** | Public config, database credential, signing key, provider credential, client secret bundle, operator credential, ephemeral target |
| **Consumer** | Application or workload that requires the secret |
| **Owner** | Accountable owner for rotation and policy (Infrastructure, Platform, ONE, RU, Ops) |
| **Impact class** | S0, S1, S2, or S3 (see below) |
| **Version policy** | `preferred` version and accepted legacy versions, when overlap rotation is needed |
| **Lifecycle owner** | Who creates, rotates, revokes, and destroys the secret |
| **Delivery policy** | Allowed delivery transports (`env`, `file`, `runtime-api`, Managed Secret Provider) |
| **Access pattern** | Startup only, persistent runtime, on-demand, periodic refresh, build time only, unknown |
| **Target state** | Keep in deployment metadata, Managed Secret Provider, runtime file, short-lived credential, or remove |
| **Rotation strategy** | Manual, automatic, provider-managed, impossible/replace integration, unknown |
| **Priority** | P0 immediate, P1 next migration, P2 hardening, P3 static config |
| **Notes** | Contour-specific context and migration blockers |

Impact classes:

| Class | Meaning |
|-------|---------|
| **S0** | Not secret-bearing; safe to keep in deployment metadata |
| **S1** | Compromise affects one service or database account |
| **S2** | Compromise affects an external provider, upstream, or integration boundary |
| **S3** | Compromise enables signing, token issuance, financial operations, or privileged control-plane access |

Example rows:

| Secret contract | Type | Consumer | Owner | Impact | Version policy | Lifecycle owner | Delivery policy | Access pattern | Priority |
|-----------------|------|----------|-------|--------|----------------|-----------------|-----------------|----------------|----------|
| `protocol/app-signing-key` | Signing key | `simple-l1` | Platform | S3 | prefer `v2`, accept `v1/v2` during rotation | Infrastructure-managed | file or runtime API; env denied | Persistent runtime | P0 |
| `commerce/database` | Database credential | `meanly-api` | Infrastructure | S1 | current only unless overlap supported | Infrastructure-managed | Managed Secret Provider; env compatibility temporary | Startup + connection pool runtime | P1 |
| `one/provider/ezpin` | Provider credential | `meanly-api` | ONE | S2 | provider-defined | Provider/manual | Managed Secret Provider; env denied | Persistent runtime | P0 |
| `runtime/log-level` | Public config | any | Infrastructure | S0 | not applicable | not applicable | deployment metadata | Startup only | P3 |

## Secret contract conventions

Logical secret names follow contour and capability boundaries, not current env
variable names.

```text
<contour>/<capability>/<purpose>
```

Examples:

- `protocol/database`
- `protocol/app-signing-key`
- `one/provider/ezpin/api-key`
- `one/kernel/token`
- `ru/kernel/partner-client-id`
- `ru/kernel/partner-financial-secret`
- `ops/deployment/bootstrap-token`

The same contract can be backed by different transports in different
environments: Managed Secret Provider in production, local file in development,
CI/CD injection in tests, or temporary bootstrap material during recovery.

Typed contract example:

```yaml
id: one/provider/ezpin/api-key
version:
  preferred: current
  accept:
    - current
type: provider-credential
consumer: meanly-api
required: true
owner: ONE
impact: S2
rotation: provider-managed
lifecycle:
  create: ONE
  rotate: ONE
  revoke: ONE
  destroy: ONE
delivery:
  preferred: managed-secret-provider
  env_allowed: false
access: persistent-runtime
```

## Observed inventory — 2026-06 read-only pass

Source: running container environment **names only** from `lena-1-gcl` and
`lena`. No secret values were read into this document.

| Secret contract | Current name | Where now | Type | Consumer | Owner | Impact class | Target state | Rotation strategy | Access pattern | Priority | Notes |
|-----------------|--------------|-----------|------|----------|-------|--------------|--------------|-------------------|------------------|----------|-------|
| `one/app-signing-key` | `APP_KEY` | ONE API container env | Signing key | `meanly-api` | ONE | S3 | Managed Secret Provider -> runtime file or startup injection | Manual; scheduled after impact review | Continuous runtime | P0 | Laravel app key; rotation can affect encrypted payload/session semantics |
| `one/database/password` | `DB_PASSWORD`, `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD` | ONE API and MySQL container env | Database credential | `meanly-api`, MySQL | Infrastructure | S1 | Managed Secret Provider | Manual, later automatic | Startup + connection pool runtime | P1 | Includes app DB user and root password |
| `one/provider/ezpin/client-id` | `EZPIN_CLIENT_ID` | ONE API container env | Provider credential | `meanly-api` | ONE | S2 | Managed Secret Provider | Provider/manual | Continuous runtime | P0 | ONE only; RU bootstrap must not read |
| `one/provider/ezpin/secret-key` | `EZPIN_SECRET_KEY` | ONE API container env | Provider credential | `meanly-api` | ONE | S2 | Managed Secret Provider | Provider/manual | Continuous runtime | P0 | ONE only |
| `one/provider/ezpin/terminal` | `EZPIN_TERMINAL_ID`, `EZPIN_TERMINAL_PIN` | ONE API container env | Provider credential | `meanly-api` | ONE | S2 | Managed Secret Provider | Provider/manual | Continuous runtime | P0 | ONE only |
| `one/kernel/token` | `APP_WILDFLOW_TOKEN` | ONE API container env | Kernel credential | `meanly-api` | ONE | S2 | Managed Secret Provider | Manual, later short-lived token if supported | Continuous runtime | P0 | Direct supply authority |
| `one/protocol/par-client-secrets` | `SIMPLE_L1_PAR_CLIENT_SECRETS_JSON` | ONE API container env | Client secret bundle | `meanly-api` | Platform | S3 | Managed Secret Provider -> runtime file preferred | Manual | Startup + runtime validation | P0 | JSON secret bundle should not live in deployment metadata |
| `one/protocol/dns-provider-token` | `CLOUDFLARE_API_TOKEN`, `SIMPLE_L1_CLOUDFLARE_API_TOKEN` | `simple-l1` / Coolify env | Provider credential | `simple-l1`, Coolify | Platform | S2 | Managed Secret Provider | Manual/provider-managed | Periodic DNS steering/runtime | P0 | Scope to DNS zone only |
| `one/protocol/client-registry` | `SL1E_CLIENT_REGISTRY_JSON` | `simple-l1` env | Client registry / possible secret-bearing config | `simple-l1` | Platform | S1 | Managed Secret Provider or signed registry artifact | Manual | Startup + runtime lookup | P1 | Split public registry data from secret material during migration |
| `ops/deployment/app-key` | `APP_KEY` | Coolify container env | Signing key | Coolify | Ops | S3 | Managed Secret Provider | Manual | Continuous runtime | P0 | Deployment plane secret; protects env snapshots and metadata |
| `ops/deployment/database-password` | `DB_PASSWORD`, `POSTGRES_PASSWORD` | Coolify and Coolify Postgres env | Database credential | Coolify, Postgres | Ops | S1 | Managed Secret Provider | Manual, later automatic | Startup + connection pool runtime | P1 | Coolify DB may contain deployment env history |
| `ops/deployment/redis-password` | `REDIS_PASSWORD` | Coolify / Redis env | Infrastructure credential | Coolify, Redis | Ops | S1 | Managed Secret Provider | Manual | Continuous runtime | P1 | Deployment plane runtime secret |
| `ops/deployment/realtime-secret` | `PUSHER_APP_SECRET`, `SOKETI_DEFAULT_APP_SECRET` | Coolify / realtime env | Infrastructure credential | Coolify, realtime | Ops | S1 | Managed Secret Provider | Manual | Continuous runtime | P1 | Operator UI realtime transport |
| `ops/deployment/root-password` | `ROOT_USER_PASSWORD` | Coolify env | Operator credential | Coolify | Ops | S3 | Managed Secret Provider + rotate | Manual | Login/runtime | P0 | Privileged control-plane credential |
| `ops/deployment/sentinel-token` | `TOKEN` | Coolify sentinel env | Infrastructure credential | Coolify sentinel | Ops | S1 | Managed Secret Provider | Manual | Continuous runtime | P1 | Monitoring/control-plane token |
| `ru/app-signing-key` | `APP_KEY` | RU API container env | Signing key | `meanly-api-ru` | RU | S3 | Managed Secret Provider -> runtime file or startup injection | Manual; scheduled after impact review | Continuous runtime | P0 | Separate contour from ONE |
| `ru/database/password` | `DB_PASSWORD`, `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD` | RU API and MySQL container env | Database credential | `meanly-api-ru`, MySQL | Infrastructure | S1 | Managed Secret Provider | Manual, later automatic | Startup + connection pool runtime | P1 | Includes app DB user and root password |
| `ru/kernel/partner-credentials` | provider record fields (`api_key`, `client_id`, `financial_secret`) | RU API database provider record | Kernel credential | `meanly-api-ru` | RU | S3 | Managed Secret Provider or encrypted provider credential store | Manual, later short-lived partner credential if supported | Continuous runtime | P0 | Financial secret is S3 even though not observed in container env |
| `ru/protocol/par-client-secrets` | `SIMPLE_L1_PAR_CLIENT_SECRETS_JSON` | RU API container env | Client secret bundle | `meanly-api-ru` | Platform | S3 | Managed Secret Provider -> runtime file preferred | Manual | Startup + runtime validation | P0 | JSON secret bundle should not live in deployment metadata |
| `ru/protocol/client-registry` | `SL1E_CLIENT_REGISTRY_JSON` | `simple-l1-ru` env | Client registry / possible secret-bearing config | `simple-l1-ru` | Platform | S1 | Managed Secret Provider or signed registry artifact | Manual | Startup + runtime lookup | P1 | Split public registry data from secret material during migration |
| `runtime/log-level` | `LOG_OUTPUT_LEVEL`, `SOKETI_DEBUG` | container env | Public config | any | Infrastructure | S0 | Keep in deployment metadata | Not applicable | Startup only | P3 | Not secret-bearing |
| `runtime/node-env` | `APP_ENV`, `NODE_ENV` | container env | Public config | any | Infrastructure | S0 | Keep in deployment metadata | Not applicable | Startup only | P3 | Not secret-bearing |
| `runtime/public-urls` | `APP_URL`, `COOLIFY_FQDN`, `NEXT_PUBLIC_*`, `SIMPLE_L1_*_URL` | container env | Public config | any | Infrastructure | S0 | Keep in deployment metadata unless value embeds credential | Not applicable | Startup/runtime config | P3 | Public URL naming should remain explicit |

### Observed Coolify DB metadata

Source: Coolify Postgres schema and key/name columns only. Secret value columns
were not read into this document.

| Host | Table | Secret-bearing columns | Observed metadata |
|------|-------|------------------------|-------------------|
| `lena-1-gcl`, `lena` | `environment_variables` | `value` | `key`, `resourceable_type`, `resourceable_id`, `is_runtime`, `is_buildtime`, `is_multiline` |
| `lena-1-gcl`, `lena` | `shared_environment_variables` | `value` | `key`, `type`, `project_id`, `environment_id`, `server_id`, `is_multiline` |
| `lena-1-gcl`, `lena` | `private_keys` | `private_key` | `name`, `fingerprint`, `is_git_related` |
| `lena-1-gcl`, `lena` | `cloud_provider_tokens` | `token` | `provider`, `name` (no rows observed in this pass) |
| `lena-1-gcl`, `lena` | `s3_storages` | `key`, `secret` | `name`, `region`, `is_usable` (no rows observed in this pass) |
| `lena-1-gcl`, `lena` | `scheduled_database_backups` | backup target references | `uuid`, `enabled`, `save_s3`, `database_type`, `s3_storage_id`, `disable_local_backup` (no rows observed in this pass) |

Application resource mapping observed in Coolify DB:

| Host | Application rows |
|------|------------------|
| `lena-1-gcl` | `meanly-api`, `meanly-frontend-global`, `meanly-mysql` |
| `lena` | `meanly-api` (legacy global), `meanly-frontend-global` (legacy global), `meanly-frontend-ru`, `meanly-api-ru`, `meanly-mysql`, `meanly-mysql-ru` |

The lena Coolify DB still tracks legacy global application/database records even
after their containers were stopped. Their deployment metadata remains part of
the Phase 1 risk surface until removed or sanitized.

### Observed file and volume surfaces

Source: path names, file sizes, and modes only. File contents were not read into
this document.

| Host | Path / volume | Why it matters |
|------|---------------|----------------|
| `lena-1-gcl` | `/data/coolify/source/.env` | Coolify/root deployment env with S2/S3 key names |
| `lena-1-gcl` | `/data/coolify/source/.env-20260624-*` | Historical source env backups with secret-bearing key names |
| `lena-1-gcl` | `/data/coolify/source/.env.production` | Historical production env template/source |
| `lena-1-gcl` | `/data/coolify/source/scripts/sovereign-identity-env.sh` | Script may embed identity/bootstrap env material |
| `lena-1-gcl` | `/data/coolify/applications/t1740k2sqm3ryyguobvfju4a/.env` | ONE API application env |
| `lena-1-gcl` | `/data/coolify/applications/s7ndb7kcd90789hsaljmkvus/.env` | ONE storefront env |
| `lena-1-gcl` | Docker volumes: `coolify-db`, `coolify-redis`, `mysql-data-xb80omvbl8mbo6taz3i6wpor`, `simple-l1-data` | Offline media/snapshot exposure for deployment DB, app DB, and identity ledger |
| `lena` | `/data/coolify/source/.env` | Coolify/root deployment env with S2/S3 key names |
| `lena` | `/data/coolify/source/.env-*`, `/data/coolify/source/.env.bak-*`, `/data/coolify/source/.env.production` | Historical source env backups |
| `lena` | `/data/coolify/source/.par_secret_meanly_one` | Standalone PAR secret artifact |
| `lena` | `/data/coolify/source/scripts/sovereign-identity-env.sh` | Script may embed identity/bootstrap env material |
| `lena` | `/data/coolify/applications/z11be0goi328e6uhcfrd87ko/.env` | RU API application env |
| `lena` | `/data/coolify/applications/l14g1swrbqb6g1omdpsrj67p/.env`, `/data/coolify/applications/l14g1swrbqb6g1omdpsrj67p/.env.bak-*` | Legacy global API env and backup still present on lena |
| `lena` | `/data/coolify/applications/l8ryesvue97qoo3k2bi7y445/.env` | RU storefront env |
| `lena` | `/data/coolify/applications/nelzdlnpz8f8wdd6t7hqhnhv/.env` | Legacy global storefront env still present on lena |
| `lena` | Docker volumes: `coolify-db`, `coolify-redis`, `mysql-data-ru7e5r43zyz39lpnih80wa1m`, `mysql-data-x851rc26sbvuibk9z2f1sdm2`, `simple-l1-data`, `simple-l1-ru-data` | Includes legacy global and RU data volumes; offline media/snapshot exposure |

### Observed source key groups

Source files on both hosts contain these secret-bearing key groups by name:

- Coolify/control plane: `APP_KEY`, `DB_PASSWORD`, `REDIS_PASSWORD`,
  `PUSHER_APP_SECRET`, `ROOT_USER_PASSWORD`
- Deployment/supply bootstrap: `DIGITAL_GOODS_SOURCE_FINANCIAL_SECRET`,
  `DIGITAL_GOODS_SOURCE_PLATFORM_TOKEN`
- Identity/protocol: `SIMPLE_L1_CLOUDFLARE_API_TOKEN`,
  `SL1E_CLIENT_REGISTRY_JSON`, `SL1_ISSUER_CEREMONY_MAP`,
  `SL1_CONNECT_SECRET`, `SL1_RU_PAR_CLIENT_SECRET`
- Database/ledger: `LEDGER_DB_PASSWORD`, `POSTGRES_PASSWORD`,
  application `DB_PASSWORD`

GCP running container env also exposes ONE provider keys by name
(`EZPIN_CLIENT_ID`, `EZPIN_SECRET_KEY`, `EZPIN_TERMINAL_ID`,
`EZPIN_TERMINAL_PIN`, `APP_WILDFLOW_TOKEN`), even though those names were not
obvious in the Coolify `environment_variables` query during this pass. Their
authoritative source remains a Phase 1 follow-up item.

## Inventory by contour

### ONE (GCP `lena-1-gcl`)

| Service | Long-lived secrets (move out of deployment metadata) | Notes |
|---------|-----------------------------------------------------|-------|
| Commerce API | `APP_KEY`, DB password, EZPin credentials, Fazer credentials, `APP_WILDFLOW_TOKEN`, payment/signing material | Direct supply authority per ADR 0034 |
| Identity (`simple-l1`) | issuer signing keys, registry secrets, bootstrap tokens | Shares contour with ONE commerce |
| MySQL | root/app DB passwords | Volume backup is high-risk until host encryption posture is confirmed |
| Coolify | deployment-plane DB, service env snapshots | Audit `project_env` and compose exports |

### RU (lena)

| Service | Long-lived secrets (move out of deployment metadata) | Notes |
|---------|-----------------------------------------------------|-------|
| Commerce API (`api.meanly.ru`) | `APP_KEY`, DB password, ONE partner `api_key` / `client_id` / `financial_secret` on wildflow provider | Remote kernel consumer; no direct vendor credentials |
| Identity (`simple-l1-ru`) | issuer signing keys, registry secrets | Separate sovereign ledger volume |
| MySQL RU | root/app DB passwords | Same offline-media risk as ONE |
| Coolify | deployment-plane DB, service env snapshots | Same audit requirement |

### Protocol namespace (ONE host today)

Protocol hosts (`pass.simplelayer.one`, `simplelayer.one`) run on the ONE identity
node. Secret inventory follows the `simple-l1` row above.

## Phase checklist

### Phase 1 — Classify and inventory

- [ ] Export current env **names only** from Coolify for ONE and RU API + identity services
- [ ] Mark each variable: public config / long-lived / high-sensitivity / ephemeral target
- [ ] Assign a stable secret contract name where the value is secret-bearing
- [ ] Assign `type`, `consumer`, `owner`, and `impact class` for each contract
- [ ] Record version policy, lifecycle owner, delivery policy, and access pattern for every P0/P1 item
- [ ] Confirm no vendor credentials remain on RU API (ADR 0034)
- [ ] List backup paths that may contain plaintext env (Coolify DB, compose, volume snapshots)

Done when:

- [ ] A complete contract registry exists for every discovered S1/S2/S3 item
- [ ] Every secret-bearing contract has owner, consumer, type, and impact class
- [ ] Every P0/P1 contract has lifecycle, delivery, access, and rotation metadata
- [ ] No discovered secret-bearing item remains unclassified
- [ ] Inventory contains no secret values

### Phase 2 — Managed Secret Provider

- [ ] Choose provider for GCP contour (current candidate: GCP Secret Manager)
- [ ] Choose provider for lena contour (Vault, SOPS+age, or equivalent)
- [ ] Create secret namespaces: `sovereign/one/`, `sovereign/ru/`, `sovereign/protocol/`
- [ ] Define rotation owners and cadence per secret class

Done when:

- [ ] Managed Secret Provider namespace exists for each contour
- [ ] All S2/S3 contract targets have provider paths or policy-approved exceptions
- [ ] Provider access policy is scoped by contour and consumer
- [ ] Provider audit logging is enabled or explicitly deferred with owner approval

### Phase 3 — Bootstrap identity

- [ ] ONE host: workload or service account with least-privilege secret accessor role
- [ ] RU host: bootstrap identity scoped to RU namespace only
- [ ] Document bootstrap revocation and re-issue procedure
- [ ] Store bootstrap recovery material outside deployment metadata

Done when:

- [ ] Each host/workload has only the bootstrap identity required for its contour
- [ ] RU bootstrap cannot read ONE provider or protocol S3 material unless explicitly intended
- [ ] Bootstrap revocation and recovery have been tested without changing application code
- [ ] Bootstrap material is not stored in deployment metadata

### Phase 4 — Secret injection

- [ ] Commerce API ONE: migrate `APP_KEY`, DB, EZPin, Fazer, wildflow token
- [ ] Commerce API RU: migrate `APP_KEY`, DB, ONE partner credentials
- [ ] Identity nodes: migrate signing and registry secrets
- [ ] Replace plaintext in Coolify env with references (`secret://...` or equivalent)
- [ ] Verify `docker inspect` / compose no longer expose moved secrets

Done when:

- [ ] Secret Resolver can resolve every required P0/P1 contract in production
- [ ] All S2/S3 secrets are absent from deployment metadata, compose, and `docker inspect`
- [ ] Delivery policy is enforced, including env denial for contracts that forbid env
- [ ] Service startup fails closed when a required contract cannot be resolved

### Phase 5 — Short-lived credentials

- [ ] RU → ONE API: evaluate partner token refresh instead of static financial secret where API allows
- [ ] Inter-service auth: prefer SL1-issued short-lived tokens over static shared secrets
- [ ] DB access: evaluate connection pooling with rotated credentials
- [ ] Re-run inventory; target near-zero permanent secrets in deployment metadata

Done when:

- [ ] Every eligible S2/S3 permanent credential has a short-lived replacement plan
- [ ] Rotation overlap is represented through version policy (`preferred` / `accept`)
- [ ] Expired credentials fail without breaking accepted overlap windows
- [ ] Remaining permanent credentials have documented provider or protocol constraints

## Host encryption (offline media)

Separate from secret migration. Tracks ADR 0035 Layer 0 requirement.

- [ ] Confirm current GCP persistent disk encryption mode (Google-managed vs customer-managed)
- [ ] Decide whether customer-controlled offline protection requires additional host encryption
- [ ] Encrypt or restrict MySQL volume backups
- [ ] Encrypt Coolify DB backups
- [ ] Verify detached volume / snapshot export procedure requires customer key material

## Verification

After each phase:

1. Service starts successfully with bootstrap identity only in deployment metadata.
2. No moved secret appears in Git, compose, or Coolify env export.
3. Rotation drill succeeds without application code change.
4. Contour boundary preserved: RU bootstrap cannot read ONE vendor secrets.
