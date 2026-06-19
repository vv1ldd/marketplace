# Identity Claims Model

Engineering checkpoint. Conceptual vocabulary (`Claim`, `Evidence`, `Validity`) applies here and in design discussions only — **not** in migrations, tables, or public API until Gate 1 and Gate 2 pass.

## Phase closure (current)

**Design / documentation phase:** complete.  
**Mode:** observation only — until the first real bundle exists.

```
identity-claims-v1
External Reality → Signal → Evidence → Claim Update → History → Reproducible Bundle
```

**Hypothesis:** one model Node → Edge → Evidence → History describes different identity relations without core changes.

**Next decision point:** `scratch/verification-live-proofs/<timestamp>-<txSuffix>/`

- PASS → Artifact 2 (`controls` Email or `owns` Domain, same core)
- FAIL → observed limitation → new abstraction (not guesswork)

## Mission (one line)

**SL1 does not store reality.** SL1 stores a **history of identity claims**, supported by **evidence** from external sources.

Expanded:

> SL1 does not store reality.  
> SL1 stores the history of claims about identity, grounded in evidence obtained from external worlds.

## Layer hierarchy

Do not collapse levels:

```
Reality
   ↓
Signals          (Polygon tx, DNS lookup, email code, …)
   ↓
Evidence         (accepted basis for a claim; stored as binding_proofs + evaluation events)
   ↓
Claims           (edges: owns address, controls email, …; stored as identity_bindings)
   ↓
Identity Graph   (canonical node = VaultIdentity)
   ↓
History          (binding_events, verification_events)
```

## Three mistakes to avoid

| Mistake | Collapse |
|---------|----------|
| Web3 default | Address = Identity (signal = identity) |
| Proof systems | Evidence = Truth |
| SL1 trap | Claim = Reality |

Our model treats:

- **Signals** as inputs from external worlds
- **Evidence** as accepted grounds, not truth
- **Claims** as justified assertions at time T, not objective facts

## Verified in code (today)

| Concept | Implementation |
|---------|----------------|
| Node | `vault_identities` |
| Edge / claim | `identity_bindings` |
| Evidence | `binding_proofs` (`proof_type`, `proof_payload`, `proof_reference`) |
| Claim evaluation history | `verification_events`, `binding_events` |
| Graph diff events | `wallet_bound`, `wallet_binding_failed`, `wallet_revoked`, `proof_verified`, `proof_verification_failed` |
| SL1 root anchor | `vault_identities.anchor_address` |

Layers in code: Binding Layer + Verification Layer ≈ **claim + evidence management** (names unchanged in code).

## Unverified (hypothesis only)

Do **not** implement until after Gate 1 and Gate 2:

- Validity / freshness / TTL
- Trust scores
- Full claim lifecycle: Evidence Expired, Claim Weakens, Claim Revoked (beyond current revoke)
- Merge identity, delegate authority, recover identity

These are not rejected — the system has not yet been forced by real cases (expired email, lapsed domain, compromised address, replaced evidence). There is no data yet that requires a Validity layer.

**Identity governance — operational freeze point**

```text
Identity Continuity v1
Local precursor:        PASSED
Production readiness:   PENDING
Certificate:            NOT ISSUED
```

Do **not** claim “operationally proven” without staging context. Implementation (local): append
contract `9b88741`, replay, authorize continuity, local restore drill. Production (staging):
durability, destructive restore, 24h soak, certificate — all pending. Docs:
[`identity-continuity-v1-soak-gate.md`](identity-continuity-v1-soak-gate.md) ·
[`identity-continuity-v1-retention-replay-runbook.md`](identity-continuity-v1-retention-replay-runbook.md) ·
local precursor [`records/identity-continuity-v1-local-precursor-2026-06-19.md`](records/identity-continuity-v1-local-precursor-2026-06-19.md).

## Engineering principle: layers follow events

**Do not add a new layer until an event appears that cannot be expressed with existing layers.**

| Event | Existing layers suffice |
|-------|-------------------------|
| `wallet_bound` | Edge + History |
| `proof_verified` | Evidence + History |
| `wallet_revoked` | Edge + History |

When something like `evidence_expired` becomes necessary — and cannot be modeled as History + claim status alone — that justifies a **Validity** layer. Not before.

Same rule for Trust, merge, delegation: wait for a concrete event the current model cannot carry.

## Release gates

| Gate | Experiment | Pass criteria |
|------|------------|---------------|
| **1** | Live Polygon evidence | External signal → RPC → evidence accepted → claim update; dry-run + persistence; see `verification:validate-polygon-usdc-proof` |
| **2** | Non-EVM edge | Email (`controls`) or Domain (`owns`) through same binding / proof / events **without core schema changes** |
| **3** | Claim lifecycle | Expiry, weakening, invalidation (future) |

**Phase 3 Validation: PASSED** and **Verification Layer: VERIFIED AGAINST REAL NETWORK** only after Gate 1 persistence succeeds on real Polygon.

If Gate 1 and Gate 2 pass **without core changes**, the result is not “we designed an identity graph” but:

> We expressed two fundamentally different relations through one model: Node → Edge → Evidence → History.

That is an engineering outcome, not a whitepaper claim.

## Current project status

| Area | Status |
|------|--------|
| Core model | ✅ Implemented |
| Architecture | ✅ Documented |
| Mocked validation | ✅ Passing |
| **Live validation** | ⏳ **Pending** |

Next step is **operational**, not architectural.

### Critical path (no new branches until checked)

- [ ] **Artifact 1 — Live Polygon validation** (Gate 1)
- [ ] **Artifact 2 — Non-EVM edge validation** (Gate 2 — Email or Domain, not NFT)

