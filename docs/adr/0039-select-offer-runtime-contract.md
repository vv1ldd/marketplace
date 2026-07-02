# ADR 0039 — Runtime Contract Draft: `selectOffer()`

## Status

**Draft** — 2026-07-02

Companion to [0039-offer-routing-policy.md](0039-offer-routing-policy.md). Defines the
**implementable contract** for competitive provider routing at checkout / redeem time.

**Current code anchor:** `App\Services\Architecture\OfferRoutingService::selectOffer()`.

**Acceptance gate (unchanged from ADR):** ≥2 independent providers on the same Entitlement;
routing choice materially affects selection; Execution Records always carry `offer_snapshot_id`.

---

## 1. Boundary

```text
Intent (buy:steam-wallet:20:USD:TR)
  → Entitlement (CanonicalProductIdentity)
  → available_offers[]          // read-only Knowledge projection
  → selectOffer(...)            // Execution plane — pure function
  → OfferSnapshot               // immutable pin
  → ExecutionRecord
  → Settlement?
```

**Forbidden:** writing Knowledge, mutating past snapshots, inferring Entitlement from Order/Payment.

---

## 2. PHP signature (target)

```php
public function selectOffer(
    ?string $intentKey,                          // e.g. buy:steam-wallet:20:USD:TR
    CanonicalProductIdentity $entitlement,
    Collection $availableOffers,                 // OfferCandidate[]
    ?RoutingPolicy $policy = null,               // market default if null
    ?ExecutionSignals $signals = null,          // metrics + circuit state
    ?array $excludeProviderIds = [],             // retry / failover only
): ?OfferSnapshotData;
```

`rankOffers()` remains public for simulation, A/B replay, and ops dashboards.

---

## 3. OfferCandidate (input DTO)

Each element in `available_offers` is a **read projection**, not a Knowledge write:

```json
{
  "offer_id": "offer:provider:42:product:881",
  "product_id": 881,
  "provider_id": 42,
  "provider_type": "wildflow-sandbox",
  "provider_product_id": 1204,
  "sku": "SELLER-WF-ABC123",
  "provider_sku": "WF-ABC123",
  "price": {
    "amount": 19.99,
    "currency": "USD",
    "buyer_price_cents": 1999
  },
  "margin": {
    "buyer_price_cents": 1999,
    "purchase_price_cents": 1650,
    "margin_bps": 1746
  },
  "availability": {
    "in_stock": true,
    "stock_count": 14,
    "fulfillment_mode": "provider_code",
    "preorder": false
  },
  "ranking": {
    "score": 0.82,
    "metrics": {
      "stock_count": 14,
      "seller_completed_90_days": 312,
      "success_rate_7d": 0.991,
      "p50_fulfillment_ms_7d": 1840
    }
  },
  "snapshot_hint": {
    "offer_snapshot_id": null,
    "valid_until": "2026-07-02T12:00:00Z"
  }
}
```

**Source (interim):** `SellerOfferRankingService::rankedOffersForProducts()` +
provider catalog rows. **Target:** first-class Knowledge `Offer` snapshots per Entitlement.

---

## 4. RoutingPolicy (configuration)

Policy is **Execution configuration** (config file / ops table), not a Knowledge entity.

```json
{
  "policy_id": "weighted_v1",
  "version": "2026-07-02",
  "method": "weighted",
  "market": "meanly_storefront",
  "weights": {
    "price": 0.35,
    "margin_bps": 0.20,
    "success_rate": 0.25,
    "latency": 0.10,
    "availability": 0.10
  },
  "provider_split": [
    { "provider_id": 42, "traffic_weight": 70 },
    { "provider_id": 77, "traffic_weight": 30 }
  ],
  "circuit_breaker": {
    "enabled": true,
    "trip_metrics": [
      "architecture.anomaly.settlement_without_execution",
      "architecture.anomaly.execution_status_mismatch"
    ],
    "trip_threshold": 3,
    "window_seconds": 900,
    "cooldown_seconds": 1800,
    "open_weight": 0
  },
  "constraints": {
    "require_in_stock": true,
    "exclude_preorder": false,
    "min_margin_bps": 500,
    "max_buyer_price_cents": null
  },
  "retry": {
    "max_attempts": 2,
    "exclude_failed_provider": true
  }
}
```

### 4.1 Weighted score

For each candidate `o` after hard filters:

```text
score(o) =
    w_price     * norm_inverse_price(o)
  + w_margin    * norm_margin_bps(o)
  + w_success   * norm_success_rate(o)
  + w_latency   * norm_inverse_latency(o)
  + w_avail     * norm_stock(o)
```

Normalization is per-request min/max across the candidate set (0..1). Ties break on
lower `provider_id` for determinism.

### 4.2 Weighted traffic split

When multiple providers survive filters:

1. Compute `score(o)` for each offer.
2. Group by `provider_id`; provider score = max score among its offers.
3. Apply **effective traffic weight**:
   - `effective_weight(p) = base_traffic_weight(p)` if circuit closed
   - `effective_weight(p) = circuit.open_weight` (default 0) if circuit open
4. Draw provider using weighted random **or** deterministic sticky hash:

```text
bucket = crc32(entitlement.fingerprint + intentKey + policy.version) % 100
```

