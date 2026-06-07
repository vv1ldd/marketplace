# ADR 0022: Capabilities Are Granted, Identities Are Not Created

## Status

Accepted

## Context

Many commerce systems model business access through account types:

- Customer Account
- Seller Account
- Creator Account
- Admin Account

This couples identity, authentication, onboarding, authorization, and business capability into one concept.

As Meanly evolves, that model creates unnecessary complexity:

- New business functions require new account types.
- Onboarding becomes identity creation.
- Authorization logic leaks into authentication flows.
- UI exposes protocol concepts instead of user actions.

The platform already follows these invariants:

- Read is public.
- Write requires Connect.
- Authentication is not Authorization.
- Marketplace is discovery.
- Vault is ownership.
- Protocols stay invisible.

A consistent authority model is required.

## Decision

Identity is not created as part of business onboarding.

Identity exists independently.

Business access is granted through capabilities and authorities.

The canonical flow is:

```text
Identity
        -> Session
        -> Capabilities
        -> Authority Surfaces
```

Not:

```text
Customer Account
Seller Account
Creator Account
Admin Account
```

Authentication proves possession.

Authorization resolves capabilities.

Capabilities may be granted, revoked, or expanded without changing identity.

## Authority Model

Authorities are outcomes of capability resolution.

Examples:

- `wallet_holder`
- `creator_seller`
- `merchant_node`
- `sovereign_validator`

Authorities must not require separate login systems.

All authorities use the same Connect flow.

## Capability Model

Capabilities express what an authenticated identity may attempt.

Examples:

- `marketplace.buy`
- `marketplace.sell`
- `partner.manage`
- `ops.access`

Roles or authorities may aggregate capabilities:

```text
creator_seller
        -> marketplace.sell

merchant_node
        -> marketplace.sell
        -> partner.manage

sovereign_validator
        -> ops.access
```

The system should add or revoke capabilities without creating a new identity, login type, or account type.

## Product Implications

Users interact with products and actions, not protocols.

User-facing language should describe actions:

- Browse Marketplace
- Continue with Meanly
- Approve in Meanly One
- Open Vault
- Sell with Meanly

Protocol implementation details remain internal.

Simple L1 remains an authority protocol boundary and is not exposed as a primary user-facing destination.

## Marketplace And Vault Boundary

Marketplace is discovery.

Vault is ownership.

```text
Marketplace
        -> what is available

Vault
        -> what is mine
```

Users should not need to search for ownership records in Marketplace.

Users should not need to buy products in Vault.

## Catalog Model

Catalog classification is independent from authority.

`Catalog.product_kind` may include:

- `physical_good`
- `gift_card`
- `digital_good`
- `subscription`
- `voucher`

Adding a new product kind should not create a new authentication flow.

## Ownership Model

Ownership records are independent from catalog classification.

`Vault.ownership_record_kind` may include:

- `receipt`
- `license`
- `entitlement`
- `subscription_right`

Examples:

```text
ebook
        -> digital_good
        -> receipt
        -> license

course
        -> digital_good
        -> receipt
        -> entitlement
```

The ownership model should express what the buyer owns or may access after authority-backed fulfillment.

## Creator Economy

Creator selling is represented as authority, not identity.

Example:

```text
authority
        -> creator_seller

capabilities
        -> marketplace.sell

product_kind
        -> digital_good

ownership_record
        -> receipt
        -> license
        -> access_policy
```

No creator-specific authentication flow is introduced.

No creator account type is introduced.

## Layered Evolution Rule

Business expansion should normally occur above the identity, session, Connect, and protocol layers.

The expected dependency chain is:

```text
Protocol
        -> Identity
        -> Capability
        -> Authority
        -> Domain Objects
        -> Surface
        -> Action
```

Example:

```text
Simple L1
        -> Identity / Session
        -> marketplace.sell
        -> creator_seller
        -> Product
        -> Seller Workspace
        -> Publish Product
```

Legal entity example:

```text
Simple L1
        -> Identity / Session
        -> partner.manage
        -> merchant_node
        -> LegalEntity / CompanyClaim
        -> Partner Workspace
        -> Add Company by INN
```

Upper layers may depend on lower layers.

