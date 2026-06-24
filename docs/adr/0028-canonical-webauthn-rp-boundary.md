# ADR 0028: Canonical WebAuthn RP Boundary

**Scope:** This ADR fixes the WebAuthn relying party boundary for Meanly and
Simple Layer One deployments. It separates the issuer host that issues SL1
proofs from the authentication authority that owns passkey ceremonies.

## Status

Accepted - **2026-06**

## Decision

Meanly production uses `identity.meanly.one` as the canonical WebAuthn RP ID.

```text
Production:
  RP ID           = identity.meanly.one
  Ceremony Origin = connect.identity.meanly.one
  Issuer Host     = pass.meanly.one

Protocol / Lab:
  RP ID           = identity.simplelayer.one
  Ceremony Origin = connect.identity.simplelayer.one
  Issuer Host     = pass.simplelayer.one

RU:
  RP ID           = identity.meanly.ru
  Ceremony Origin = connect.identity.meanly.ru
  Issuer Host     = pass.meanly.ru
```

Principle:

```text
Issuer Host != RP ID

pass.*     owns issuance.
identity.* owns authentication.
```

## Normative invariants

Only the following are contract. They are technology-neutral and must not change
after user onboarding:

```text
identity.*          = authentication authority / RP boundary
connect.identity.*  = ceremony origin
pass.*              = issuer endpoint

Issuer Host != RP ID
```

Everything else in this document is **current implementation or non-normative
example**, not contract. In particular the following are NOT invariants and may
change without revising this ADR:

- WebAuthn / passkeys as the credential technology
- the issuer proof-token format
- how credentials are stored (ledger schema, fields)
- any specific credential, alias, or account (for example `@vv1lddev`)

## Context

SL1 Connect has three distinct roles:

```text
Application
    |
    v
Issuer Host
    |
    v
Ceremony Origin
    |
    v
WebAuthn RP ID
```

For Meanly this becomes:

```text
Application
    |
    v
pass.meanly.one
    |
    v
connect.identity.meanly.one
    |
    v
RP ID = identity.meanly.one
```

Applications such as `app.meanly.one`, `merchant.meanly.one`,
`vault.meanly.one`, `credit.meanly.one`, and `ops.meanly.one` do not conduct
their own WebAuthn ceremony. They delegate authentication to the identity
authority and consume the issuer-returned authentication proof (today an SL1
proof token; the proof format is implementation detail, not contract).

This matches the responsibility layout:

```text
meanly.one
+-- identity  authentication authority
+-- pass      issuer / ceremony entrypoint
+-- app       product UX
+-- ops       operations
+-- sl1       protocol endpoints
```

`simplelayer.one` remains the protocol and reference infrastructure surface.
`meanly.one` is the first commercial network / issuer built on SL1.

## Rationale

Passkeys are bound to the RP ID. Moving a passkey between RP IDs is not a
transparent migration; users must register new credentials or go through a
recovery / rotation flow.

Using `identity.meanly.one` says:

> Only the identity authority owns authentication.

Using the apex `meanly.one` would say:

> Any current or future `*.meanly.one` service is potentially in the same
> authentication trust space.

That is broader than the architecture needs. The Connect model centralizes
WebAuthn at the identity authority; product and operations surfaces should not
be able to initiate passkey ceremonies merely because they live under the same
apex domain.

The existing production credential for `@vv1lddev` is already registered with
`rp_id = identity.meanly.one`, so this decision also preserves the earliest
real credential without re-registration.

This ADR does not standardize WebAuthn itself. It standardizes the
authentication trust boundary:

```text
Authentication Authority
    |
    v
Ceremony Origin
    |
    v
Issuer
```

WebAuthn / passkeys are the first implementation below that boundary. If a
future credential technology replaces WebAuthn, the internal authentication
authority may change, but the external trust topology should remain stable:
`identity.*`, `connect.identity.*`, and `pass.*`.

The decision should survive three changes without revision:

1. Rewriting vaults, credit, settlement, supply, or ledger internals.
2. Onboarding a new issuer operator that follows the same topology, for example
   `identity.bank-x.com`, `connect.identity.bank-x.com`, and `pass.bank-x.com`.
3. Replacing the authentication technology below the identity authority.

## Consequences

New production registrations MUST use:

```text
connect.identity.meanly.one -> RP ID identity.meanly.one
```

`pass.meanly.one` MAY issue authentication proofs, host issuer routes, and
coordinate authorization requests, but it MUST NOT become the RP ID. (Proof
format and issuer route shape are implementation detail.)

Future services under `*.meanly.one` MUST delegate authentication to the
identity authority instead of creating their own passkey RP boundary.

Protocol / lab deployments under `simplelayer.one` MUST remain separate from
Meanly production identities. Credentials registered for
`identity.simplelayer.one` are not production Meanly credentials.

RU production identities MUST use a separate RP ID under the RU identity
authority (`identity.meanly.ru` unless another RU canonical domain is accepted
before launch). RU credentials are therefore intentionally separate from global
Meanly credentials.

## Rollout phases

`pass.simplelayer.one` is a protocol / lab issuer, not the long-term Meanly
production issuer. Moving to `pass.meanly.one` is therefore not just a DNS
launch; it is the transition from architectural intent to system fact.

### Phase 1: Infrastructure freeze

Bring up:

```text
pass.meanly.one
connect.identity.meanly.one
```

Verify that registration, authentication, and proof issuance run through the
production namespace and that newly registered credentials receive:

```text
rp_id = identity.meanly.one
```

Supply-plane services may still be unstable during this phase. The important
constraint is that production credentials are created under the canonical RP ID.

### Phase 2: Registration cutover

After the production path is verified, new production registrations MUST route
through:

```text
connect.identity.meanly.one
```

Lab remains available for protocol development:

```text
pass.simplelayer.one
connect.identity.simplelayer.one
identity.simplelayer.one
```

But lab MUST NOT be the place where production identities are created.

### Phase 3: Namespace freeze

After the first external users register, treat these names as frozen external
contract:

```text
identity.meanly.one
connect.identity.meanly.one
pass.meanly.one
```

Further iteration should happen below the identity layer: supply plane, vaults,
rails, settlement, credit, and accounting.

Supply-plane hosts and commerce services can change repeatedly. The WebAuthn RP
ID should be treated as expensive to migrate after user onboarding.
