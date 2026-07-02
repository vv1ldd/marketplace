# ADR 0038: Knowledge / Execution Plane Boundary

## Status

**Accepted** — 2026-07-02

**Acceptance evidence:** automated pressure cases PC-1, PC-2, PC-4, PC-5 pass in
`tests/Feature/ExecutionCausalityPressureTest.php`; sandbox rollout validated in
`tests/Feature/ArchitectureSandboxValidationTest.php` with
`ARCHITECTURE_SNAPSHOT_FULFILLMENT=true`. Implementation:
`OfferSnapshotService`, `ExecutionRecordService`, sidecar pinning at checkout and
redeem, snapshot-only fulfillment under feature flag.

**Acceptance criterion (met):** at least one full E2E flow (Intent → Entitlement
→ **OfferSnapshot pinned** → Execution → optional Settlement) completes in
production or staging **without violating causality** — no upward derivation, no
exceptions carved in application code.

Additionally, **Appendix A pressure cases** MUST be exercised (automated or manual
audit). Green-path alone is insufficient. Each case MUST demonstrate that plane
boundaries hold under failure and concurrent Knowledge rebuild — not only under
happy-path success.

**Depends on:** [ADR 0037](0037-digital-entitlement-model.md) (Accepted).

## Context

[ADR 0037](0037-digital-entitlement-model.md) defines the domain vocabulary and
**causality**: each plane has its own source of truth; truth flows downward only.

| Plane | Source of truth | Derived from |
|-------|-----------------|--------------|
| **Knowledge** | Publication contracts, provider catalogs | Publishers |
| **Execution** | Execution Record | Intent + Offer snapshot |
| **Settlement** | Ledger / payments | Execution + commercial events |

This ADR is not only about **forbidden crossings**. It defines the **allowed
dependency graph**: which plane may influence which, and which arrows are
permanently banned.

[ADR 0039](0039-offer-routing-policy.md) defines **how** Execution selects an
Offer inside this graph. Routing is an algorithm in the Execution plane — it does
not change the domain model or Knowledge truth.

**Related:** [ADR 0037](0037-digital-entitlement-model.md),
[ADR 0036](0036-meanly-api-dgs-sidecar-boundary.md),
[projection-inventory-and-rebuild-matrix.md](../projection-inventory-and-rebuild-matrix.md).

## Decision

### Allowed dependency graph

```text
Publishers (external)
        │
        ▼
   Knowledge  ─────────────────────────────┐
        │                                  │ read-only:
        │ constrains                       │ Offer candidates,
        ▼                                  │ Entitlement projection
   Execution  ◄── routing (ADR 0039)       │
        │                                  │
        │ constrains                       │
        ▼                                  │
   Settlement                              │
                                           │
   ✗  any arrow pointing upward  ✗  ◄──────┘
```

**Rule:** arrows may point **down** (constrain). Arrows may **never** point **up**
(derive truth).

```text
Knowledge  →  Execution  →  Settlement     ✓  allowed causality
Execution  →  Knowledge                     ✗  forbidden
Settlement →  Execution                     ✗  forbidden
Settlement →  Knowledge                     ✗  forbidden
Knowledge  →  Settlement (skipping Execution) ✗  forbidden
```

Skipping a layer is forbidden. Settlement cannot document issuance without an
Execution Record. Execution cannot invent Entitlements without Knowledge projections.

### Causality vs references

| Permitted | Forbidden |
|----------|-----------|
| `settlement.execution_id` → Execution Record | `order.status = paid` ⇒ mark Issued |
| `execution.offer_snapshot_id` → Offer snapshot | `payment captured` ⇒ fulfillment succeeded |
| `execution.entitlement_id` → Entitlement | `execution succeeded` ⇒ update provider catalog |
| Correlation ids across planes | Inferring Knowledge existence from Settlement |

### Plane inventory

**Knowledge Plane**

```text
Intent          (discovery / liquidity signals)
Entitlement     (CanonicalProductIdentity projection)
Offer           (immutable snapshots)
Brand
Catalog         (ProviderProduct, WildflowCatalog lineage)
Commerce graph  (projections for discovery)
```

**Execution Plane**

```text
Intent resolution   (at checkout — reads Knowledge)
Offer selection     (ADR 0039 — algorithm, not Knowledge write)
Reservation
Fulfillment         (provider dispatch, DGS / Node sidecar)
ExecutionRecord     (append-only issuance fact)
VaultEntry          (secret custody reference)
```

**Settlement Plane**

```text
Order
Invoice
Payment
Refund
Ledger
```

### Knowledge Plane

**Source of truth:** what publishers declare via Publication contracts and catalog
feeds. Rebuildable from upstream sources — never from Execution or Settlement events.

