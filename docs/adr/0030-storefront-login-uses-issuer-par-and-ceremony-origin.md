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

## Trade-offs

- Storefront sign-in changes from an in-page ceremony to a redirect to the
  ceremony origin. This is the intended ADR 0028 shape but is a UX change.
- Each contour must have its storefront client registered before cutover, or the
  PAR call fails (the same dependency that blocked early `ops` short links).

## Non-decision

This ADR does not change any deployed runtime behavior. The current in-page
storefront ceremony remains until the change-set above is implemented and
verified, so it is never mixed into the completed ADR 0028 rollout.
