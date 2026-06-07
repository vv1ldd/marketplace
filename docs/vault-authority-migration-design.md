# Vault Authority Migration Design

## Purpose

Close the `CabinetController` vault step-up exception documented in
`identity-passkey-boundary-audit.md`.

The migration goal is not to replace local WebAuthn with remote WebAuthn. The
goal is to remove credential semantics from the application layer.

## Authority Invariant

Current exception:

```text
vault session created because local passkey verification succeeded
```

Target state:

```text
vault session created because SL1 authority for intent=meanly.vault.open was granted
```

Vault must not know whether authority came from a passkey, hardware key, future
credential type, delegated authorization, or recovery flow. It consumes only a
verified authority result.

## Target Flow

```text
Application Intent
  -> SL1 authorization request
  -> Meanly One / SL1 Provider
  -> possession proof inside the Identity Layer
  -> authorization code or normalized proof
  -> Marketplace callback
  -> vault session
```

Concrete vault intent:

```text
meanly.vault.open
  -> SL1 authorization request
  -> Meanly One / SL1 Provider
  -> verified authority for subject and intent
  -> vault session with bounded expiry
```

## Required Authority Claims

Vault step-up needs authority claims, not credential claims:

- `subject`: canonical `entity_l1_address` authorized for the current account.
- `intent`: must equal `meanly.vault.open`.
- `nonce`: replay guard issued by Marketplace.
- `state`: callback correlation value issued by Marketplace.
- `issued_at`: provider-issued timestamp.
- `expires_at`: provider-issued or policy-derived expiry timestamp.
- `authorization_id`: stable provider-side authorization event identifier.
- `assurance_level`: provider-normalized assurance for this action.

Marketplace may persist hashes or identifiers needed for audit and replay
defense. It must not persist credential-level authority.

Treat this list as the vault authority interface contract. Future
implementations should be reviewed against the data shape as well as behavior:
vault receives granted authority, not credential evidence.

## Forbidden Credential Claims

Vault must not require or inspect:

- `credential_id`
- WebAuthn credential payloads
- `webauthn_sign_count`
- attestation data
- `authenticator_type`
- `passkey_provider`
- transport hints
- platform authenticator details
- device metadata
- biometric modality

If a provider includes these fields for its own diagnostics, Marketplace must
ignore them for vault authorization.

## Application Contract

`CabinetController` should become an intent and session boundary:

1. Create a `meanly.vault.open` authorization request.
2. Store a short-lived pending nonce/state for the current user.
3. Redirect or hand off to Meanly One / SL1 Provider.
4. Validate the returned authorization result through the SL1 authority
   verifier.
5. Create the existing bounded vault session only after authority validation.

The controller must not:

- generate WebAuthn options;
- call passkey lookup or WebAuthn verification actions;
- query local passkey records to decide vault access;
- branch on authenticator metadata.

## Transitional Routes

Existing routes are transitional compatibility surfaces:

- `GET /vault/passkey-options`
- `POST /vault/passkey-confirm`
- `GET /cabinet/vault/passkey-options`
- `POST /cabinet/vault/passkey-confirm`

During migration, new authority routes should be introduced before old routes are
removed:

- `GET /vault/authorize`
- `GET /vault/authorize/callback`

After the authority flow is active, passkey routes should return `410 Gone` with
copy directing clients to Meanly One.

## Boundary Tests

Boundary tests prove the application no longer validates credentials directly:

- `IdentityAuthorityBoundaryGuardrailTest` keeps WebAuthn/passkey verification
  dependencies from expanding outside documented legacy boundaries.
- `CabinetController` does not generate WebAuthn request options for vault
  unlock.
- `CabinetController` does not call passkey authentication actions.
- `CabinetController` does not load local credential records for vault authority.
- Vault unlock tests do not depend on `credential_id`, authenticator metadata, or
  WebAuthn assertion shape.

These tests should fail if credential semantics re-enter the application layer.

## Authority Tests

Authority tests prove the vault opens only through granted authority:

- valid `meanly.vault.open` authorization creates a bounded vault session;
- wrong intent is denied;
- expired authorization is denied;
- replayed nonce/state is denied;
- subject mismatch is denied;
- missing `authorization_id` is denied;
- insufficient `assurance_level` is denied;
- malformed provider result is denied without creating a vault session.

These tests should assert business authority, not provider implementation
details.

## Migration Steps

1. Add an SL1 authority verifier contract for normalized authorization results.
2. Add `meanly.vault.open` request and callback routes.
3. Move vault unlock UI to the new Meanly One authorization flow.
4. Create vault sessions from validated authority claims only.
5. Add boundary and authority tests.
6. Convert old vault passkey routes to `410 Gone`.
7. Update `identity-passkey-boundary-audit.md` and close the Legacy Authority
   Exception.

## Closure Criteria

The exception is closed when:

- no vault path generates or verifies local WebAuthn challenges;
- no vault path reads local passkey records as authority;
- `CabinetController` contains no WebAuthn-specific vault code paths;
- the application layer imports no credential-verification components for vault
  authority;
- `IdentityAuthorityBoundaryGuardrailTest` lowers the
  `CabinetController.php` credential-boundary baseline to `0`;
- valid SL1 `meanly.vault.open` authority opens the vault;
- invalid, expired, replayed, wrong-subject, or wrong-intent authority is denied;
- `identity-passkey-boundary-audit.md` no longer lists vault unlock as an active
  exception.
