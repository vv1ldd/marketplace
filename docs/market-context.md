# Market Context

## Context

Meanly uses one Laravel backend for multiple semantic markets. Local domains do not create separate applications, indexes, or catalogs. They provide an interpretation context for the same canonical model.

Market domains are host aliases, not subdomain conventions. A market can be reached through any domain:

```text
meanly.one
meanly.ar
meanly.ru
another-local-brand.example
```

```text
Domain
  ↓
MarketContext
  ↓
Search / Signals / Discovery
  ↓
SearchProfile + governance
```

## Invariant

MarketContext parameterizes interpretation. It does not fragment the system.

Context affects defaults, not truth:

- it does not mutate SearchProfile
- it does not create local search engines
- it does not duplicate catalog facts
- it does not bypass governance

## Dimensions

- `country`: where a user or external signal comes from
- `locale`: language of interface or intent
- `market`: business grouping
- `product_region`: catalog geography of an item
- `domain`: entry point into the same backend

Example:

```text
domain: meanly.ar
query: playstation usa

market: latam_ar
locale: es
demand_region: AR
preferred_product_regions: AR, US, TR
product_region in query: US
```

## Configuration

Markets are defined in `config/markets.php`.

Default local domains:

```text
meanly.test
ar.marketplace.test
ru.marketplace.test
```

Default production domains and independent aliases:

```text
meanly.one
www.meanly.one
ar.marketplace.one
ru.marketplace.one
meanly.ar
meanly.ru
```

The domains must also be included in `APP_PUBLIC_DOMAINS`, because storefront routes are domain-scoped. Market aliases can be configured independently:

```dotenv
APP_PUBLIC_DOMAINS=meanly.one,www.meanly.one,meanly.test,meanly.ar,meanly.ru,marketplace.one,www.marketplace.one
MARKET_GLOBAL_DOMAINS=meanly.one,www.meanly.one,meanly.test,marketplace.one,www.marketplace.one
MARKET_LATAM_AR_DOMAINS=meanly.ar,ar.marketplace.one,ar.marketplace.test
MARKET_RU_DOMAINS=meanly.ru,ru.marketplace.one,ru.marketplace.test
```

Keep sessions domain-local unless there is a deliberate cross-domain auth requirement:

```dotenv
SESSION_DOMAIN=null
```

Simple L1 remains the shared identity layer across domains.

## Runtime

`ResolveMarketContext` runs before `SetLocale`.

It resolves host to `MarketContext`, stores it in the container, attaches it to the request attributes, shares it with views, and emits:

```text
X-Market: latam_ar
```

Use the helper when code needs the current market:

```php
market()->market;
market()->locale;
market()->currency;
market()->demandRegion;
market()->preferredProductRegions;
```

Locale priority stays governed by `LocaleResolver`:

```text
query/session/profile/legal entity/profile region/explicit market/browser/app
```

The global market does not override browser language. Explicit local markets like `latam_ar` and `ru` provide a default locale.
