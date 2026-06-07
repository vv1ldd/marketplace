# ADR 0020: Replay-Verifiable Authority Decisions

## Status

Accepted

## Context

ADR 0019 defines a capability as an authority-issued, scoped, time-bounded permission to attempt a transition.

A capability is not a transition.

A permission is not an outcome.

Authorization is not a state change.

After a capability is issued, the user may submit a request. The authority layer must then re-evaluate the request against current authority state. The result is an authority decision.

The system needs authority decisions to be replay-verifiable so that outcomes can be audited as deterministic evaluations of capability, request, and authority state.

## Decision

An Authority Decision is a deterministic evaluation of:

- A capability
- A request
- The authority state at decision time
- The applicable authority rules

Every authority outcome that can affect marketplace causality must be replay-verifiable.

Authority decisions are not treated as final truth by themselves. They are causal artifacts whose correctness can be verified by replay.

## Definitions

### Capability

A capability is an authority-issued permission to attempt a transition.

It may include:

- `contract_id`
- `replay_key`
- `scope`
- `issued_at`
- `expires_at`
- `action`
- `endpoint`

### Authority Decision

An Authority Decision is the backend evaluation result for an attempted transition.

It should include:

- `decision_id`
- `contract_id`
- `replay_key`
- `scope`
- `action`
- `outcome`
- `blocking_reason`
- `authority_state_hash`
- `authority_ruleset_version`
- `request_hash`
- `decided_at`
- `causal_event_id`

Example:

```json
{
  "decision_id": "dec_01HX7Z9NF8Q2",
  "contract_id": "cap_checkout_01HX7Y3M8E4N",
  "replay_key": "checkout:product:123:01HX7Y3M8E4N",
  "scope": "product:123",
  "action": "checkout",
  "outcome": "accepted",
  "blocking_reason": null,
  "authority_state_hash": "sha256:...",
  "authority_ruleset_version": "checkout-v1",
  "request_hash": "sha256:...",
  "decided_at": "2026-06-04T15:40:00Z",
  "causal_event_id": "evt_01HX7Z9P3S4V"
}
```

Rejected example:

```json
{
  "decision_id": "dec_01HX7Z9NF8Q3",
  "contract_id": "cap_checkout_01HX7Y3M8E4N",
  "replay_key": "checkout:product:123:01HX7Y3M8E4N",
  "scope": "product:123",
  "action": "checkout",
  "outcome": "rejected",
  "blocking_reason": "offer_no_longer_available",
  "authority_state_hash": "sha256:...",
  "authority_ruleset_version": "checkout-v1",
  "request_hash": "sha256:...",
  "decided_at": "2026-06-04T15:41:00Z",
  "causal_event_id": null
}
```

## Replay Rule

An authority decision should be reproducible as:

```text
decision == replay(
  authority_state,
  capability,
  request,
  authority_ruleset_version
)
```

If replay cannot reproduce the decision, the system must treat the decision as suspect and investigate the authority state, ruleset version, request capture, or replay implementation.

## Boundary Rule

Frontend may:

- Submit requests using authority-issued capabilities
- Render authority decisions returned by the backend
- Render blocking reasons
- Refresh projections after rejected or expired capabilities

Frontend must not:

- Produce authority decisions
- Compute authority outcomes
- Write ledger events
- Treat capability issuance as transition success
- Treat accepted UI state as proof of a ledger event

Backend authority must:

- Re-evaluate capabilities at execution time
- Attach decisions to stable identifiers
- Capture enough state for replay
- Record causal events only after accepted transitions
- Preserve rejected decisions when needed for audit, fraud analysis, or support

## Capability, Decision, And Ledger

The chain is:

```text
Capability issued
        -> User submits request
        -> Authority re-evaluates
        -> Authority Decision accepted or rejected
        -> Accepted decision emits Ledger Event
        -> Replay verifies decision and event
```

Capability does not guarantee transition success.

Decision does not become truth by assertion.

Ledger event records the causal effect of accepted authority decisions.

Replay verifies that the decision and resulting event are consistent with captured authority state and rules.

## Relationship To Previous ADRs

This ADR extends:

- ADR 0016: Market Profile Separation
- ADR 0017: Storefront Projection Contract
- ADR 0018: Authority Action Contracts
- ADR 0019: Capability Contract Semantics

The responsibility ladder is:

```text
Presentation
        -> Projection
        -> Capability
        -> Authority Decision
        -> Ledger Event
        -> Replay Verification
```

The conceptual separation is:

```text
presentation
  != information
  != capability
  != decision
  != causality
```

## Consequences

Authority outcomes become auditable artifacts instead of opaque responses.

Valid capabilities remain safe because authority still re-evaluates state at execution time.

Rejected transitions can be explained and investigated without pretending they changed marketplace causality.

The marketplace stores causality, while truth is treated as the verifiable result of replaying causal artifacts under captured authority state and rules.
