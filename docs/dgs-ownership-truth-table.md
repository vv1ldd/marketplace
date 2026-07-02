# DGS Ownership Truth Table & Subsystem Maturity Map

Status: Reference (living document)
Related: ADR 0036 (Meanly API ↔ DGS sidecar boundary), ADR 0037 (digital entitlement model), ADR 0038 (knowledge/execution plane boundary), `docs/dgs-phase-4-split-mode.md`

## Why this document exists

The Digital Goods Source (DGS) responsibility is intentionally split across a
PHP financial/catalog kernel and a Node fulfillment sidecar. During the current
migration that split is easy to misread ("which side actually issues the PIN?").
This document is the single source of truth for **who owns what** and **how
mature each piece is**, so operators and contributors stop reverse-engineering it
from code and ADRs.

If code and this table disagree, treat it as a bug in one of them and reconcile.

## Ownership truth table

| Domain (Data / Action)        | Authority (Owner)          | Implementation surface                                   | Maturity        |
| ----------------------------- | -------------------------- | ------------------------------------------------------- | --------------- |
| Provider catalog              | PHP Kernel (DGS)           | Ingest/sync scripts, catalog DB, unified-catalog API     | Implemented     |
| Liquidity / balance validation| PHP Kernel (DGS)           | Financial ledger, partner balance, grant-credit (HMAC)   | Implemented     |
| Offer snapshots               | PHP Kernel (ADR 0038)      | `offer_snapshots` projection                             | Frozen (Core)   |
| Credentials & vendor balances | Node Sidecar (simple-l1)   | Vault-held EzPin creds, HMAC-signed proxy calls          | Transitional    |
| Redemption / issuance (redeem)| Node Sidecar (simple-l1)   | `/api/v1/fulfillment/issue`, uProof queue, async handler | Experimental    |
| Shadow parity / verification  | Node Sidecar (simple-l1)   | `:8092/shadow/ingest`, PHP↔Node comparison               | Transitional    |
| Identity-bound fulfillment    | Node Sidecar (simple-l1)   | uProof / mesh authority projection                       | Experimental    |

### Maturity legend

- **Implemented** — live in production, covered by tests, safe to depend on.
- **Frozen (Core)** — stable contract; change only via a new ADR.
- **Transitional** — works, but mid-migration; expect env-driven behavior and rollback toggles.
- **Experimental** — real code exists, not yet the proven default production path.

## Runtime routing (who gets called)

```
Storefront / checkout
        │
        ▼
Marketplace (WildflowDriver)
        │
        ├─► PHP DGS   :8080  grant-credit, catalog, availability   (DIGITAL_GOODS_SOURCE_URL)
        │
        └─► Node side :8091  vendor redeem when WILDFLOW_FULFILLMENT_MODE=split|node  (DGS_FULFILLMENT_URL)
                              shadow parity → :8092/shadow/ingest
```

- `WILDFLOW_FULFILLMENT_MODE=http` (default): financial + fulfillment stay on PHP path.
- `WILDFLOW_FULFILLMENT_MODE=split`: financial on PHP, vendor redeem canaries to Node
  per `WILDFLOW_SPLIT_FULFILLMENT_PROVIDERS`.
- `WILDFLOW_FULFILLMENT_MODE=node`: vendor redeem fully on Node.
- Instant rollback: set `WILDFLOW_FULFILLMENT_MODE=http` (no sidecar redeploy needed).

## Topology (ADR 0036)

| Zone                 | Host          | DGS role                    | EzPin credentials |
| -------------------- | ------------- | --------------------------- | ----------------- |
| ONE authority        | GCP / lena    | Full DGS + Node fulfillment | Present           |
| RU edge              | lena-1-gcl    | Sterile DGS (mirror only)   | Absent            |

Edge behavior: sterile DGS pulls catalog from the authority and returns
`503 EDGE_FULFILLMENT_DELEGATED_TO_ONE` for redeem, keeping vendor keys in one zone.

## Known open items (transitional → implemented)

These are the gaps that keep the "Transitional/Experimental" rows from being
"Implemented". Close them in order:

1. Fund the EzPin sandbox wallet and pass one live redeem canary (`node_status=ISSUED`).
2. Finish ADR 0036 Phase B/C: DNS to GCP authority, live `ezpin` allowlist, meanly.ru edge verification.
3. Make PHP↔Node fulfillment ownership explicit in API responses (e.g. `fulfillment_delegated_to: node`).
4. Standardize the `digital-goods-source` image build/publish (parity with marketplace `docker-publish.yml`).
5. Decide identity-bound fulfillment: finish the checkout → DGS → mesh path, or mark it explicitly out of current scope.

## Storefront performance & ops (adjacent, for operator context)

- Hot storefront reads (homepage, intent corridors, top searches) are cached for 300s and
  pre-warmed every 3 minutes by `catalog:warm-cache` so runtime never pays the cold-start cost.
- Operational alerts (`meanly:check-alerts`) now cover disk pressure, queue backlog, and
  recent failed jobs in addition to fulfillment/checkout/AI, so a full disk or stuck queue
  surfaces before it takes the platform read-only.
