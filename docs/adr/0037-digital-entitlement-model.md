# ADR 0037: Digital Entitlement Model

## Status

**Accepted** — 2026-07

**Acceptance criterion:** domain vocabulary (Intent, Entitlement, Offer, Execution,
Settlement) is stable and MUST be used in all new architecture documents, ADRs, and
API contracts. Implementation may lag; terminology does not.

**Not gated on:** routing policy (ADR 0039), causality enforcement in code (ADR 0038),
or schema rename of `CanonicalProductIdentity`.

## Context

Meanly has completed the **catalog pipeline** (provider sync → canonical identities
→ commerce graph → storefront publish). The platform can expose thousands of digital
goods on a storefront.

The deeper product is not a **product catalog**. It is a **marketplace of digital
entitlements**: rights to receive a specific digital good (gift card balance,
subscription period, license key, in-game currency, etc.) that may be fulfilled
through multiple providers.

Today the system often reasons in legacy commerce terms:

```text
Product → Order → Provider
```

That model treats the seller SKU and the provider as the object of truth. Routing,
pricing, and fulfillment are coupled to catalog rows. Adding a direct brand publisher
or a second aggregator requires special cases.

This ADR defines the **domain vocabulary** that replaces Product/Order/Provider as
the primary semantic chain. Subsequent ADRs (Knowledge/Execution boundary, routing
policy, publication contracts) MUST reference these terms without redefining them.

**Related:** [ADR 0017](0017-storefront-projection-contract.md) (projection contract),
[ADR 0022](0022-capabilities-are-granted-identities-are-not-created.md) (capabilities),
[ADR 0034](0034-regional-supply-contour-boundary.md) (supply contours),
[ADR 0036](0036-meanly-api-dgs-sidecar-boundary.md) (DGS boundary).

## Decision

### Object of truth

The system guarantees correctness about **what entitlement was requested**, **which
offer snapshot was selected**, and **what execution actually occurred** — not about
a particular provider SKU, seller product row, or commercial order document at
fulfillment time.

```text
Intent → Entitlement → Offer → Execution → Settlement (optional)
```

Each layer answers a different question:

| Layer | Question | Stability |
|-------|----------|-----------|
| **Intent** | What does the user want? | Ephemeral / session-scoped |
| **Entitlement** | What exactly must be received? | Stable domain identity |
| **Offer** | How can it be fulfilled right now? | Time-bounded, immutable snapshots |
| **Execution** | What actually happened? | Append-only fact log |
| **Settlement** | How was it paid for / documented commercially? | Optional; orthogonal to issuance |

### Causality (source of truth per plane)

Each plane has its own source of truth. Lower planes **constrain** upper planes in
the dependency graph; they do not **derive** them.

| Plane | Source of truth | Derived from |
|-------|-----------------|--------------|
| **Knowledge** | Publication contracts, provider catalogs | Publishers (external authority) |
| **Execution** | Execution Record | Intent + Offer snapshot |
| **Settlement** | Ledger, payments, commercial documents | Execution + commercial events |

**Allowed causality (downward only):**

```text
Knowledge  →  Execution  →  Settlement
```

**Forbidden derivation (upward causality):**

```text
Knowledge  ✗←  Execution
Execution  ✗←  Settlement
Settlement ✗←  Knowledge
```

Backward **references** (foreign keys, correlation ids) are permitted. Backward
**inference of truth** is not.

| Forbidden inference | Why |
|---------------------|-----|
| Order exists → Entitlement exists | Settlement does not create Knowledge |
| Payment completed → Fulfillment occurred | Settlement does not prove Execution |
| Execution succeeded → Update provider catalog | Execution does not rewrite Knowledge |

This aligns with Simple L1: **layer constrains layer**, not component owns component.
Knowledge constrains what Execution may attempt; Execution constrains what Settlement
may document — never the reverse.

Causality invariants and the allowed dependency graph are normative in
[ADR 0038](0038-knowledge-execution-plane-boundary.md).

### Migration rule

Large systems migrate safely when changes are sequenced:

```text
1. Model      — define what is true (this ADR)
2. Vocabulary — adopt terms in ADRs, APIs, ops language
3. Names      — rename classes, tables, endpoints (optional, last)
```

**Do not** mix domain model change with terminology rename in the same step. If a
regression appears, the cause must be isolatable.

### Entitlement naming (no premature rename)

`CanonicalProductIdentity` already carries Entitlement semantics and is referenced
across the Knowledge Plane. **Do not rename** the class, table, or API in Phase 1.

