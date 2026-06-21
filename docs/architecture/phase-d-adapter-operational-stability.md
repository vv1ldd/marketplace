# Phase D — Capability Promotion

**Status:** CLOSED (architecture) · operational promotion PENDING (mainnet soak)

**Scope:** Promoting settlement capabilities per rail (`read_only` → `full`, proofs, merchant/checkout rails) **without** changing identity, attachment, wallet custody, or authority boundaries.

**Phase question:** *Which settlement capabilities are enabled?*

Architecture for Phase D is closed. Remaining work is **operational only**:

```text
Mainnet soak → operational evidence → promotion
```

See [Settlement Adapter Framework](settlement-adapter-framework.md) for capstone state.

Phase D’s achievement is not Polygon-specific. It establishes a formal barrier between:

```text
external truth
      ↓
adapter observation
      ↓
application visibility
```

No layer below application visibility may invent settlement state.

**Stable separations (held):**

```text
Adapter readiness    ≠    Product exposure
SettlementAdapter    ≠    Settlement engine
```

`SETTLEMENT_ADAPTER_POLYGON_ENABLED=true` does not open asset actions — it makes the proven read path visible.

**Phase D may change:**

```text
✓ adapter mode (read_only → full, per rail)
✓ proof submission
✓ settlement workflows
✓ merchant rails
✓ checkout rails
```

**Phase D must not change:**

```text
✗ identity model
✗ attachment model (identity verification semantics)
✗ wallet custody model
✗ authority boundaries (Identity / Wallet / Settlement)
```

If a Phase D proposal requires platform **wallet authority**, it is out of scope — escalate as a new architectural phase, not a routine promotion.

**Mental model shift:**

```text
Before:  "Add a network"
Now:     "Connect another source of observable settlement state"
```

**Depends on:**

- [Settlement Adapter Framework](settlement-adapter-framework.md) — model, invariants, promotion gate
- [Phase C closure](phase-c-settlement-attachment-closure.md) — authority separation proven
- `SettlementAdapter` contract — `verifyAttachment`, `observeBalance`, `listEvents`, `healthCheck`
- Three independent rollout switches:

```text
POLYGON_RPC_ENABLED
        |
        v
Adapter can observe chain

SETTLEMENT_ADAPTER_POLYGON_ENABLED
        |
        v
Product can expose settlement adapter

blockchain_networks.networks.polygon.enabled
        |
        v
Network feature rollout
```

**read_only contract:**

```text
allows:
    verify attachment
    observe balance
    audit reads
blocks:
    transfer proof
    write operations
    settlement actions
```

Production can confirm **“we see real settlement states”** without claiming **“we execute settlement actions”**.

---

## Settlement Adapter Invariant

**Status:** CLOSED (behavior contract fixed before rollout)

**Adapter responsibility:** observe reality

**Adapter prohibition:** invent reality

The adapter is not a ledger and not a source of truth. It translates external settlement state into verifiable observation.

```text
If observation succeeds:
    produce evidence
If observation fails:
    produce availability state
Never:
    failure → zero balance
    failure → attachment change
    failure → fake observation
```

When observation is **unavailable**:

- does not create `balance_read`
- does not mutate attachment
- does not imply zero balance

Only **successful observation** creates observation evidence.

This protects the most dangerous semantic error: **unavailable ≠ empty**.

**Enforced in:** `PolygonSettlementAdapter::observeBalance()` — audit write occurs only when `observation_state === live`.

**Layer contract:**

```text
Identity
    |
    v
Attachment
    |
    v
SettlementAdapter
    |
    +--> observe success
    |        |
    |        v
    |    observation evidence
    |
    +--> observe failure
             |
             v
        availability state
        (never zero)
```

Valid chain observation (zero is real):

```json
{
  "observed": true,
  "observation_state": "live",
  "amount": "0"
}
```

Infrastructure failure (cannot observe):

```json
{
  "observed": false,
  "observation_state": "balance_unavailable",
  "reason": "rpc_error"
}
```

---

## Health gate

`healthCheck()` PASS when:

- adapter registered
- rpc reachable (when `POLYGON_RPC_ENABLED`)
- chain id matches expected network

`healthCheck()` FAIL codes:

