# ADR 0039: Offer Routing Policy

## Status

**Proposed** — 2026-07

**Acceptance criterion (→ Accepted):** minimum **two independent Providers** offer
the same Entitlement; `selectOffer()` is implemented and exercised in Execution;
routing policy choice materially affects provider selection (not degenerate
single-provider `return only_candidate`).

**Why Proposed:** with one provider, routing collapses to trivial selection. Real
pressure (cost, SLA, region, quotas, degradation, preferred provider) appears only
with competing Offers.

**Depends on:** [ADR 0037](0037-digital-entitlement-model.md) (Accepted),
[ADR 0038](0038-knowledge-execution-plane-boundary.md) (Accepted).

## Context

[ADR 0037](0037-digital-entitlement-model.md) defines **what** exists: Intent,
Entitlement, Offer, Execution, Settlement.

[ADR 0038](0038-knowledge-execution-plane-boundary.md) defines **what may influence
what**: causality flows Knowledge → Execution → Settlement only; upward derivation
is forbidden.

This ADR defines **how** Execution selects one Offer among many candidates for a
given Intent and Entitlement. Routing is:

- an **algorithm** in the Execution plane;
- **not** part of the Knowledge domain model;
- **not** allowed to mutate Knowledge or derive truth upward.

Changing routing policy must not require catalog schema changes, identity rebuilds,
or Settlement model changes.

**Related:** [ADR 0037](0037-digital-entitlement-model.md),
[ADR 0038](0038-knowledge-execution-plane-boundary.md),
`SellerOfferRankingService` (interim implementation).

## Decision

### Routing as a pure function

After Intent resolves to an Entitlement, Execution invokes:

```text
selectOffer(
    intent: Intent,
    entitlement: Entitlement,
    available_offers: Offer[],      // read from Knowledge; immutable snapshots
    policy: RoutingPolicy,
    signals?: ExecutionSignals      // optional: historical SLA, latency, budget
) -> OfferSnapshot
```

**Output:** `OfferSnapshot` — immutable reference stored on the Execution Record.
Execution then proceeds; Knowledge is not written.

```text
Intent → Entitlement → selectOffer() → OfferSnapshot → Execution → Settlement?
                              ▲
                              │
                    reads Knowledge Offers
                    reads Execution history (metrics only)
```

### RoutingPolicy (pluggable)

Policies are configuration or strategy objects — not database entities in Knowledge.

| Policy | Selects by |
|--------|------------|
| `LowestPrice` | Minimum buyer price for entitlement |
| `HighestAvailability` | In-stock, non-pre-order preference |
| `LowestLatency` | Historical p50 fulfillment time |
| `HighestSuccessRate` | Historical issue success ratio |
| `PreferredProvider` | Explicit provider priority list |
| `Weighted` | Score blend (price, SLA, margin, load) |
| `CostVsSla` | Trade-off curve per market |

Policies may be composed (e.g. `Weighted` with market-specific weights). Policy
changes apply to **future** `selectOffer()` calls only; past Execution Records
retain their `offer_snapshot_id`.

### Inputs and boundaries

| Input | Plane | May influence selection? |
|-------|-------|--------------------------|
| `available_offers` | Knowledge (read) | Yes |
| Entitlement fingerprint | Knowledge (read) | Yes |
| Intent signals (market, locale) | Intent | Yes |
| Historical SLA / latency | Execution metrics (read) | Yes |
| Payment method | Settlement | Only if policy explicitly includes it |
| Order status | Settlement | **No** — forbidden upward causality |
| Live catalog rebuild state | Knowledge | **No** — offers already snapshotted at read time |

### Failure and retry

If selected Offer fails fulfillment:

1. Write Execution attempt fact (failed).
2. Call `selectOffer()` again with failed offer excluded (or deprioritized).
3. New `OfferSnapshot` → new Execution attempt.
4. Do **not** mutate the failed Offer snapshot in Knowledge.

Maximum retry count and fallback policy are Execution configuration.

### Interim mapping

Today `SellerOfferRankingService::rankedOffersForProviderProduct()` approximates
ranking inside the Knowledge/seller listing layer. Target state:

- Knowledge publishes immutable `Offer` snapshots per Entitlement + Provider.
- Execution calls `selectOffer()` at checkout / redeem time.
- `best_offer_product_id` on `CanonicalProductIdentity` becomes a **denormalized
  cache** for storefront display — not the routing authority.

Storefront may **display** the cached best offer; Execution **selects** at runtime.

### Default policy (Phase 1)

Until Offer snapshots exist as first-class entities:

```text
RoutingPolicy = Weighted(
    price: 0.5,
    success_rate: 0.3,
    latency: 0.2
)
```

First-party Meanly shop offers and provider-network offers compete under the same
policy when multiple Offers exist for one Entitlement.

## Consequences

### Positive

- Routing evolves independently of catalog and Settlement.
- A/B tests = policy variant on `selectOffer()`, not schema migration.
- Simulation: replay historical Intents against new policy on archived Offer sets.
- Completes the ADR trilogy: vocabulary (0037) → causality (0038) → selection (0039).

### Forbidden

- Routing policy that writes to Knowledge (catalog, identity, offer snapshots).
- Routing that infers Entitlement from Order or Payment state.
- Mutating `OfferSnapshot` after selection.
- Skipping `offer_snapshot_id` on Execution Record.

### Migration

- **Phase 1:** Document `selectOffer()` contract; align ranking service inputs.
- **Phase 2:** First-class Offer snapshots in Knowledge; Execution reads them.
- **Phase 3:** Policy registry per market / channel; metrics from Execution history.

## Open questions

1. Per-Entitlement policy override vs global market default?
2. Margin-aware routing: commercial signal in Execution or Settlement?
3. Pre-order offers: separate policy branch or `HighestAvailability` exclusion?
