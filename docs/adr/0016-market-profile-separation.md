# ADR 0016: Market Profile Separation

## Status

Accepted

## Context

The marketplace is evolving into a multi-market storefront platform.

Different markets may require different:

- Languages
- Currencies
- Homepage composition
- Support channels
- Legal presentation
- Visual themes
- Navigation structures

However, introducing market-specific behavior must not change the authority model, trust model, checkout semantics, ledger semantics, or verification semantics.

The marketplace remains a single causal system with shared authority and shared verification semantics.

## Decision

A Market Profile is a presentation policy, not an authority policy.

Market Profiles may influence rendering, localization, presentation, navigation, and user-facing composition.

Market Profiles must not influence:

- Trust thresholds
- Checkout eligibility
- Proof requirements
- Ledger write semantics
- Fulfillment semantics
- Identity binding semantics
- Authority decisions
- Verification outcomes

## Shared Layers

The following layers are globally shared across all markets:

- Authority Layer
- Ledger Layer
- Simple L1 Trust Kernel
- Checkout Semantics
- Verification Semantics
- Fulfillment Semantics
- Identity Semantics

These layers define marketplace causality and correctness.

Their behavior must remain invariant across markets.

## Market-Specific Layers

The following layers may vary by market:

- Locale
- Currency Display
- Theme
- Copy
- Navigation
- Homepage Composition
- Category Priority
- Support Channels
- Legal Presentation

These layers define presentation only.

They do not define authority.

## Market Profile DTO

Market-specific presentation is exposed through a Market Profile DTO.

Example:

```json
{
  "market": {
    "key": "ru",
    "locale": "ru",
    "display_currency": "RUB",
    "theme": "ru-retail",
    "presentation_profile": {
      "navigation": ["catalog", "vault", "support"],
      "homepage_blocks": ["categories", "featured", "safe"],
      "support_channels": ["telegram", "email"]
    }
  },
  "authority": {
    "kernel": "marketplace-commerce",
    "ledger_semantics": "shared",
    "trust_kernel": "simple-l1"
  }
}
```

## Boundary Rule

`presentation_profile` affects rendering.

`authority` affects allowed actions.

Frontend may read both.

Frontend may use `presentation_profile` for:

- Layout
- Copy
- Localization
- Navigation
- Themes

Frontend must not derive authority from `presentation_profile`.

Allowed actions must continue to be derived from authority-backed action contracts.

## Deployment Model

Shared:

- Repository
- Frontend application
- API contract
- Authority semantics
- Ledger semantics

Per-market:

- Domain
- Deployment
- CDN
- Cache topology
- Locale
- Legal presentation
- Support channels
- Market profile

## Architectural Invariant

Many storefronts.

One authority model.

Many presentations.

One causal marketplace.

The core boundary is:

```text
presentation_profile -> rendering        OK
authority -> allowed actions             OK
presentation_profile -> authority        forbidden
presentation_profile -> trust decisions  forbidden
presentation_profile -> ledger semantics forbidden
```

If this invariant is preserved, the platform can launch many markets such as `ru`, `ge`, `eu`, `ar`, `tr`, and `br` without creating many independent marketplace systems. It remains one causal marketplace system with many presentations.
