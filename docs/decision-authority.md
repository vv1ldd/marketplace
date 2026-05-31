# Decision Authority

## Context

Access Plane Registry answers where a user can operate. Decision Authority answers what a user can authorize.

```text
Access Plane Registry
    ↓
Where can I operate?

Decision Authority Registry
    ↓
What can I authorize?

Governance Engine
    ↓
Is this transition allowed right now?
```

## Principle

Access to a surface does not imply authority over every action on that surface.

Examples:

- A user may enter the Decision Console but not approve `ADD_PRODUCT`.
- A partner may approve supply actions but not catalog-model expansion.
- An auditor may enter Tribunal but not approve semantic model changes.
- `APPLY_REBUILD` is blocked by dual-control policy until that flow exists.

## Registry

Decision policies live in `config/decision_authorities.php`.

Each recommendation type can define policies for transitions:

```php
'ADD_ALIAS' => [
    'approve' => ['roles' => ['super_admin']],
    'reject' => ['roles' => ['super_admin']],
],
```

## Governance Engine

`GovernanceEngine::canTransition($user, $recommendation, $targetStatus)` returns:

```text
ALLOW
DENY(reason)
```

Current deny reasons:

- `INVALID_STATE_TRANSITION`
- `UNSUPPORTED_TRANSITION`
- `ROLE_REQUIRED: ...`
- `SOVEREIGN_IDENTITY_REQUIRED`
- `DUAL_CONTROL_PENDING`

The engine does not know about search, supply, demand, or ranking. It evaluates authority over state transitions.

## Guardrail

Decision Console must ask the Governance Engine before changing recommendation state.

Approval still does not mutate SearchProfile, catalog facts, ranking, or supply. It only changes governance state.
