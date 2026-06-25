# ADR 0029: Connect Origin UX Boundary

**Scope:** This ADR captures the next product/architecture decision after
[ADR 0028](0028-canonical-webauthn-rp-boundary.md). It asks whether the
`connect.identity.*` origin should expose any post-authenticated account UI, or
whether it should remain a ceremony-only surface.

## Status

Accepted - **2026-06** (UX workstream implemented in `simple-l1` commit `b34d77b`)

Implemented: contour-aware `walletSurfaceForHost`, RU/EN ceremony string catalog
threaded via `ui_locale`, connect-origin landing page for `GET /` on
`connect.identity.*`.

## Current position

Do **not** modify the post-authenticated wallet screen as part of the ADR 0028
rollout.

ADR 0028 fixed the production identity trust boundary:

```text
identity.*          = authentication authority / RP boundary
connect.identity.*  = ceremony origin
pass.*              = issuer endpoint
```

The rollout work has already aligned the authentication flow with this contract:

```text
connect.identity.meanly.one
  -> Sign in to Meanly
  -> Continue with Meanly
```

Users no longer see Vault-oriented copy in the authentication ceremony itself.

Root behavior for the connect origin is not yet defined by ADR 0028:

```text
connect.identity.meanly.one/
  -> currently falls back to the general Simple Layer One protocol landing page
```

This is not an authorization failure. The active ceremony routes already use the
connect surface:

```text
connect.identity.meanly.one/authorize
connect.identity.meanly.one/r/sl1rq_...
connect.identity.meanly.one/wallet
```

The undefined behavior is what the connect origin should show when a user visits
it directly, outside an authorization flow.

## Decision question

Should authenticated users be able to browse a wallet/vault UI from
`connect.identity.*`?

## Options

### Option A: Keep current wallet screen

After authentication, `connect.identity.*` may continue to show the current
post-authenticated account UI:

```text
Your Vault
Open Vault
Switch account
Sign out
```

This preserves current behavior and avoids combining UX redesign with the
identity infrastructure rollout.

### Option B: Ceremony-only connect origin

`connect.identity.*` becomes a pure ceremony surface:

```text
pass.*      owns issuance
identity.*  owns authentication
connect.*   owns ceremony
```

In this model, `connect.identity.*` never becomes a mini wallet. It only
collects proof, confirms intent, and returns to the relying application.

### Root behavior alternatives

The direct root route for `connect.identity.*` needs a separate UX contract:

```text
GET https://connect.identity.meanly.one/
```

Possible outcomes:

- Redirect to `pass.*`, treating the connect origin as ceremony-only and not a
  standalone site.
- Render a minimal connect landing page explaining secure identity connection and
  the WebAuthn ceremony boundary.
- Return an explicit non-document response, such as `404`, for direct visits.

The current fallback to the general protocol marketing page is historical
behavior, not a deliberate identity surface decision.

## Recommendation

Defer the decision until after ADR 0028 is fully stable in production.

The current preference is **Option B** for conceptual clarity, but it should be
implemented as a separate change set. Keeping this separate prevents ambiguity
between:

```text
ADR 0028 rollout
  = RP boundary, issuer delegation, production identity hosts

Connect UX simplification
  = whether connect.identity.* may expose account/vault UI
```

## Non-decision

This ADR does not change `renderWallet` or any deployed runtime behavior.

It also does not change the current `GET /` behavior for
`connect.identity.meanly.one`. Defining that root contract is part of this
future ADR 0029 change set, not a repair inside the ADR 0028 rollout.

