# ADR 0019: Capability Contract Semantics

## Status

Accepted

## Context

ADR 0018 separates Projection DTOs from Action Contracts.

Projection DTOs expose observations. Action Contracts expose authority-granted capabilities.

The next boundary is the semantics of a capability itself. A capability cannot be just a frontend boolean such as `allowed: true`. It must represent a bounded authority grant that can be validated by the backend and, when executed, recorded through the marketplace causal path.

Without capability semantics, UI code can treat display fields as permissions, reuse stale capabilities, submit actions outside their scope, or make actions difficult to replay and audit.

## Decision

A capability is an authority-issued, scoped, time-bounded permission to attempt a specific transition.

Capabilities are created by the authority layer.

Capabilities are consumed by the frontend.

Capabilities are validated by the backend at execution time.

Capabilities are not created by the frontend.

## Capability Fields

A capability should include:

- `action`: the transition name
- `allowed`: whether the capability is currently executable
- `scope`: the bounded resource or aggregate scope
- `method`: the HTTP method
- `endpoint`: the backend endpoint to call
- `contract_id`: a stable identifier for the authority-issued contract
- `issued_at`: when the capability was issued
- `expires_at`: when the capability expires
- `blocking_reason`: why the capability is not executable, when blocked
- `replay_key`: optional key for replay verification, audit, or idempotency

Example:

```json
{
  "action": "checkout",
  "allowed": true,
  "scope": "product:123",
  "method": "POST",
  "endpoint": "/api/storefront/v1/checkout/create",
  "contract_id": "cap_checkout_01HX7Y3M8E4N",
  "issued_at": "2026-06-04T15:20:00Z",
  "expires_at": "2026-06-04T15:30:00Z",
  "blocking_reason": null,
  "replay_key": "checkout:product:123:01HX7Y3M8E4N"
}
```

Blocked example:

```json
{
  "action": "checkout",
  "allowed": false,
  "scope": "product:123",
  "method": "POST",
  "endpoint": "/api/storefront/v1/checkout/create",
  "contract_id": "cap_checkout_01HX7Y3M8E4P",
  "issued_at": "2026-06-04T15:20:00Z",
  "expires_at": "2026-06-04T15:30:00Z",
  "blocking_reason": "no_selected_offer",
  "replay_key": null
}
```

## Semantics

### Authority-Issued

Only the backend authority layer may issue capabilities.

Frontend may display a capability and submit to its endpoint, but may not create, extend, reinterpret, or upgrade it.

### Scoped

A capability applies only to its declared `scope`.

Examples:

- `product:123`
- `order:uuid`
- `vault:item:uuid`
- `partner-registration:entity`
- `redeem:intent:uuid`

Frontend must not reuse a capability outside its scope.

### Time-Bounded

A capability has a lifetime.

Expired capabilities must be re-projected from the backend.

Frontend must not extend capability lifetime locally.

### Replay-Verifiable

Capabilities should be traceable through `contract_id` and, when appropriate, `replay_key`.

Execution endpoints should be able to validate that the requested action matches the issued contract, scope, and current authority state.

### Execution-Time Validation

A capability is permission to attempt a transition, not proof that execution will succeed.

The backend must still validate the transition at execution time.

Authority may reject execution if state changed after projection.

## Boundary Rule

The frontend may:

- Render capabilities
- Hide or disable blocked capabilities
- Submit a user request to a declared capability endpoint
- Include `contract_id` or `replay_key` when required
- Refresh projections when capabilities expire

The frontend must not:

- Mint capabilities
- Infer capabilities from projection fields
- Reuse a capability for another scope
- Extend capability lifetime
- Convert blocked capabilities into allowed actions
- Treat `allowed: true` as a guarantee of successful execution
- Write ledger facts

## Relationship To Previous ADRs

This ADR extends:

- ADR 0016: Market Profile Separation
- ADR 0017: Storefront Projection Contract
- ADR 0018: Authority Action Contracts

The complete separation is:

```text
presentation
  != information
  != capability
  != authority
  != causality
```

The architectural chain is:

```text
Market Profile
        -> Projection DTO
        -> Action Contract
        -> Capability Contract
        -> Authority Core
        -> Ledger
```

## Consequences

The storefront can render rich market-specific interfaces without becoming an authority surface.

Capabilities remain backend-issued and auditable.

Stale projections can be refreshed instead of trusted indefinitely.

Execution remains safe because backend authority validates each attempted transition before any ledger-relevant effect.
