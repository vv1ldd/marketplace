# ADR 0026: Settlement Instrument Sovereignty

**Scope:** This ADR defines the **settlement layer** — how value moves through
replaceable instruments attached to an identity. It complements ADR 0024
(governance: who controls `sl1e_`) and ADR 0025 (who may interpret truth). It
does not prescribe a specific custody technology (PRF, MPC, HSM, seed phrases).

### Constitutional trio (orthogonal ADRs)

| ADR | Question |
|-----|----------|
| **0024** | Who controls identity? |
| **0025** | Who may interpret a fact? |
| **0026** | Through which instruments may value move? |

These layers MUST remain orthogonal:

| Change | Touches | MUST NOT require rewriting |
|--------|---------|----------------------------|
| New passkey / recovery factor | 0024 | 0025, 0026 |
| New settlement adapter / rail | 0026 | 0024, 0025 |
| New proof type / consumer | 0025 | 0024, 0026 |

## Status

Accepted — **architecture-complete** (2026-06).

Implementation follow-ups (executor registry, wrapped custody, CI for Instrument
Replacement Test) belong in feature specs, drills, and tests — not as extensions
of this ADR.

## Document boundary

This ADR documents **invariants and forbidden drift**, not a shipping milestone.

| In scope | Out of scope |
|----------|--------------|
| Settlement spine, instrument replaceability | PRF / MPC / HSM design |
| `wallet-as-identity` fuse | Managed wallet v0 runbook |
| Instrument Replacement Test (contract) | Concrete test class name / file path |
| Orthogonality with 0024 / 0025 | New proof-type ADRs (touch 0025 only) |

When the settlement spine diagram is copy-pasted across many docs, extract
`docs/architecture/spine.md` and link from ADRs — do not duplicate before that
signal appears.

## Foundational invariant

> **Identity owns instruments. Instruments produce settlement. Settlement
> produces accounting.**

`sl1e_...` is the durable subject. Settlement instruments are **attachments**
to that subject — not substitutes for it, and not owners of it.

The settlement spine:

```text
sl1e_
  ↓
IdentityBinding          (settlement capability attachment)
  ↓
SettlementProof          (observed / verified value movement)
  ↓
CreditDecision           (eligibility to credit)
  ↓
AccountingEvent          (ledger / statement truth)
```

No step in this chain requires **wallet** as a primary domain object.

Wallet is a presentation and transport convenience. The model object is
**binding** — a settlement capability declared against an identity.

### Anti-pattern (inverted crypto stack)

```text
Seed
  ↓
Address (= identity)
  ↓
Apps
```

### Platform model

```text
Passkey / credentials
  ↓
sl1e_  (identity)
  ↓
Policy
  ↓
Settlement instruments
```

Address, private key, bank account, Lightning node, or future rail endpoint
are **implementations** of a binding — not identity itself.

## Central thesis

> **Settlement instruments are replaceable attachments to an identity. Identity
> continuity MUST NOT depend on any specific settlement instrument, signing
> mechanism, custody model, address, or wallet technology.**

From this thesis follow:

- managed → wrapped → external custody evolution **per instrument**
- server → client → external signing surfaces **per instrument**
- Polygon → Bitcoin → bank rails **without identity fork**
- revoke → replace → migrate **without “create new wallet and move funds” UX**

The valuable outcome of managed wallet v0 is not “another wallet type.” It is
proof that `sl1e_` behaves as a long-lived subject while instruments around it
are swappable adapters to value networks. See
[`managed-wallet-v0-level-3-staging-drill.md`](../managed-wallet-v0-level-3-staging-drill.md)
for the operational proof runbook.

## Binding is a capability, not a key container

Do **not** model:

```text
IdentityBinding → private key
```

Model:

```text
IdentityBinding → settlement capability
```

A binding MAY represent:

| Capability surface | Example `binding_key` / rail |
|--------------------|------------------------------|
| On-chain address   | `polygon`, `bitcoin`, `ton` |
| External wallet    | verified address, signature proof |
| Future fiat / CBDC | account endpoint, rail reference |
| Future protocols   | Lightning node, custom rail id |

A private key is **one implementation** of how a binding fulfills its capability.
It is not the binding's semantic type.

### Current implementation mapping

`identity_bindings` already carries capability semantics:

| Field | Role |
|-------|------|
| `binding_type` | capability class (`wallet`, future types) |
| `binding_key` | rail / network identifier |
| `binding_value_normalized` | canonical settlement endpoint (e.g. address) |
| `verification_state` | whether the capability is active for settlement |
| `verification_method` | how attachment was proven (`vault_key`, `signature`, …) |
| `binding_source` | coarse custody hint (`managed`, `external`) |
| `metadata` | operational detail (provisioning version, key reference, …) |

