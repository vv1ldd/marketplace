# Access Planes

## Context

Meanly has multiple authority surfaces: storefront, vault, partner console, ops, tribunal, and decision console. These are not just pages. They are planes of authority unlocked by identity, role, and operational state.

```text
Identity
    ↓
Access Plane Registry
    ↓
Authority Evaluation
    ↓
Navigation Surface
```

## Principle

Access Plane determines what actions a user is allowed to authorize.

MarketContext determines how requests are interpreted.
SearchProfile determines what the system knows.
Decision layer determines what can change.

## Registry

Planes are declared in `config/access_planes.php`.

Each plane can define:

- `label`
- `route`
- `route_params`
- `authority`
- `description`
- `requires_auth`
- `required_roles`
- `requires_sovereign_identity`
- `required_legal_entity`

## Current Planes

- `storefront`: public browsing and purchase surface
- `vault`: authenticated personal identity/assets surface
- `partner`: B2B partner/seller operations
- `ops`: global operator surface
- `tribunal`: sovereign ledger audit surface
- `decision_console`: governance authority surface

## Switcher

The console switcher is rendered from `AccessPlaneRegistry`, not hardcoded links.

Each plane is evaluated as:

```text
available | locked(reason)
```

This makes the switcher a visualization of current user authority, not a navigation widget.
