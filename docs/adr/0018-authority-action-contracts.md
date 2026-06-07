# ADR 0018: Authority Action Contracts

## Status

Accepted

## Context

The storefront consumes projection DTOs from Laravel and renders market-specific UI in Next.

Projection DTOs expose information. They are not permissions.

If frontend code derives permissions from projected information, it can accidentally reconstruct authority or causality in the presentation layer. For example, a product with stock, a verified identity, or a favorable risk score does not by itself grant checkout capability unless the authority layer explicitly grants that capability.

The system needs a clear boundary between projected information and authority-granted capabilities.

## Decision

Projection DTOs describe state.

Action Contracts describe authority-granted capabilities.

Frontend may render capabilities.

Frontend must not create permissions.

Frontend must not infer action eligibility from presentation fields, projected state, market profile, theme, locale, price, stock, identity display fields, or risk-like values.

## Definitions

### Projection DTO

A Projection DTO is information exposed for rendering.

Examples:

- Product name
- Display price
- Category
- Region
- Seller display name
- Market profile
- Blocking reason
- Identity display state
- Order status label

Projection DTOs are read models.

They do not grant authority.

### Action Contract

An Action Contract is an authority-backed capability exposed to the frontend.

Examples:

- View product
- Start checkout
- Create checkout intent
- Open vault item
- Scratch safe item
- Submit partner registration
- Send redeem verification email
- Activate redeem code

Action Contracts define what the frontend may render as an executable transition.

## Contract Shape

Action Contracts should expose allowed actions, blocked actions, next action, and blocking reason.

Example:

```json
{
  "actions": {
    "allowed_actions": ["VIEW", "CHECKOUT"],
    "blocked_actions": [],
    "next_action": "CHECKOUT",
    "blocking_reason": null,
    "capabilities": {
      "checkout": {
        "allowed": true,
        "method": "POST",
        "endpoint": "/api/storefront/v1/checkout/create"
      }
    }
  }
}
```

A blocked action should also be explicit:

```json
{
  "actions": {
    "allowed_actions": ["VIEW"],
    "blocked_actions": ["CHECKOUT"],
    "next_action": "VIEW_PROVIDER_NETWORK",
    "blocking_reason": "no_selected_offer",
    "capabilities": {
      "checkout": {
        "allowed": false,
        "method": "POST",
        "endpoint": "/api/storefront/v1/checkout/create",
        "blocking_reason": "no_selected_offer"
      }
    }
  }
}
```

## Boundary Rule

Frontend may:

- Render allowed actions
- Render blocked actions
- Render blocking reasons
- Render next action
- Submit to declared action endpoints

Frontend must not:

- Invent actions absent from the Action Contract
- Enable actions by inspecting Projection DTO fields
- Treat market profile values as permissions
- Treat stock, price, identity, or risk display values as permissions
- Decide checkout eligibility in React
- Decide verification outcomes in React
- Decide trust thresholds in React
- Decide ledger writes in React

## Review Tests

Every storefront action change should answer:

```text
Is this action explicitly granted by an authority-backed Action Contract?
```

If no, the frontend must not render it as executable.

Every projection change should answer:

```text
Can this projected field become a permission in frontend code?
```

If yes, the change must be redesigned so the permission is represented by an Action Contract instead.

## Relationship To Previous ADRs

This ADR extends:

- ADR 0016: Market Profile Separation
- ADR 0017: Storefront Projection Contract

The architectural chain is:

```text
Market Profile
        -> Presentation Policy
Storefront Projection
        -> Rendered Read Model
Action Contract
        -> Authority-Granted Capability
Authority Layer
        -> Validated Transition
Ledger Layer
        -> Durable Causal Record
```

The direction of dependency is one-way:

```text
Ledger -> Authority -> Action Contract -> Projection DTO -> Storefront UI
```

The reverse direction is forbidden:

```text
Storefront UI -> Authority        forbidden
Projection DTO -> Ledger          forbidden
Market Profile -> Authority       forbidden
Theme -> Eligibility              forbidden
```

## Consequences

The frontend becomes safer and simpler: it renders what the authority grants.

New markets can change presentation without changing capabilities.

New capabilities must be added through backend authority contracts, not through frontend inference.

The marketplace remains one causal system with many storefront presentations.