```text
CanonicalProductIdentity
        │
        │  semantic alias (ADR 0037)
        ▼
Entitlement
        │
        │  after migration stabilizes (optional)
        ▼
rename in code / schema
```

**Domain assertion:** `CanonicalProductIdentity` **is** an Entitlement projection.
New documentation and ADRs use **Entitlement**; existing code keeps
`CanonicalProductIdentity` until a dedicated rename phase proves worthwhile.

### Three planes (entity placement)

| Plane | Entities | Role |
|-------|----------|------|
| **Knowledge** | Intent (discovery), Entitlement, Offer, Brand, Catalog | What exists |
| **Execution** | Reservation, Fulfillment, ExecutionRecord, VaultEntry | What was issued |
| **Settlement** | Order, Invoice, Payment, Refund, Ledger | How commerce was documented |

**Order is not Execution.** Execution is the primary fact of issuance. Settlement
documents are one way to frame and pay for execution — not the other way around.

Execution **can exist without Order**:

- internal smoke / integration tests;
- re-issue or compensation delivery;
- data migration or administrative issuance;
- provider retry after settlement failure.

Forbidden crossings between planes are defined in [ADR 0038](0038-knowledge-execution-plane-boundary.md).

### 1. Intent

An **Intent** is a user- or agent-expressed desire to obtain an entitlement.

Examples:

- `"I want Steam Wallet 20 USD"`
- `"Buy PlayStation Plus 12 months TR"`
- Search query + checkout gesture resolving to a purchase goal

**Properties (conceptual):**

- `intent_id` — correlation id for the purchase journey
- `intent_key` — normalized key (e.g. `buy:steam-wallet:20:USD`)
- `actor` — SL1E subject or anonymous session
- `signals` — locale, market, payment rail preference
- `created_at`

**Not an Intent:** shopping-cart line item, provider SKU, or order row.

**Existing code (partial):** `IntentLiquidityGraph`, `IntentLiquidityNode`,
`buy:commerce:{slug}` corridors, SL1E `Intent` (authorization surface — distinct
namespace but compatible philosophy).

### 2. Entitlement

An **Entitlement** is the platform-stable definition of *what* must be delivered,
independent of *who* fulfills it.

Examples:

- Steam Wallet — face value 20 USD — region US
- Spotify Premium — 1 month — region TR

**Persistence today:** `CanonicalProductIdentity` (semantic alias: Entitlement).

**Properties (conceptual):**

- `entitlement_id` / `identity_id`
- `fingerprint` — stable hash of semantic signals (brand, face value, region, category)
- `identity_slug` — public URL key
- `brand`, `face_value`, `face_value_currency`, `region`, `platform`
- `capabilities` — redeemable surfaces, activation constraints

Multiple providers attach **Offers** to the same Entitlement. The buyer sees one
product family; the platform sees many fulfillment paths.

### 3. Offer

An **Offer** is an immutable, time-bounded statement that a specific **Provider**
can fulfill a specific **Entitlement** under stated commercial and operational
terms **at a point in time**.

Offers are **not** recomputed views of `Product` + ranking. Each published offer is
an append-only snapshot.

**Properties (conceptual):**

- `offer_id`
- `entitlement_id`
- `provider_id`
- `provider_sku` / `service_sku`
- `price`, `currency`
- `region`, `capabilities`
- `inventory_state` — in_stock, pre_order, out_of_stock
- `sla_signals` — success_rate, latency_p50, availability
- `valid_from`, `valid_until`
- `confidence`

When an offer changes (price drop, stock out), the platform **closes** the old
offer snapshot and **opens** a new one. History is retained for audit, replay, and
routing simulation.

**Today (legacy mapping):**

| Legacy | Role relative to Offer |
|--------|------------------------|
| `ProviderProduct` | Provider-side catalog fact feeding offer creation |
| `Product` + `ProductSalesChannel` | Seller listing; interim offer projection |
| `best_offer_product_id` on identity | Denormalized selected offer pointer |

**Target:** first-class `offers` table (or equivalent) with immutable snapshots;
`Product` becomes a seller-specific projection, not the routing source of truth.

### 4. Execution

**Execution** is the append-only record of what the platform actually did to
satisfy an Intent against a chosen Offer snapshot.

Phases:

```text
Reservation → Fulfillment dispatch → Secret custody (Vault) → Issued
```

**Execution Record (conceptual)** — primary fact of issuance:

```text
execution_id
intent_id
entitlement_id
offer_snapshot_id      # immutable reference, not live offer
reservation_id
fulfillment_id
provider_id
vault_secret_ref
status                   # reserved | fulfilling | issued | failed
issued_at
audit_trail
```

Execution Records are **not** Orders. An Execution Record may exist with
`order_id = null`.

