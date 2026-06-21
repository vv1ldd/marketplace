# Phase C — Authority Separation

**Status:** CLOSED

**Scope:** Separation of identity, wallet, and settlement authorities. Settlement attachment durability (Polygon/Bitcoin) is the **mechanism** that proved the boundary; the **outcome** is orthogonal authority layers that future changes must respect.

**Phase question:** *Are authorities separated?*

**Proven:**

- Verified attachment survives re-authentication
- Settlement state survives projection/cache reset
- Balance is reconstructed from external settlement source
- No manual repair required

**Invariant:**

A verified settlement attachment remains durable independently from disposable projections.

**Architecture rules:**

- Vault is not a balance database
- Balances are observed
- Attachments are persisted
- Identity is the continuity primitive

**Non-goals:**

- Vault does not custody settlement state
- UI/cache is not source of truth
- Projection failure does not imply asset loss

---

## Production rollout boundary (post-closure)

Phase C is closed by operational proof. Production exposure follows a separate, controlled path:

1. **Closure artifact** (this document) — invariant reference for all subsequent changes
2. **`settlement_adapters.polygon`** — default-off, `read_only` first (`config/settlement_adapters.php`)
3. **`SettlementAdapter` contract** — `verifyAttachment`, `observeBalance`, `listEvents`, `healthCheck`
4. **Settlement audit events** — `attachment_created`, `settlement_observed`, `balance_read` (observability without balance custody)

Only after adapter contract + audit boundary are in place:

```text
Phase C     CLOSED
Adapter     READY
Production  ENABLED   ← settlement_adapters.polygon.enabled=true, mode=read_only
Rollout     LATER     ← blockchain_networks.networks.polygon.enabled=true
```

**Authority model:** Wallet connect proves [identity authority](../architecture/settlement-adapter-framework.md#authority-model-canonical); adapter `read_only` / `full` governs [settlement authority](../architecture/settlement-adapter-framework.md#authority-model-canonical) only — not wallet signing or custody.

`polygon.enabled=true` in network config is the final network rollout flag — not the trust switch for the experiment.

**Related code:**

- `app/Contracts/SettlementAdapter.php`
- `config/settlement_adapters.php`
- `app/Services/SettlementAuditEventRecorder.php`
- `tests/Feature/SettlementAttachmentOperationalDrillTest.php`

**Capstone:** [Settlement Adapter Framework](settlement-adapter-framework.md) — architecture closure → operational promotion phase

---

## Conceptual closure

Phase C is closed not only by Polygon/Bitcoin attachment durability, but by **authority separation** — a project grammar for future rails, proofs, and UI flows.

The design question for any new capability is no longer *“How do we explain this to the user?”* but **“Which layer does this belong to?”** If the answer is unambiguous, the architecture stays coherent.

**Two long-lived artifacts:**

1. **Model** (rail-agnostic):

   ```text
   Identity is durable.
   Wallets are attachments.
   Rails are settlement adapters.
   Adapter modes grant settlement capabilities,
   not wallet control.
   ```

2. **Architectural correctness test:**

   ```text
   Which authority changes?
   □ Identity
   □ Wallet
   □ Settlement
   ```

Use this before implementation review. Examples: *auto-settlement* that requires platform wallet signing → **Wallet authority** → high-level architectural event, not a routine feature. *Lightning rail*, *bank rail*, *new wallet provider* → classify layer first, then implement.

If future changes can be described through these two artifacts **without new entities or exceptions**, the model has generalized successfully. Phase D may then discuss **which settlement capabilities to enable** without reopening identity, custody, or authority boundaries.

**Completion criterion:** Phase C is closed when the next phase can evolve the system **without reopening** identity, attachment, wallet custody, or authority boundaries. Phase D owns capability promotion only — adapter modes, proofs, settlement/merchant/checkout rails — not the three-authority model.

```text
Phase D may change:
  ✓ adapter mode
  ✓ proof submission
  ✓ settlement workflows
  ✓ merchant rails
  ✓ checkout rails

Phase D must not change:
  ✗ identity model
  ✗ attachment model
  ✗ wallet custody model
  ✗ authority boundaries
```

**Canonical reference:** [Authority model](../architecture/settlement-adapter-framework.md#authority-model-canonical) · feature review table · layer boundary invariants · [Phase D — Capability Promotion](phase-d-adapter-operational-stability.md)