| Code | Meaning |
|------|---------|
| `rpc_error` | RPC configured/enabled but unreachable or wrong chain |
| `balance_unavailable` | Observation path blocked (RPC off, adapter off, crypto rails off) |
| `stale_observation` | RPC healthy but no recent successful `balance_read` audit (when reads exist) |

**Test:** `tests/Feature/SettlementAdapterHealthTest.php`

---

## Observation replay gate

Repeat the Phase C attachment-durability drill on the adapter boundary (authority separation unchanged):

```text
observe
  ↓
audit event (balance_read)
  ↓
projection loss
  ↓
observe again
  ↓
same external truth
```

Proves **external settlement state is reproducible from the adapter boundary** — not that cache survived.

**Test:** `tests/Feature/SettlementAdapterObservationReplayTest.php`

---

## Mainnet soak gate

**Status:** PENDING — the only remaining operational proof before exposure.

This is **not** a check for “does Polygon work” — that was already proven. What remains unknown is production reality through the adapter boundary.

```text
Production reality
        ↓
PolygonSettlementAdapter
        ↓
Observation contract
        ↓
Audit evidence
```

```text
Phase D — Mainnet Soak
Input:
    real chain conditions
Validate:
    health stability
    observation correctness
    failure semantics
    replay determinism
Output:
    adapter trusted for visibility
```

**PASS criteria:**

- health stable
- no false zero
- rpc failure produces availability state (not zero, not `balance_read`)
- live chain zero remains valid zero (`observed: true`, `live`)
- replay deterministic
- no attachment mutation on adapter failure
- stale detection behaves correctly

Record results in ops notes before promoting exposure.

**Post-soak release artifact:** [Promote Settlement Adapter Observation Path](promote-settlement-adapter-observation-path.release-note.md)

---

## Architecture closure state (current)

```text
Phase A  Identity correctness        CLOSED   → Is identity correct?
Phase B  Identity continuity         CLOSED   → Is identity durable?
Phase C  Authority separation        CLOSED   → Are authorities separated?
Phase D  Capability promotion        CLOSED   → Which settlement capabilities are enabled?
         ├─ health gate             READY (automated)
         ├─ replay gate             READY (automated)
         └─ mainnet soak            PENDING (operational)

Polygon adapter
    implementation                 READY
    read_only                      READY
    writes                         DISABLED

Exposure                         PENDING
```

---

## Post-promotion state (after mainnet soak PASS)

Deployment state change only — not architecture:

```env
SETTLEMENT_ADAPTER_POLYGON_ENABLED=true
```

```text
Exposure                         ENABLED
```

**Unchanged:** schema, identity, attachment, custody, truth model.  
**Changed:** visibility of proven observation path.

---

## Phase matrix (reference)

See [Settlement Adapter Framework](settlement-adapter-framework.md) for capstone state. Architecture closed; soak → promotion remains.

**Exposure progression** (does not change the data model — only path availability):

```text
OFF
 ↓
Adapter exists, hidden
 ↓
Adapter visible read_only          ← SETTLEMENT_ADAPTER_POLYGON_ENABLED=true
 ↓
(optional future)
full settlement actions
```

Enabling Polygon no longer changes the model. It opens the first implementation instance of a shared settlement contract.

A second rail later tests **adapter contract compliance**, not “a new network integration”:

```text
verifyAttachment()
observeBalance()
listEvents()
healthCheck()
```

If a rail satisfies the contract, it is another adapter — not new architecture.

**Next PR after soak:** [Promote Settlement Adapter Observation Path](promote-settlement-adapter-observation-path.release-note.md)

**Architecture layers (held):**

```text
Identity Layer
      |
      v
Attachment Contract
      |
      v
Settlement Adapters
      |
      v
External Chains
```

Polygon is the first adapter passing this contract — not part of the identity model.

**Production enable (post mainnet soak PASS):**

```env
SETTLEMENT_ADAPTER_POLYGON_ENABLED=true
SETTLEMENT_ADAPTER_POLYGON_MODE=read_only
```

This enables an **observer**, not a money system. `blockchain_networks.networks.polygon.enabled=true` remains a separate network rollout flag.

**Related code:**

- `app/Services/SettlementAdapters/PolygonSettlementAdapter.php`
- `app/Services/SettlementAuditEventRecorder.php`
- `tests/Feature/SettlementAdapterHealthTest.php`
- `tests/Feature/SettlementAdapterObservationReplayTest.php`
