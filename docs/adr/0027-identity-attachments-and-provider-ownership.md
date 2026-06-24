# ADR 0027: Identity Attachments and Provider Ownership

**Scope:** This ADR defines the **ownership boundary** between the SL1 identity
layer (protocol) and settlement providers (Vault, banks, exchanges). It
complements [ADR 0024](0024-identity-root-authority-and-phased-sovereignty.md)
(governance), [ADR 0025](0025-interpret-once-consume-contracts.md) (interpretation), and
[ADR 0026](0026-settlement-instrument-sovereignty.md) (settlement spine).

## Status

Accepted — **2026-06**

## Foundational axiom

> **Identity never owns settlement infrastructure.**

Corollary chain:

```text
Identity owns attachments.
Providers own instruments.
Instruments produce settlement.
Settlement produces accounting.
```

`sl1e_...` is the stable subject. Attachments are **references** from identity
to provider-owned instruments — not custody of those instruments.

## Document boundary

| In scope | Out of scope |
|----------|--------------|
| Identity surface vs provider custody | Managed wallet v0 runbook |
| Attachment enumeration contract | Provider-specific KYC / compliance |
| Forbidden protocol responsibilities | Concrete HTTP paths per provider |
| Terminology (`instrument`, `attachment`, `provider`) | PRF / MPC / HSM design |

## Problem

The word **wallet** is overloaded:

| Surface | What users think | What it actually is |
|---------|------------------|---------------------|
| **Protocol UI** (`simplelayer.one`) | Metamask-style crypto wallet | Identity dashboard — passkeys, SL protocol balance, linked providers |
| **Vault UI** (`meanly.one`) | — | Custody + settlement — keys, USDC, BTC, send/receive |

When the protocol displays fake multi-chain balances (e.g. projected BTC/ETH),
users infer **“SL1 wallet contains BTC”** instead of **“SL1 identity may link to a
provider that holds BTC.”** That drift turns the identity protocol into a second
marketplace.

## Ownership model

```text
sl1e_
  ↓
Attachment          (identity layer: link / unlink / enumerate)
  ↓
Provider Instrument (provider layer: keys, addresses, rails)
  ↓
Settlement          (transfer, observe, proof)
```

Examples:

```text
sl1e_abc → attachment_123 → vault:polygon-usdc → USDC transfer
sl1e_abc → attachment_456 → wise:eur-account   → SEPA transfer
```

**IdentityBinding** SHOULD be read as:

> Identity **references** an external instrument — not “identity owns wallet.”

The binding record is an attachment pointer (`provider_id`, `instrument_id`,
`status`). Capability details (`supports_receive`, network labels, balances)
come from **provider summary**, not from identity schema.

## Identity surface responsibilities

The protocol / identity layer MAY:

- `authenticate` — passkey, Connect proofs
- `attach` — create attachment to a provider (via provider `link()`)
- `detach` — remove attachment
- `enumerate` — list attachments (provider id, instrument id, status)

The protocol / identity layer MUST NOT:

- store settlement private keys
- know blockchain networks as first-class identity schema
- execute payments or sign transactions
- compute provider balances
- import seed phrases

Native **SL** balance on the protocol node (rewards, receipts, settlement
*meaning* in SL1 ledger sense) MAY appear on the identity surface. External
fiat/crypto balances MUST NOT be simulated there.

## Provider responsibilities

A **wallet provider** (today: Meanly Vault; tomorrow: Coinbase, Wise, bank
rails) owns:

- instruments (managed keys, accounts, addresses)
- settlement adapters and execution
- capability matrix (receive, send, observe)
- balances and history for its instruments

Provider-facing API (conceptual):

```text
link(identity_proof)
unlink(attachment_id)
listInstruments(identity)
summary(identity)
```

Provider API MUST NOT be required to expose `sendTransaction` / `deriveAddress`
to the identity protocol — those stay inside provider UI or provider-authenticated
APIs.

## Attachment record (identity view)

Identity needs only:

```json
{
  "provider_id": "meanly.vault",
  "instrument_id": "inst_123",
  "status": "active"
}
```

Not:

```json
{
  "network": "polygon",
  "supports_usdc": true,
  "supports_receive": true
}
```

Network and capability knowledge is **settlement infrastructure** — provider
owned.

## Protocol UI consequences

`simplelayer.one` identity section SHOULD present:

```text
SL1 Identity
  SL balance (protocol-native)
  Linked providers
    Meanly Vault — Connected — N instruments — [Open →]
```

Heavy custody UI (USDC, BTC, SOL, TON, send, swap, history) lives in the
provider application.

## Relationship to ADR 0026

ADR 0026: *Identity owns instruments. Instruments produce settlement.*

ADR 0027 **tightens** ownership:

- **Instruments** are provider objects.
- **Identity** owns **attachments** (references to those instruments).

ADR 0026 remains valid for the settlement spine; ADR 0027 prevents the
identity layer from absorbing provider implementation.

## Implementation status (2026-06)

| Property | Status |
|----------|--------|
| Vault owns managed keys + adapters | **Achieved** — marketplace |
| IdentityBinding as attachment | **Achieved** — `binding_source`, metadata |
| Protocol identity summary without fake chains | **In progress** — simple-l1 `#identity` |
| Provider `summary()` for protocol aggregator | **Open** — HTTP bridge from SL1 to Vault |
| Multi-provider registry | **Not started** |

## Consequences

- Do **not** port `ManagedWalletProvisioner`, settlement adapters, or payment
  executors into simple-l1.
- Remove protocol-side **external_projection** balances (BTC/ETH placeholders).
- Future rails (PIX, SEPA, Stripe Treasury) add **providers**, not identity
  schema fields.
- UI copy on protocol domains SHOULD prefer **Identity** over **Wallet** where
  custody is not implied.

## Summary

```text
Who are you?     → SL1 identity surface
What is linked?  → attachments (provider_id, instrument_id, status)
What do you own? → provider summary (instruments, balances, actions)
How to pay?      → provider custody UI — never protocol monolith
```
