# Public Pricing Projection

## Contract

Storage prices are internal. Projected prices are public.

```text
Product.price_rub
  -> PricingProjectionService
  -> public amount / currency / label
  -> storefront, catalog, search, facts, JSON-LD
```

Public read models must not render storage price columns directly.

Forbidden in public rendering surfaces:

- `price_rub`
- `old_price_rub`
- `purchase_price_rub`

Use projected fields instead:

- `display_price`
- `price.amount`
- `price.currency`
- `price.label`

## Context Boundaries

`price_rub` remains the storage baseline for current products and can still be used by internal bounded contexts:

- checkout
- order creation
- settlement
- accounting
- partner dashboards
- ops dashboards
- ranking metrics

Those contexts handle storage, payment, or operational truth. They are not public price read models.

## Dependency Direction

```text
MarketContext
  -> PricingContext
  -> PricingProjectionService

LocaleContext
  -/-> PricingContext
```

`PricingContext` is derived from `MarketContext`, not from UI locale. Locale can change language and formatting behavior, but it must not change settlement, storage, or display currency selection for a market.

`PricingProjectionService` is the public price read-model boundary:

```text
PricingContext
  selects currencies

FinanceService
  performs conversion math

PricingProjectionService
  combines both into public price output
```

## Public Surfaces

The public projection rule applies to:

- storefront pages and cards
- catalog pages
- provider network pages
- public product pages
- public product search API
- product facts
- JSON-LD / SEO price output
- future public HTTP resources

If a public page needs to show a price, it should receive a projection from application code or render an already projected `price.label`.

## Guardrail

`tests/Feature/PublicPricingProjectionGuardrailTest.php` enforces the rule for public rendering paths:

```text
resources/views/storefront
resources/views/catalog
resources/views/network
resources/views/products
resources/views/landing.blade.php
app/Http/Resources/Public
```

The test intentionally excludes checkout, partner, ops, and accounting paths because those areas may legitimately read storage prices.

## Phase Boundary

This contract completes the public read-model slice:

```text
Phase 1   MarketContext
Phase 2   PricingContext
Phase 3   Public PricingProjectionService
Phase 3.5 Public rendering guardrail
```

Checkout, orders, and settlement are later phases. Do not route public price rendering around `PricingProjectionService` while those phases remain storage-backed.
