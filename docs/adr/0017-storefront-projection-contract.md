# ADR 0017: Storefront Projection Contract

## Status

Accepted

## Context

The storefront layer is moving from Laravel Blade UI surfaces to Next projections.

At the same time, the platform is becoming a multi-market storefront system. Different markets may have different presentation profiles, but all markets must remain projections of the same causal marketplace.

Without a strict projection contract, frontend code can gradually begin reconstructing authority, eligibility, checkout, identity, or trust decisions locally. That would fragment the marketplace into many inconsistent products and weaken the authority and ledger boundaries.

## Decision

The storefront frontend consumes projections.

The storefront frontend never derives authority.

The storefront frontend never reconstructs causality.

The storefront frontend only renders authority-backed contracts.

## Layer Model

### Level 1: Causal Core

The causal core defines correctness and must remain market-invariant:

- Simple L1
- Authority
- Ledger
- Verification
- Checkout Semantics
- Identity Semantics

### Level 2: Projection Contract

The projection contract exposes authority-backed read models and transition contracts:

- Storefront DTOs
- Action Contracts
- Market Profile DTO

### Level 3: Presentation

The presentation layer renders market-specific UI:

- Theme
- Locale
- Navigation
- Homepage Blocks
- Support Channels
- Legal Copy

## Boundary Rule

Frontend may render:

- DTO fields
- Action contracts
- Blocking reasons
- Next actions
- Market presentation profiles

Frontend must not derive:

- Checkout eligibility
- Trust thresholds
- Verification outcomes
- Identity binding
- Fulfillment eligibility
- Ledger semantics
- Authority decisions

Allowed actions must be supplied by authority-backed action contracts.

If a UI action is not present in the DTO action contract, the frontend must not invent it.

## Practical PR Test

Every storefront or market-profile PR should answer:

```text
Can this change alter authority outcomes?
```

If the answer is yes, the change is not a presentation or Market Profile change.

If the answer is no, the change may be a presentation or Market Profile change.

Examples:

| Change | Market/Profile Presentation Change |
| --- | --- |
| New CTA color | Yes |
| Different category order | Yes |
| WhatsApp instead of Telegram support | Yes |
| New homepage block | Yes |
| Display currency formatting | Yes |
| New eligibility rule | No |
| Trust threshold change | No |
| Checkout approval change | No |
| Identity binding change | No |
| Ledger semantics change | No |

## Consequences

New countries, domains, and brands should be added through presentation profiles and deployment configuration, not by forking causality.

The architectural chain is:

```text
Many Storefronts
        -> One Projection Contract
        -> One Authority Model
        -> One Ledger Semantics
```

This keeps the marketplace extensible across many markets while preserving a single trust, authority, and ledger model.
