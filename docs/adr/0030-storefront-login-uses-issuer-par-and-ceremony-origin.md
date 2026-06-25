# ADR 0030: Storefront Login Uses Issuer PAR and Ceremony Origin

**Scope:** This ADR aligns the **storefront** login flow (`meanly.one`,
`meanly.ru`, and the Maestrooo proxy) with the identity boundary fixed in
[ADR 0028](0028-canonical-webauthn-rp-boundary.md). It records the decision to
route storefront sign-in through the issuer's Pushed Authorization Request (PAR)
and the canonical ceremony origin, the same way the Coolify (`ops.*`) client
already does.

## Status

Proposed - **2026-06** (accepted in direction; implementation deferred to a
separate change-set)

## Context

Two relying-party flows currently differ in shape:

```text
ops.meanly.one (Coolify, separate codebase)
    -> server-side PAR to pass.meanly.one/api/sl1e/authorize/requests
    -> short link  pass.meanly.one/r/sl1rq_…
    -> issuer→ceremony delegation
    -> connect.identity.meanly.one/r/…      (ceremony on the ceremony origin)

meanly.one / meanly.ru (marketplace storefront)
    -> SimpleL1ConnectController::connect builds a full authorize URL inline
    -> frontend rewrites it to a LOCAL /authorize?<all params>
    -> ceremony renders in-page on meanly.ru   (long URL, not on connect.identity.*)
```

Mechanically:

- `SimpleL1ProtocolClient::authorizationUrlForHost(...)` returns a complete
  authorize URL with every query parameter inline (no PAR).
- `authorizePathFromRedirect()` in the frontend strips the host and forces a
  local `/authorize?…` route, so the ceremony never leaves the storefront host.

Result: the storefront produces a long URL and runs the ceremony on the
storefront origin instead of `connect.identity.*`. This is functional but is not
the same contract surface as `ops.*`, and it is weaker alignment with ADR 0028.

## Decision

The storefront login flow will use the issuer's PAR endpoint and the canonical
ceremony origin, identical in shape to the Coolify client:

```text
storefront login
    -> PAR to pass.<contour>/api/sl1e/authorize/requests
    -> short link  pass.<contour>/r/sl1rq_…
    -> connect.identity.<contour>/r/…
```

This keeps the invariants of ADR 0028 intact and makes every relying party use
the same boundary:

```text
identity.*          = authentication authority / RP boundary
connect.identity.*  = ceremony origin
pass.*              = issuer endpoint
```

## Consequences

### 1. The issuer owns the authorization request

Today part of the request state is effectively authored by the client (the
storefront assembles the authorize URL). After this ADR the request exists as an
**object owned by the issuer**, not as a URL composed by the storefront:

```text
client_id + secret
      ↓ PAR
sl1rq_xxx
      ↓
issuer-owned request state
```

The short `sl1rq_…` reference is a handle to issuer state, not a serialization
of client-supplied parameters.

### 2. The storefront special case disappears

```text
before:  ops → PAR,  storefront → inline authorize
after:   ops → PAR,  storefront → PAR,  maestrooo → PAR
```

One authorization model means one threat model, one trace, and one set of logs.
There is no longer a "storefront-shaped" exception to reason about.

### 3. It fits ADR 0028 cleanly

If the ceremony must live on a dedicated origin (`connect.identity.<contour>`),
then every client should reach it the same way. Otherwise the system keeps an
exception (`ops → ceremony origin`, `storefront → almost ceremony origin`), which
is exactly the divergence this ADR removes.

This ADR is therefore **not new functionality** — it is the removal of an
architectural divergence between storefront and ops. The system gets simpler
because it has fewer exceptions.

## Required work (separate change-set)

1. **Register storefront clients** (`meanly.one`, `meanly.ru`, Maestrooo) in the
   issuer `SL1E_CLIENT_REGISTRY_JSON` with a `client_secret`. PAR requires client
   authentication; this is what already enabled short links for `meanly.ops`.
2. **Use PAR in the backend.** `SimpleL1ProtocolClient::authorizationUrlForHost`
   (and the deep-link variant) push the request to the issuer and return the
   short `pass.<contour>/r/sl1rq_…` reference instead of an inline authorize URL.
3. **Stop forcing a local ceremony.** The frontend redirects the browser to the
   short issuer link rather than rewriting it to a local `/authorize?…`, so the
   ceremony runs on `connect.identity.<contour>`.
4. **Verify per contour** (`meanly.one`, `meanly.ru`) that the redirect chain and
   `rp_id` match ADR 0028, exactly as verified for `ops.*`.

## Pre-cutover verification

Before flipping the flag on any contour, walk the registration matrix:

| Contour      | client_id | redirect_uri | rp_id | PAR        |
|--------------|-----------|--------------|-------|------------|
| meanly.one   | ✓         | ✓            | ✓     | ✓          |
| meanly.ru    | ✓         | ✓            | ✓     | ✓          |
| maestrooo    | ✓         | ✓            | ✓     | ✓          |
| ops          | ✓         | ✓            | ✓     | already ✓  |

Then verify the full chain on each contour while the flag is still off:

```text
storefront
  → PAR
  → sl1rq_xxx
  → short URL (pass.<contour>/r/…)
  → ceremony (connect.identity.<contour>)
  → callback
```

Only enable the contour once both the matrix row and the chain pass.

## Trade-offs

- Storefront sign-in changes from an in-page ceremony to a redirect to the
  ceremony origin. This is the intended ADR 0028 shape but is a UX change.
- **Contour onboarding gains a mandatory issuer-registration step.** Previously a
  new storefront contour could come up almost autonomously. After this ADR a new
  contour requires client registration, a `client_secret`, redirect validation,
  and `rp_id` validation at the issuer before login works. This is the conscious
  price of making the authorization request issuer-owned: the PAR call fails
  without it (the same dependency that blocked early `ops` short links).

## Non-decision

This ADR does not change any deployed runtime behavior. The current in-page
storefront ceremony remains until the change-set above is implemented and
verified, so it is never mixed into the completed ADR 0028 rollout.