Freeze protects **experiment purity**: no NFT, feed, reputation, trust, or delegation until both artifacts exist — otherwise it is unclear what actually worked.

**Experiment design:** one model · two relation classes · zero core changes.

Everything else waits until the model is proven across **two relation classes** on one core.

### Artifact 1 — Live Polygon (Gate 1)

Proves: external signal (Polygon tx) → evidence (`usdc_transfer` / signature) → claim update (`Vault owns Address`) → history (event recorded).

Artifact fields (enough):

- tx hash
- receipt found
- evidence accepted
- event recorded

### Artifact 2 — Non-EVM edge (Gate 2, after Artifact 1)

Proves: external signal (email / DNS) → evidence (`email_code` / `dns_record`) → **different relation** (`controls Email` / `owns Domain`) → **same** history model.

NFT is not Artifact 2 — it still tests `owns → EVM address`.

If both pass without core changes:

```
Different sources + different evidence + different relations → same identity model
```

That closes the main architectural risk — not “many integrations”, but **one primitive** (Node → Edge → Evidence → History) across two worlds.

### Validation contract

**Hypothesis:** Node → Edge → Evidence → History can describe identity relations of different classes without core changes.

**Success criterion for a new world:** only signal adapter, evidence verifier, and payload change — not node, edge, or history models.

**Process after each artifact:** ask what broke or was insufficiently expressed — not “what do we build next?” New abstractions follow Observation → Need → Abstraction.

### Artifact 1 bundle (reproducible, not a product feature)

On successful gate run, the command writes:

```
scratch/verification-live-proofs/{timestamp}-{txSuffix}/
  tx.json
  receipt.json
  decoded-evidence.json
  claim-update.json
  events.json
  manifest.json
```

Dry-run: `claim-update.json` and `events.json` explicitly record `persisted: false` / `recorded: false`.  
Persistence: full proof row + `proof_verified` event.

`manifest.json` includes a versioned validation contract (stable across reruns):

```json
{
  "validation_contract": {
    "validation_contract": "identity-claims-v1",
    "model": "Node-Edge-Evidence-History",
    "relation": "owns",
    "evidence_type": "usdc_transfer",
    "network": "polygon",
    "chain_id": 137,
    "gate": "dry_run"
  },
  "provenance_chain": {
    "external_signal": "tx.json",
    "rpc_observation": "receipt.json",
    "decoded_evidence": "decoded-evidence.json",
    "claim_update": "claim-update.json",
    "history_event": "events.json"
  }
}
```

Dry-run proves: Signal → Evidence.  
Persistence proves: Evidence → Graph state → History.

### Experimental boundary

Not: “we added USDC proof.”  
Test: **can an external signal become a justified identity state change through the shared mechanism?**

| Layer | Question |
|-------|----------|
| Adapter / Polygon | What happened externally? |
| Verifier | Why do we treat this as evidence? |
| Identity model | Which claim/edge changed? |
| History | How was the change recorded? |

**Auditability check (Artifact 1):** after the bundle exists, ask: *if the verifier is rewritten tomorrow, can we still explain why the old assertion existed?*  
If yes — Evidence is separated from Claim. If no — signal and identity state are still too coupled.

**Freeze** = do not change experimental conditions until observation. Then:

- **Variant A:** Signal → Evidence → Claim → History works → Artifact 2 (Email/Domain, same core).
- **Variant B:** a layer cannot express X → X justifies a new abstraction (Observation → Need → Abstraction).

## Freeze (validation phase, not development phase)

- No new proof types (NFT, registry sprawl)
- No code rename (`binding_proofs` → `evidence`, etc.)
- No Activity Feed, trust, or validity layer
- **Invest in:** first live Polygon proof, then first non-EVM edge

## Vocabulary mapping (docs only)

| Code (stable) | Docs / discussions |
|---------------|-------------------|
| `VaultIdentity` | Node |
| `identity_bindings` | Claim / edge |
| `binding_proofs` | Evidence |
| `proof_verified` | Evidence accepted |
| `wallet_bound` | Claim created |
| `wallet_revoked` | Claim revoked |
| `verification_events` | Claim evaluation history |

## Live validation commands

```bash
# Gate 1 — dry run (no DB writes)
php artisan verification:validate-polygon-usdc-proof \
  --tx=0x... --recipient=0x... --minimum-amount=0.01 --dry-run

# Gate 1 — persistence
php artisan verification:validate-polygon-usdc-proof \
  --tx=0x... --recipient=0x... --minimum-amount=0.01
```

Artifacts (on success): directory under `scratch/verification-live-proofs/{timestamp}-{txSuffix}/`

Optional: `--artifact-dir=/path/to/bundle`

Required env: `POLYGON_RPC_URL`, `POLYGON_RPC_ENABLED=true`, and transaction parameters (flags or `POLYGON_PROOF_E2E_*`).

## Operational mode (simple commerce first)

On-chain identity rails are **dormant by default** until live Artifact 1 validation:

```env
COMMERCE_CRYPTO_RAILS_ENABLED=false
```

When `false` (default):

- Polygon is hidden from storefront settlement catalog
- Wallet binding / USDC transfer proof APIs reject EVM rails
- Merchant crypto deposit rail is hidden and blocked
- SL1 vault, invoices, merchant transfers, and ops review remain active

To resume crypto work later:

```env
COMMERCE_CRYPTO_RAILS_ENABLED=true
POLYGON_RPC_ENABLED=true
POLYGON_RPC_URL=https://...
```

Then run Artifact 1 validation (see commands above).
