# ADR 0029: Connect Origin UX Boundary

**Scope:** This ADR captures the next product/architecture decision after
[ADR 0028](0028-canonical-webauthn-rp-boundary.md). It asks whether the
`connect.identity.*` origin should expose any post-authenticated account UI, or
whether it should remain a ceremony-only surface.

## Status

Proposed - **2026-06**

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