Map bucket to cumulative `effective_weight` ranges. Selected provider → highest-scoring
offer within that provider.

**Deterministic sticky routing** is default (replay-friendly). Random split is opt-in
per policy flag for soak tests.

### 4.3 Circuit breaker

**Signals** (read Execution metrics only):

| Metric key | Trip meaning |
|------------|----------------|
| `architecture.anomaly.settlement_without_execution` | Paid without Execution |
| `architecture.anomaly.execution_status_mismatch` | Order vs Execution divergence |
| `architecture.execution.fallback_live_catalog_count` | Snapshot bypass pressure |

**State per provider** (cache key `routing:circuit:{provider_id}`):

```json
{
  "state": "closed|open|half_open",
  "failure_count": 0,
  "opened_at": null,
  "last_failure_at": null
}
```

Trip when `failure_count >= trip_threshold` inside `window_seconds`. While open,
`effective_weight = 0`; traffic falls through to next provider in split. After
`cooldown_seconds`, transition to `half_open` (single probe attempt).

Circuit state changes **do not** write Knowledge. They only affect future `selectOffer()`.

---

## 5. ExecutionSignals (optional input)

```json
{
  "request_id": "chk_9f2a",
  "channel": "meanly_storefront",
  "locale": "en",
  "buyer_l1_address_hash": "sha256:…",
  "circuit_state": {
    "42": { "state": "open", "opened_at": "2026-07-02T04:10:00Z" }
  },
  "budget": {
    "max_buyer_price_cents": 2200
  }
}
```

If `signals.circuit_state` is omitted, service loads from cache via `ArchitectureMetrics`.

---

## 6. Output

### 6.1 Success

Returns `OfferSnapshotData` (existing DTO). Persisted as `offer_snapshots` row; referenced
by `execution_records.offer_snapshot_id`.

Audit payload on Execution start:

```json
{
  "routing": {
    "policy_id": "weighted_v1",
    "policy_version": "2026-07-02",
    "method": "weighted",
    "intent_key": "buy:steam-wallet:20:USD:TR",
    "entitlement_fingerprint": "…",
    "selected_provider_id": 77,
    "selected_product_id": 881,
    "candidate_count": 4,
    "excluded_provider_ids": [],
    "circuit_tripped": [42],
    "score": 0.87,
    "split_bucket": 54
  }
}
```

### 6.2 Failure modes

| Condition | HTTP / Execution | Behavior |
|-----------|------------------|----------|
| No candidates after filters | 422 `no_eligible_offer` | Block checkout |
| Single provider, policy requires competition | 422 `degenerate_routing` | ADR not Accepted |
| Snapshot pin failed | 422 `snapshot_pin_failed` | Do not start Execution |
| Selected provider fails fulfillment | Execution `failed` | Retry `selectOffer(excludeProviderIds: [42])` |

---

## 7. Retry contract (failover)

On fulfillment failure:

```php
$snapshot = $routing->selectOffer(
    $intentKey,
    $entitlement,
    $offers,
    $policy,
    $signals,
    excludeProviderIds: [$failedProviderId],
);
```

Max attempts = `policy.retry.max_attempts`. Each attempt → **new** `OfferSnapshot`.
Never mutate the failed snapshot.

---

## 8. Implementation phases

| Phase | Deliverable | Touches |
|-------|-------------|---------|
| **A** | `RoutingPolicy` value object + config `config/routing.php` | `OfferRoutingService` | **Done** (flag `ROUTING_WEIGHTED_ENABLED`) |
| **B** | Weighted score + deterministic split | `rankOffers()` refactor |
| **C** | Circuit breaker reader on `ArchitectureMetrics` | cache + ops alert hook |
| **D** | Multi-provider `availableOffersForEntitlement()` | Knowledge read path |
| **E** | Checkout / `StorefrontFulfillmentService` wire-up | Execution start |
| **F** | Simulation CLI: replay intents on archived offer sets | ops tooling |

**Phase A–B** can ship behind `ROUTING_POLICY=weighted_v1` feature flag with a single
provider (no behavioral change) until Phase D supplies competition.

---

## 9. Test matrix (minimum)

1. **Weighted split** — two providers 70/30; 1000 deterministic buckets ≈ 70/30 ±5%.
2. **Circuit trip** — inject 3 `settlement_without_execution`; provider weight → 0.
3. **Margin floor** — exclude offers below `min_margin_bps`.
4. **Retry** — failed provider excluded on second `selectOffer()`.
5. **Causality** — no Knowledge rows written during routing (ADR 0038 guard).

---

## 10. Open decisions

1. Per-Entitlement policy override vs market default only?
2. Margin signal: buyer price only, or include partner credit / FX?
3. Sticky hash vs random split for production traffic?
4. Export circuit state to Meanly ops dashboard alerts?

---

## References

- [ADR 0037 — Digital Entitlement Model](0037-digital-entitlement-model.md)
- [ADR 0038 — Knowledge / Execution Boundary](0038-knowledge-execution-plane-boundary.md)
- [ADR 0039 — Offer Routing Policy](0039-offer-routing-policy.md)
- `OfferRoutingService`, `ArchitectureMetrics`, `ExecutionCausalityPressureTest`
