# ADR 0023: Identity Authority and Credential Ownership

## Status

Accepted

## Context

Meanly applications previously contained remnants of direct passkey authentication flows.
That blurred the boundary between credential ownership, identity authority, and
application sessions.

The system now treats Marketplace, B2B, Ops, mobile clients, and future apps as
relying parties. They request identity authorization and consume verified SL1
authority. They do not own credential mechanics.

## Decision

Passkeys belong to the Identity Layer.

Applications are relying parties. They consume authority; they do not authenticate
passkeys.

```text
Identity Layer owns credentials.
Authority Layer proves current authority.
Application Layer consumes authority.
```

The responsibility chain is:

```text
Passkey -> Identity -> Authority -> Application Session -> Commerce
```

Passkey evidence proves possession. SL1 identity proves subject continuity. The
SL1 Provider proves current authority. Applications create application-local
sessions for the verified `entity_l1_address`.

## Modes

### Known Subject

The device already knows:

- `entity_l1_address`
- `key_l1_address`
- trusted provider metadata
- enough cached policy/routing context to know the key is eligible

Discovery is not required. Meanly One may construct a local proof after user
presence, then prefer provider normalization into an authorization code:

```text
Passkey -> local assertion -> local proof -> SL1 Provider -> authorization code
```

For compatibility, recovery, development, or constrained environments, Meanly
One may return a bounded native direct proof to a relying party:

```text
Passkey -> local proof -> relying party verification
```

Native direct proof mode is not the production default.

### Zero Device

The device does not know the subject or does not have a trusted local key. It may
only have an identity hint, QR code, invite, username, email, or phone.

This requires the provider:

```text
Discovery -> Enrollment -> Trust Establishment -> Credential Binding -> Authorization
```

Recovery, enrollment, policy, device approval, credential rotation, and risk
controls live in the Identity Layer.

## Application Contract

Applications MAY:

- initiate SL1 authorization
- receive authorization results
- validate normalized SL1 provider assertions
- establish application-local sessions for `entity_l1_address`

Applications MUST NOT:

- register passkeys
- enumerate passkeys
- select passkeys
- validate passkeys
- expose active `/passkey/login` or `/passkey/register` flows
- store authenticator-specific metadata as authority
- branch product behavior by authenticator type

Applications may store an application session, authorization evidence hashes, and
business audit metadata derived from SL1 proofs. They must not become credential
authorities.

## Consequences

Credential evolution is isolated to the Identity Layer. If WebAuthn changes or a
new credential mechanism replaces passkeys, applications should not need to
change their auth model.

New clients can be added without copying credential logic. Marketplace, B2B,
Ops, Merchant Console, mobile apps, and future clients use the same relying-party
contract:

```text
authorize -> callback -> validated SL1 proof/code -> application session
```

`SIMPLE_L1_ACCEPT_NATIVE_DIRECT_PROOF` remains a compatibility or constrained
environment mode. Provider mode remains the production default.

## Follow-up

Audit old application-owned passkey/WebAuthn routes and copy. Retired routes may
remain as explicit `410 Gone` compatibility responses, but no user-facing
application flow should depend on local marketplace passkey login or registration.

See ADR 0024 for phased Identity Root Authority (provider v1, sovereign-ready
metadata, future user root recovery key).