### 5. Settlement (optional)

**Settlement** frames execution in commercial terms: payment capture, invoicing,
refunds, ledger entries, marketplace channel hooks (Yandex Market, etc.).

```text
settlement_id
execution_id             # optional back-link; execution is primary
order_id                 # compatibility artifact
invoice_id
payment_id
amount, currency
status
```

When a customer purchases through the storefront, Settlement typically follows
Execution. When ops issues a compensatory code, Execution may complete with no
Settlement document at all.

**Today:** `orders`, `order_items`, payment rails, and ledger projections live in
the Settlement plane. They MUST reference Execution facts, not replace them.

### Semantic flow (customer purchase)

```text
User: "I want Steam Wallet 20 USD"
        │
        ▼
   Intent (resolve)
        │
        ▼
   Entitlement: Steam Wallet 20 USD
        │
        ▼
   Offer Selection Policy  ← ADR 0039
        │
        ├── Offer A  EzPin       $18.90  SLA 99.2%
        ├── Offer B  Valve       $18.40  SLA 99.9%  ← selected
        └── Offer C  InComm      $18.75  SLA 98.8%
        │
        ▼
   Execution (snapshot Offer B)
        │
        ├── Credit reserved
        ├── Provider fulfillment 200/202
        ├── Secret stored in Vault
        └── Issued to buyer
        │
        ▼
   Settlement (optional)
        │
        ├── Order / Invoice
        ├── Payment capture
        └── Ledger entry
```

The buyer selects an **Entitlement** (or accepts a resolved Intent). The platform
selects an **Offer**. **Execution** records what was issued. **Settlement** records
how it was paid for — when applicable.

## Mapping from legacy model

| Legacy term | Plane | Entitlement model role |
|-------------|-------|------------------------|
| `CanonicalProductIdentity` | Knowledge | Entitlement (semantic alias; no rename yet) |
| `ProviderProduct` | Knowledge | Input to Offer creation |
| `Product` (shop listing) | Knowledge | Seller offer projection (interim) |
| `WildflowCatalog` | Knowledge | Provider sync lineage |
| `orders` / `order_items` | Settlement | Commercial document; not execution authority |
| EzPin | Both | Provider: Publication (Knowledge) + Fulfillment (Execution) |
| Brand Direct (future) | Both | Provider — not a special account type |

## Consequences

### Positive

- Platform independent of specific providers and specific commercial flows.
- Execution testable and auditable without creating fake customer orders.
- EzPin, Valve Direct, InComm become peers on the same Entitlement.
- Routing policy evolves without catalog schema changes (ADR 0039).
- Aligns with Simple L1: upper layers express meaning; lower layers express mechanism.

### Migration phases

- **Phase 1 (now):** Adopt vocabulary; `CanonicalProductIdentity == Entitlement`.
  No schema or class rename.
- **Phase 2:** Introduce `offers` + immutable snapshots; backfill from `ProviderProduct`.
- **Phase 3:** Introduce `execution_records`; link existing `orders` for replay parity.
- **Phase 4:** Checkout routes Intent → Entitlement → Offer; Settlement optional shell.
- **Phase 5 (optional):** Rename `CanonicalProductIdentity` → `Entitlement` in schema.

### Forbidden (domain level)

- Treating `Product.id` or provider SKU as the routing key at fulfillment time.
- Mutating a published Offer in place (changes require new snapshot).
- Treating `Order` as proof of issuance (Execution Record is proof).
- Adding brand publishers as a special-case bypass of Provider contracts.
- Deriving Entitlement semantics in the storefront frontend (ADR 0017).

Plane-level forbidden crossings: [ADR 0038](0038-knowledge-execution-plane-boundary.md).

## Open questions

1. Single `offers` table vs `offer_snapshots` + `offer_current` pointer?
2. How does `IntentLiquidityGraph` relate to purchase Intent — merge or parallel tracks?
3. Execution Record vs SL1 Intent ledger — boundary for cross-protocol audit?
4. When is Settlement mandatory vs optional per market / channel?

## ADR trilogy — maturity

| ADR | Status | Criterion to advance |
|-----|--------|-------------------|
| **0037** (this document) | **Accepted** | Terminology stable; used in new docs |
| **0038** | Accepted | Green-path E2E **+ Appendix A pressure cases** PC-1, PC-2, PC-4, PC-5 (automated) |
| **0039** | Proposed | ≥2 Providers + working `selectOffer()` under real competition |

Canon = invariants (source of truth, causality direction, allowed dependencies,
moment of Offer selection). Services, tables, and APIs are current embodiment only.
