# ADR 0021: Consumer Brand And Kernel Language

## Status

Accepted

## Context

The marketplace has a layered architecture:

```text
Consumer Brand
        -> Meanly
Identity / Trust Kernel
        -> Simple L1
Authority / Ledger
        -> Marketplace Core
```

Buyer-facing screens should help users complete goals such as signing in, paying, accessing purchases, recovering accounts, and opening safe codes.

They should not require buyers to understand protocol concepts such as proof tokens, replay keys, authority contracts, ledger anchors, trust kernels, or Storefront Tokens.

## Decision

Consumer-facing concepts must map to user goals.

Kernel-facing concepts must map to system semantics.

The primary buyer-facing brand is Meanly.

Simple L1 remains the identity and trust kernel, but it should not be the primary buyer-facing product language.

## Language Mapping

| System term | User term |
| --- | --- |
| Simple L1 proof | Sign-in approval |
| Storefront Token | Session |
| Vault handoff | Open Vault |
| Identity binding | Meanly ID |
| Ledger-backed receipt | Receipt |
| Safe unlock capability | Open Safe |
| Simple L1 app approval | Approve in Meanly |

## Product Boundary

Buyer-facing UI should use terms such as:

- Sign in with Meanly
- Pay with Meanly
- Meanly Vault
- Meanly Safe
- Meanly Receipt
- Meanly ID

Kernel/API/debug UI may use terms such as:

- Simple L1
- Proof token
- Storefront Token
- Handoff
- Capability
- Ledger anchor
- Replay key

## Practical Product Test

Every user-facing concept should answer:

```text
Would a buyer need this concept to complete their goal?
```

If no, the concept should stay in kernel, API, support, docs, or advanced/debug UI.

## Vault Flow

The primary Vault flow is:

```text
Continue with Meanly
        -> Approve in app
        -> Vault opens
```

Advanced protocol controls may exist behind a collapsed advanced section:

- Developer login
- Proof Token exchange
- Storefront Token input
- Debug session projection

## Relationship To Previous ADRs

This ADR extends the projection and capability boundary from:

- ADR 0016: Market Profile Separation
- ADR 0017: Storefront Projection Contract
- ADR 0018: Authority Action Contracts
- ADR 0019: Capability Contract Semantics
- ADR 0020: Replay-Verifiable Authority Decisions

The full presentation chain is:

```text
Brand
        -> Presentation
        -> Projection
        -> Capability
        -> Authority
        -> Ledger
```

Brand and presentation can simplify language, but they must not alter projection contracts, capabilities, authority decisions, or ledger semantics.