**May:**

- rebuild, reindex, republish projections;
- merge or split Entitlement fingerprints (with audit);
- close and open Offer snapshots (never mutate in place);
- restore catalog from provider sources;
- run for hours without any purchase.

**Must not:**

- derive catalog or identity truth from Orders, Payments, or Execution outcomes;
- modify Execution Records, Vault, or fulfillment audit;
- select the Offer for an in-flight execution.

### Execution Plane

**Source of truth:** Execution Record — what was reserved, dispatched, stored, issued.

**Derived from:** resolved Intent + selected Offer snapshot (immutable reference).

**May:**

- read Knowledge projections (Entitlement, available Offers);
- run `selectOffer()` per ADR 0039;
- write Execution Records and Vault entries;
- retry with a new Offer snapshot (new execution attempt);
- complete without Settlement (test, compensation, admin);
- read **historical Execution metrics** for routing (latency, success rate) — these
  inform ADR 0039 but do not rewrite Knowledge.

**Must not:**

- derive Entitlement or Catalog truth from Settlement state;
- mutate Knowledge rows (Entitlement, Offer snapshots, catalog);
- trigger identity rebuild or catalog sync from fulfillment handlers.

### Settlement Plane

**Source of truth:** ledger entries and commercial document state.

**Derived from:** Execution facts + commercial events (payment captured, refund issued).

**May:**

- create Order / Invoice / Payment linked to `execution_id`;
- capture payment, refund, write ledger;
- exist without Execution (abandoned checkout).

**Must not:**

- prove issuance (Execution Record proves issuance);
- select provider or Offer;
- cause Knowledge updates;
- set Execution status to match payment without an explicit execution event.

### Forbidden crossings (summary)

| Direction | Forbidden |
|-----------|-----------|
| Knowledge → Execution history | Alter past ExecutionRecord, Vault |
| Execution → Knowledge | Mutate Entitlement, Catalog, Offer snapshots |
| Settlement → Execution | Rewrite issued/failed from payment state |
| Settlement → Knowledge | Catalog rebuild, identity merge from orders |
| Any upward arrow | Derive lower-plane truth from higher plane |

### Operational boundaries (current stack)

| Component | Plane | Causality role |
|-----------|-------|----------------|
| `SyncCatalogsCommand` | Knowledge | Ingest publisher truth |
| `catalog:rebuild-identities` | Knowledge | Rebuild projection from catalog |
| `meanly:publish-provider-catalog` | Knowledge | Expose to storefront |
| DGS unified-catalog | Knowledge | Provider catalog read model |
| `fulfillment/issue`, redeem jobs | Execution | Write Execution facts |
| Vault transit | Execution | Secret custody |
| `SellerOfferRankingService` | Execution (target) | Interim; becomes `selectOffer` input |
| `orders`, payment capture | Settlement | Document commerce |
| Shadow ingest | Execution telemetry | Metrics for routing — not Knowledge derivation |

### Rebuild safety

Knowledge projections are **class B rebuildable** from publisher sources only.
Execution and Vault are **not** rebuildable from catalog or orders. Recovery is from
execution audit logs and provider reconciliation.

## Consequences

### Positive

- Clear answer to "where does truth come from?" per plane.
- ADR 0039 routing stays a pure function — no model changes.
- New providers add Publication + Fulfillment contracts without reversing causality.
- Aligns with Simple L1 layer constraints.

### Enforcement (phased)

- **Phase 1:** Causality checklist in code review.
- **Phase 2:** Lint forbidden patterns (e.g. catalog sync triggered from order paid hook).
- **Phase 3:** Plane-separated write APIs.

### Forbidden (global)

- `Order exists` ⇒ create or confirm Entitlement.
- `Payment completed` ⇒ mark fulfillment Issued without Execution Record.
- `Execution succeeded` ⇒ mutate provider catalog or identity fingerprint.
- Single transaction spanning Knowledge rebuild and Execution write.

## Open questions

1. Settlement after Issued — refund in Settlement only, or Execution reversal event?
2. Historical SLA metrics store: Execution-plane table vs read-only analytics replica?
3. RU edge: Knowledge mirror only; confirm zero upward causality from lena Settlement.

## Follow-on

| ADR | Title | Role |
|-----|-------|------|
| **0039** | Offer Routing Policy | `selectOffer()` inside Execution; reads Knowledge Offers |

---

## Appendix A: Pressure cases (acceptance gate for → Accepted)

These scenarios are designed to **break** plane boundaries. Passing the green-path
E2E is necessary but not sufficient. Before promoting this ADR to **Accepted**, each
case below MUST be run and audited. Document: observed behavior, plane writes, and
whether causality held.

