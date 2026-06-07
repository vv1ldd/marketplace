# Full UI Projection Inventory

This inventory tracks Laravel Blade UI surfaces that are now represented by Next routes backed by Laravel projection APIs.

## Public Buyer

| Legacy surface | Next projection | Laravel projection |
| --- | --- | --- |
| `/store/*` | `frontend/app/store/[[...path]]/page.jsx` | `/api/ui/v1/projections/store/{path}` plus Storefront APIs |
| `/catalog/*` | `frontend/app/catalog/[...path]/page.jsx` | `/api/ui/v1/projections/catalog/{path}` plus Storefront catalog APIs |
| `/catalog-network/*` | `frontend/app/catalog-network/[[...path]]/page.jsx` | `/api/ui/v1/projections/catalog-network/{path}` |
| `/products-search` | `frontend/app/products-search/page.jsx` | `/api/ui/v1/projections/products-search` |
| `/meanly-ai` | `frontend/app/meanly-ai/page.jsx` | `/api/ui/v1/projections/meanly-ai` |
| `/products/{slug}` | `frontend/app/products/[slug]/page.jsx` | `/api/storefront/v1/catalog/products/{slug}` |
| `/orders/{uuid}/safe` | `frontend/app/orders/[uuid]/safe/page.jsx` | `/api/storefront/v1/orders/{uuid}/safe/*` |

## Identity And Cabinet

| Legacy surface | Next projection | Laravel projection |
| --- | --- | --- |
| `/login` | `frontend/app/login/page.jsx` | Simple L1 connect projection and handoff |
| `/register` | `frontend/app/register/page.jsx` | Simple L1 connect projection and handoff |
| `/vault` | `frontend/app/vault/page.jsx` | `/api/storefront/v1/vault` |
| `/vault/register` | `frontend/app/vault/register/page.jsx` | Simple L1 connect projection and handoff |
| `/cabinet/*` | `frontend/app/cabinet/*` | Storefront Token session and vault APIs |

## Partner And Business

| Legacy surface | Next projection | Laravel projection |
| --- | --- | --- |
| `/business/*` | `frontend/app/business/[[...path]]/page.jsx` | `/api/ui/v1/projections/business/{path}` |
| `/services/*` | `frontend/app/services/[[...path]]/page.jsx` | `/api/ui/v1/projections/services/{path}` |
| `/partner` | `frontend/app/partner/page.jsx` | `/api/ui/v1/projections/partner` |
| `/partner/register` | `frontend/app/partner/register/page.jsx` | `/api/storefront/v1/partner-registration/state` |
| `/legal-entities/register` | `frontend/app/legal-entities/register/page.jsx` | `/api/ui/v1/projections/business/register` |

## Redeem

| Legacy surface | Next projection | Laravel projection |
| --- | --- | --- |
| `/redeem/*` | `frontend/app/redeem/[[...path]]/page.jsx` | `/api/ui/v1/projections/redeem/{path}` and `/api/redeem/*` actions |

## Ops And System

| Legacy surface | Next projection | Laravel projection |
| --- | --- | --- |
| `/ops/*` | `frontend/app/ops/[[...path]]/page.jsx` | `/api/ui/v1/projections/ops/{path}` |
| `/reader` | `frontend/app/reader/page.jsx` | `/api/ui/v1/projections/reader` |
| `/terminal` | `frontend/app/terminal/page.jsx` | `/api/ui/v1/projections/terminal` |
| system/error pages | Next error boundaries and `/api/ui/v1/projections/errors/{code}` | `/api/ui/v1/projections/errors/{code}` |

## Invariants

- User-facing URLs render on the Next host.
- Laravel projection endpoints return DTOs and action contracts, not Blade HTML.
- Causal actions remain POST endpoints guarded and validated by Laravel.
- API-host redirects are compatibility recovery only.
