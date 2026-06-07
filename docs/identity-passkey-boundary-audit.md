# Identity Passkey Boundary Audit

This audit tracks application-owned passkey/WebAuthn remnants after ADR 0023.

## Boundary

Applications are relying parties. They may initiate SL1 authorization and consume
verified identity proofs. They must not own credential registration, credential
selection, or passkey authentication.

## Retired Application Login Routes

These routes intentionally remain as compatibility responses and must stay
`410 Gone`:

- `GET /passkeys/authentication-options`
- `POST /passkeys/authenticate`

Source:

- `routes/web.php`
- `tests/Feature/ConsolidatedLoginTest.php`

Expected behavior:

- no active application-local passkey login;
- no WebAuthn challenge for marketplace login;
- copy directs users to SL1 identity instead.

## Retired Business Registration Passkey Paths

`PartnerRegistrationController` still contains legacy code below early `410`
returns for historical registration paths:

- `showEnroll()`
- `options()`
- `registerIdentity()`

These paths must not become user-facing again. Business registration should use
Meanly One / SL1 identity, then verified email, INN lookup, offer signing, and
moderation.

## Legacy Authority Exception: Vault Step-up

`CabinetController` still contains application-local passkey verification for
vault unlock:

- `GET /vault/passkey-options`
- `POST /vault/passkey-confirm`
- duplicate `/cabinet/vault/passkey-*` compatibility routes

This is not marketplace login, but it still makes the application validate a
local passkey for a protected action. Under ADR 0023 this is a direct exception
to the invariant:

```text
Applications never authenticate passkeys.
```

This exception exists only as migration debt. It must move to an SL1
authorization intent:

```text
meanly.vault.open
  -> SL1 authorization request
  -> Meanly One / SL1 Provider
  -> possession proof inside the Identity Layer
  -> authorization code or normalized proof
  -> vault session
```

Until migration, this path should not be extended to general login, registration,
seller authority, payment authority, or business onboarding.

Migration design:

- `docs/vault-authority-migration-design.md`

## User-facing Copy To Avoid

Avoid language that implies Marketplace owns browser passkey verification:

- "continue online with passkey"
- "browser passkey verification"
- "add Passkey in marketplace profile"
- "marketplace passkey login"

Preferred language:

- "Continue online with Meanly identity"
- "Meanly One verifies your identity"
- "SL1 identity proof"
- "identity provider verification"

## Follow-up Migration Targets

1. Replace vault passkey unlock with the SL1 `meanly.vault.open` authority flow
   defined in `docs/vault-authority-migration-design.md`.
2. Remove unreachable legacy passkey registration code after tests prove no
   route depends on it.
3. Keep retired passkey routes as explicit `410 Gone` compatibility guards.
4. Add regression tests that marketplace login and business onboarding cannot
   issue local WebAuthn registration/authentication challenges.