### Pinned snapshot invariant

Execution MUST follow:

```text
Intent → Entitlement → OfferSnapshot (fixed once) → Execution
```

Execution MUST NOT re-read live Knowledge on each fulfillment step:

```text
Execution ──✗──► re-fetch catalog / offers on every retry
```

`offer_snapshot_id` on the Execution Record is part of the causality model — not an
optimization.

### Pressure case matrix

| ID | Pressure case | What it tests | Pass criteria |
|----|---------------|---------------|---------------|
| **PC-1** | Provider returns 500 / timeout | Execution failure isolation | Knowledge unchanged; Execution Record = `failed` with audit; no catalog sync side effect |
| **PC-2** | Payment succeeded, fulfillment failed | Settlement ≠ proof of issuance | Settlement shows captured payment; Execution Record ≠ `issued`; no Vault secret; no upward fix to Knowledge |
| **PC-3** | Fulfillment succeeded, payment rolled back | Execution fact survives Settlement reversal | Execution Record remains `issued` with Vault ref; Settlement documents refund/compensation; Execution status not rewritten |
| **PC-4** | Catalog rebuilt during active fulfillment | Knowledge/Execution independence | `catalog:rebuild-identities` or sync runs mid-flight; Execution completes against **pinned** OfferSnapshot; outcome does not flip based on new projection |
| **PC-5** | Provider removed / deactivated SKU after reservation | Snapshot authority over live catalog | SKU absent from post-rebuild Knowledge; Execution still fulfills or fails explicitly per **snapshot** terms — not per current catalog row |
| **PC-6** | Identity merge / fingerprint change during checkout | Entitlement stability at Execution boundary | Checkout started with `entitlement_id` / snapshot; Knowledge merge does not orphan or retarget in-flight Execution |
| **PC-7** | Compensation issuance without Order | Execution without Settlement | Admin/comp re-issue creates Execution Record + Vault; no synthetic Order required; Settlement plane untouched |

### Automated audit results (2026-07-02)

| Case ID | Test | Result | Notes |
|---------|------|--------|-------|
| **PC-1** | `ExecutionCausalityPressureTest::test_pc1_provider_500_does_not_touch_knowledge` | PASS | Execution `failed` / `PROVIDER_500`; Knowledge fingerprint unchanged; catalog still active |
| **PC-2** | `ExecutionCausalityPressureTest::test_pc2_payment_ok_fulfillment_fail` | PASS | Order captured; Execution ≠ `issued`; Vault empty |
| **PC-4** | `ExecutionCausalityPressureTest::test_pc4_catalog_rebuild_mid_flight` | PASS | Catalog rebuilt to `REBUILT-SKU-999`; provider received pinned SKU `4401` |
| **PC-5** | `ExecutionCausalityPressureTest::test_pc5_sku_deactivated_after_reservation` | PASS | Live SKU deactivated; fulfillment used snapshot SKU |

### Integration module review checklist (causality)

Each new integration module or provider MUST pass this review before merge:

1. **Knowledge isolation:** Does the module read `products` / `listings` / live catalog
   during Execution? Answer must be **No** — only via pinned `OfferSnapshot`.
2. **Settlement ≠ Execution:** When `execution_records.state = failed` but payment
   captured, does the system avoid treating Settlement as proof of issuance?
3. **Idempotency:** Is `execution_records.idempotency_key` passed to external gateways
   without mutating order state for deduplication?

### Audit template (per case)

```text
Case ID:
Date / environment:
Trigger:
Knowledge writes observed:     [ none | list ]
Execution writes observed:     [ ExecutionRecord status, offer_snapshot_id, Vault ]
Settlement writes observed:    [ none | list ]
Causality violations:          [ none | describe ]
OfferSnapshot pinned at start: [ yes / no — FAIL if no ]
Result:                        [ PASS | FAIL ]
```

### Causality checklist (quick reference)

Use with pressure cases — not as a substitute.

| Check | Pass |
|-------|------|
| Knowledge truth derived only from publishers | ☐ |
| Execution truth derived only from Intent + OfferSnapshot | ☐ |
| Settlement truth derived only from Execution + commercial events | ☐ |
| No upward arrow (Settlement → Execution → Knowledge) | ☐ |
| No skip (Settlement without Execution for issuance) | ☐ |
| OfferSnapshot fixed before first fulfillment dispatch | ☐ |

### Failure signals (do not accept ADR if observed)

- Application code contains `Exception: Settlement may…` or `if (paid) markIssued()`.
- Fulfillment handler triggers `wildflow:sync-catalogs` or identity rebuild.
- Retry re-selects Offer from live catalog without new Execution attempt record.
- `orders.status` used as sole proof that a secret exists in Vault.