Lower layers must not depend on upper layers.

For example:

```text
Seller Workspace
        depends on Product and creator_seller

Product
        depends on creator_seller authority

creator_seller
        depends on marketplace.sell

marketplace.sell
        depends on identity/session/protocol
```

But:

```text
Identity
Connect
Session
Simple L1
```

must not depend on `creator_seller`, `studio_owner`, `research_publisher`, `support_agent`, or any other business authority name.

New business directions should usually be expressed through:

- A capability.
- An authority mapping.
- Domain objects.
- A projection surface.
- An action contract.

Domain objects are business objects operated on by authorities. They are not identities and they are not authorities by themselves.

Examples:

- `LegalEntity`
- `CompanyClaim`
- `Product`
- `Order`
- `License`
- `Receipt`

Changes to identity, Connect, session, or protocol require stronger justification because they affect lower, more causal layers.

## Layer Test

The farther downward a change propagates, the stronger the justification required.

```text
Action
        -> low justification

Surface
        -> low justification

Domain Objects
        -> moderate justification

Authority
        -> moderate justification

Capability
        -> moderate justification

Identity
        -> high justification

Connect
        -> high justification

Protocol
        -> exceptional justification
```

Expected stability:

```text
Action
        changes frequently

Surface
        changes regularly

Domain Objects
        change regularly

Authority
        changes occasionally

Capability
        changes carefully

Identity
        changes rarely

Connect
        changes very rarely

Protocol
        changes exceptionally
```

Change risk is not determined by line count alone.

Change risk is primarily determined by causal proximity to authority, session, identity, and protocol boundaries.

Small protocol changes may be riskier than large workspace changes.

## Governance Gate

Need a new business feature?

Add a capability.

Need a new authority surface?

Add an authority mapping.

Need a new catalog type?

Add a `product_kind`.

Need a new ownership model?

Add an `ownership_record_kind`.

Need a new login type, identity type, or authentication flow?

Architectural review is required.

The short review question is:

```text
Is this feature expanding the product,
or is it changing the foundation?
```

Product Growth:

- Capability
- Authority
- Domain Objects
- Surface
- Action

Foundation Change:

- Identity
- Session
- Connect
- Protocol

Product Growth is expected.

Foundation Change must explain why.

This is not a prohibition. Foundation changes may be necessary for new proof protocols, new authority resolution models, session boundary changes, or Connect mechanism replacement. They must be explicit architectural events rather than incidental consequences of product work.

RFCs and large PRs should include a Layer Impact Assessment when they introduce new authorities, surfaces, authentication paths, session behavior, or protocol behavior.

Expected product growth example:

```text
Layer Impact Assessment

Touched:
  - Capability
  - Authority
  - Domain Objects
  - Surface
  - Action

Untouched:
  - Identity
  - Session
  - Connect
  - Protocol

Assessment:
  Product Growth
```

Foundation change example:

```text
Layer Impact Assessment

Touched:
  - Identity
  - Connect

Reason:
  ...

Assessment:
  Foundation Change
  Architectural review required
```

## Consequences

Adding new business capabilities should normally require:

- New capability definitions.
- New authority mappings.
- New domain objects or domain relationships.
- New catalog kinds.
- New ownership record kinds.

Adding a new login type, identity type, or authentication flow requires explicit architectural justification.

UI should avoid copy such as:

- Register Creator
- Create Seller Account
- Create Identity

Preferred language should describe the user's action:

- Sell with Meanly
- Continue with Meanly
- Open Creator Workspace
- Open Partner Workspace
- Open Ops

## Invariant

```text
Capabilities are granted.
Identities are not created.
```

## Relationship To Previous ADRs

This ADR extends:

- ADR 0017: Storefront Projection Contract
- ADR 0018: Authority Action Contracts
- ADR 0019: Capability Contract Semantics
- ADR 0020: Replay-Verifiable Authority Decisions
- ADR 0021: Consumer Brand And Kernel Language

The responsibility ladder is:

```text
Brand
        -> Presentation
        -> Projection
        -> Capability
        -> Authority
        -> Ownership Record
        -> Ledger / Replay
```

The core separation is:

```text
identity
  != session
  != capability
  != authority
  != ownership
```
