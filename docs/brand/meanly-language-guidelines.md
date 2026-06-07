# Meanly Language Guidelines

## Purpose

Meanly is the consumer-facing product layer.

Simple L1 is the trust and identity kernel.

Consumer-facing copy should use Meanly terminology.

Protocol, API, and architecture documentation should use Simple L1 terminology.

## Approved Consumer Language

### Identity

- Continue with Meanly
- Sign in with Meanly
- Meanly ID
- Open Meanly App
- Get Meanly

### Commerce

- Pay with Meanly
- Meanly Receipt
- Meanly Vault
- Open Vault
- View Purchases

### Support

- Contact Support
- Need Help?
- Recover Access

## Discouraged Consumer Language

Avoid exposing protocol concepts directly in primary buyer-facing UI:

- Simple L1 Proof
- Proof Token
- Storefront Token
- Capability Contract
- Ledger Anchor
- Authority Evaluation

These terms belong to developer, protocol, support, or advanced/debug tooling surfaces.

## Discouraged Brand Usage

Avoid constructions where "Meanly" is likely to be interpreted as an English adverb:

- Pay Meanly
- Shop Meanly
- Live Meanly

Prefer:

- Pay with Meanly
- Continue with Meanly
- Open Meanly Vault

## Product Risk Boundary

Brand language may evolve.

Trust, authority, identity, and ledger semantics must remain independent of branding decisions.

The product hierarchy is:

```text
Brand
        -> Presentation
        -> Projection
        -> Capability
        -> Authority
        -> Ledger
```

Changing brand language may alter the top layer.

It must not alter projection contracts, capability semantics, authority decisions, identity binding, or ledger semantics.