`vault_managed_wallet_keys` is an **implementation store** for the managed
custody path. It MUST NOT be treated as the definition of `IdentityBinding`.

## Two independent graphs

Wildflow answers two questions with **two graphs**, not one private key:

### Governance graph (ADR 0024)

```text
Passkey
Recovery passkey
Recovery code
Guardian
Root recovery key
…
```

**Question:** Who may re-establish control of `sl1e_`?

Events: `credential.bound`, `recovery.completed`, `recovery_policy.declared`, …

### Settlement graph (this ADR)

```text
Managed instrument
Wrapped instrument
External instrument
…
```

**Question:** How does value move to and from this identity?

Events / facts: binding created, proof observed, proof verified, credit
approved, instrument revoked, instrument replaced.

### Why separation matters

Most crypto products collapse both questions into one object:

```text
Private key → answers everything
```

Wildflow MUST NOT collapse them.

**Consequence 1 — instrument loss without identity loss**

```text
sl1e_x loses access to managed polygon binding
  → instrument.revoked
  → instrument.replaced (new binding, same identity)
  → governance graph untouched
```

**Consequence 2 — credential loss without instrument churn**

```text
sl1e_x loses iPhone / daily passkey
  → governance recovery path
  → settlement instruments remain valid
  → no forced address migration
```

Bounded contexts are correct when these flows are independent.

## Accounting consumes proofs, not keys

The ingress path MUST remain custody-agnostic.

`CreditDecisionPolicy` evaluates:

- proof status (`verified`, not already credited)
- binding active and verified
- vault and binding alignment with proof

It does **not** evaluate:

- `binding_source`
- presence of `vault_managed_wallet_keys`
- signing surface or executor implementation

**Ingress invariant:** If a `VaultSettlementProof` is verified and bound to an
eligible `IdentityBinding`, accounting MAY proceed — regardless of whether the
underlying instrument was managed, wrapped, or external.

Custody and signing affect **egress** (send, pay, sign) and **capability policy**
(what actions are offered), not whether observed inbound settlement is credited.

### Egress seam

Outbound execution resolves through pluggable executors (e.g.
`IdentityPaymentExecutor`). Today: `ManagedEvmIdentityPaymentExecutor`
(server-held key). Future: client signing, external wallet, MPC — without
changing the settlement proof → credit chain.

```text
ServerManagedExecutor
ClientSigningExecutor
ExternalWalletExecutor
MpcExecutor
```

C1 / accounting consumers receive `SettlementProof` (+ credit decision). They
MUST NOT depend on which executor produced or signed the underlying transaction.

## Operational metadata (not primary entities)

Custody and signing are **operational metadata** on a binding — answers to:

> How does this binding implement its settlement capability?

Recommended metadata keys (evolving; not all required in v0):

```json
{
  "custody_mode": "managed | wrapped | external",
  "signing_surface": "server | client | external",
  "provisioning": "managed_wallet_v0 | managed_wallet_import_v0 | …",
  "managed_key_reference": "uuid (when applicable)"
}
```

These dimensions are **orthogonal**:

| custody_mode | signing_surface | Example |
|--------------|-----------------|---------|
| `managed` | `server` | managed wallet v0 (current) |
| `wrapped` | `client` | passkey-unwrapped blob, mobile Secure Enclave |
| `external` | `external` | MetaMask, Tonkeeper, hardware wallet |
| `managed` | `client` | transitional: server stores ciphertext only it cannot decrypt |

Specific cryptography (PRF, WebCrypto, sodium, HSM) is an implementation choice
under `(custody_mode, signing_surface)` — not an architectural axis.

### `binding_source` (v0)

`binding_source` (`managed` | `external`) is a coarse v0 field. It MAY be
retained for compatibility. Long-term, prefer explicit `custody_mode` and
`signing_surface` in `metadata` without expanding the number of primary model
types.

## Instrument lifecycle

Bindings are versioned facts, not mutable secrets.

| Action | Identity | Binding | Keys / blobs |
|--------|----------|---------|--------------|
| **Create** | unchanged | new row | provision per custody_mode |
| **Revoke** | unchanged | `revoked` | retire implementation material |
| **Replace** | unchanged | revoke old + create new | new implementation |
| **Migrate custody** | unchanged | replace with new metadata | e.g. managed → wrapped |

