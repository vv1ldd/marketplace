# Production Ownership Transition

`marketplace` is the system of record. Legacy `wildflow` names now mean one of several different things, so cleanup must follow the classification below instead of blanket renames.

## Wildflow Surface Classification

| Surface | Examples | Classification | Action |
| --- | --- | --- | --- |
| Public and Ops wording | `resources/views/**`, `lang/*.json`, Ops labels | Branding removal | Rename user-facing text to Meanly API / provider plane unless the text describes an explicit legacy compatibility alias. |
| Runtime URL defaults | `APP_WILDFLOW_URL`, `SIMPLE_L1_PROTOCOL_GATEWAY_URL`, `config/services.php`, `docker-compose.yml` | Remove runtime dependency | Defaults must point to Meanly-owned URLs or derive from `APP_URL`. `api.wildflow.dev` may only appear as an inbound compatibility alias. |
| Composer/runtime SDK loading | Root `composer.json` PSR-4 autoload, `packages/ezpin-sdk`, `packages/fazer-sdk` | Remove build dependency | SDK classes must load from this repo or Composer-managed packages, never developer-local sibling paths. |
| API routes and middleware aliases | `routes/api.php`, `AuthenticateWildflowKernelAccess`, `VerifyWildflowFinancialSignature`, `WildflowKernelController` | Route compatibility | Keep until external clients cut over. Add Meanly-named routes/middleware first, then deprecate legacy names with telemetry. |
| Provider types | `wildflow`, `wildflow-sandbox`, `ezpin`, `ezpin-sandbox`, `fazer` | Compatibility | Preserve stored provider identifiers while Ops labels and new config speak Meanly provider plane. Do not mutate existing provider rows without a migration plan. |
| Catalog lineage | `WildflowCatalog`, `wildflow_catalogs`, `WildflowSkuAlias`, catalog import/sync commands | Catalog lineage | Keep as historical source lineage. New normalized catalog logic should write/read provider products and canonical products; storage rename comes later. |
| Financial/order storage | `wildflow_kernel_orders`, `wildflow_credit_reservations`, legacy LegalEntity fields | Storage compatibility | Keep tables and columns. Use Meanly API models/services above them where possible; no drops or table renames before backfills and snapshots. |
| Legacy voucher schema references | `api_wildflow_dev.local_vouchers` in stock/clearing/tests | Storage compatibility | Treat as legacy attached schema. Migrate reads toward marketplace-owned inventory tables before removing the schema path. |
| Tests and fixtures | `WildflowServiceContractTest`, `WildflowKernelApiTest`, storefront fixtures | Tests | Keep tests while they protect compatibility behavior. Add Meanly-named tests for new invariants, then retire legacy fixture names after cutover. |
| Scratch/debug scripts | `scratch/*`, root scratch helpers, old endpoint probes | Dead code candidate | Delete only after confirming they are not part of deployment, CI, docs, or an operator runbook. |
| Historical migration names/comments | Existing dated migrations and ledger event names | Storage compatibility | Do not rewrite historical migrations. Comments can be clarified only when touching adjacent behavior. |

## Cleanup Rule

1. Rename UI first.
2. Rename code second.
3. Rename storage last.

Storage cleanup requires an explicit migration plan with backups, backfills, read-path verification, write cutover, and rollback notes.

## Current Gate Status

- Build dependency: Meanly-owned. SDK autoloads are in `packages/ezpin-sdk` and `packages/fazer-sdk`.
- Runtime defaults: Meanly-owned. `APP_WILDFLOW_URL` and SL1 gateway defaults no longer point to `api.wildflow.*`.
- Route/storage compatibility: intentionally retained.
- Scratch/debug cleanup: pending dedicated dead-code pass.