Forbidden:

- Forking `sl1e_` because a settlement instrument changed
- Treating instrument replace as “new user” or “new wallet product”
- Requiring seed phrase export as the only migration path between custody modes

Banking analogy (intentional):

```text
Account (sl1e_)     — durable
Card A / Card B     — replaceable instruments
Apple Pay token     — another instrument surface
```

## Relationship to other ADRs

| ADR | Relationship |
|-----|--------------|
| **0024** | Governance graph: identity continuity, recovery policy |
| **0025** | Accounting truth interpreted once; consumers do not redefine proof meaning |
| **0019** | Action capabilities are authority-issued; settlement bindings are a different capability class |
| **0022** | Identity exists independently; business and settlement capabilities are granted |

ADR 0026 does not override ADR 0024. Identity recovery and instrument replace
are separate flows that MAY occur in either order depending on incident type.

## Forbidden patterns

### Architectural fuse: wallet-as-identity

The highest-value guard in this ADR is the explicit prohibition of
**wallet-as-identity**. Many systems begin correctly (`identity → wallet`) and
gradually collapse back:

```text
user_id = address
primary_account = wallet
wallet migration = user migration
```

At that moment governance and settlement graphs merge, and instrument
replaceability is lost. This ADR exists partly to prevent that drift over 2–3
years of product pressure.

**MUST NOT:**

- Use address, seed, or wallet id as the primary user / identity key
- Treat wallet migration as identity migration without an explicit, audited fork
- Model `sl1e_` as derived from or owned by any settlement endpoint

UI copy MAY say “wallet.” Domain models, foreign keys, and recovery flows MUST
anchor on `sl1e_` and `IdentityBinding`, not on address-as-identity.

### Other forbidden patterns

1. **Custody in credit path** — rejecting or approving credit based on how a key is stored
2. **Identity fork on instrument change** — new `sl1e_` when replacing polygon binding
3. **Collapsed graphs** — requiring wallet seed recovery to regain identity, or identity recovery to force new addresses without user intent
4. **Key table as model** — treating `vault_managed_wallet_keys` as the semantic center instead of `identity_bindings`
5. **Executor leakage** — accounting or proof verification branching on executor class

## Instrument Replacement Test

One-page expression of this ADR. Any feature that fails this test violates
settlement instrument sovereignty.

```text
Given:
  sl1e_x
  instrument_a  (active IdentityBinding)

When:
  instrument_a revoked
  instrument_b bound  (replacement for same capability / rail)

Then:
  identity id unchanged           (same sl1e_x / vault_id)
  governance history unchanged    (no credential.bound fork for identity)
  accounting history unchanged    (prior CreditDecisions / AccountingEvents intact)
  settlement continuity preserved (new proofs may attach to instrument_b;
                                   historical proofs remain tied to instrument_a)
```

**Pass:** replace polygon managed binding → new address, same identity, prior
statement lines still valid.

**Fail:** replace instrument → new `sl1e_`, merged user row, or rewritten
accounting history.

CI SHOULD eventually encode this as a feature test; until then it is the
document-level acceptance criterion for instrument lifecycle changes.

## Implementation status (2026-06)

| Property | Status |
|----------|--------|
| Identity ≠ wallet | **Achieved** — `sl1e_` + `IdentityBinding` |
| Instrument replace / revoke | **Achieved** — storefront + binding service |
| Managed custody v0 | **Achieved** — server encrypts, server signs (egress) |
| Proof → credit custody-agnostic | **Achieved** — `CreditDecisionPolicy` |
| Explicit `custody_mode` / `signing_surface` | **Partial** — `binding_source` + `metadata.provisioning` |
| Executor registry by binding | **Open** — singleton `ManagedEvmIdentityPaymentExecutor` |
| Wrapped / client signing | **Not started** |

## Consequences

- New rails (bank, Lightning, CBDC) extend **binding types and adapters**, not identity schema
- Custody evolution is **per-instrument migration**, not platform rewrite
- PRF, MPC, and mobile Secure Enclave are **egress implementations**, not spine changes
- UI may say “wallet” for familiarity; domain language SHOULD prefer **instrument** or **binding**
- Tests for accounting MUST use verified proofs, not assumptions about server-held keys

## Summary

```text
Identity owns instruments.
Instruments produce settlement.
Settlement produces accounting.

Governance graph  →  Who controls sl1e_?
Settlement graph  →  How does value move?

Wallet is not a primary object.
Binding is a settlement capability.
Keys are implementation detail.
```
