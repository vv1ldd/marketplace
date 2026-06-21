# Promote Settlement Adapter Observation Path

**Type:** rollout / exposure promotion (not architecture change)

**Framework:** [Settlement Adapter Framework](settlement-adapter-framework.md)

**Status:** TEMPLATE — fill in after [Phase D mainnet soak](phase-d-adapter-operational-stability.md#mainnet-soak-gate) PASS

**Switch:**

```env
SETTLEMENT_ADAPTER_POLYGON_ENABLED=true
SETTLEMENT_ADAPTER_POLYGON_MODE=read_only
```

---

## Summary

Promote **settlement observation visibility** — not “we added Polygon”. This is not a custody or settlement-writes launch.

`SETTLEMENT_ADAPTER_POLYGON_ENABLED=true` exposes a proven read path only.

## Promoted

- Settlement observation visibility (Polygon adapter, `read_only`)
- Proven observation path available to product surfaces that consume `SettlementAdapter`

## Preserved

- Identity continuity
- External settlement truth (chain remains source of truth)
- [Settlement Adapter Invariant](phase-d-adapter-operational-stability.md#settlement-adapter-invariant) / adapter contract
- Authority separation ([Phase C](phase-c-settlement-attachment-closure.md))
- `read_only` guarantees

## Not enabled

- Writes
- Transfers
- Custody semantics
- `blockchain_networks.networks.polygon.enabled=true` (separate network rollout flag)

## Unchanged

- Schema
- Identity model
- Custody assumptions
- Source of truth

## Changed

- **Who can see** the proven observation path (application visibility only)

---

## Soak evidence (fill on promotion)

| Check | Result | Notes |
|-------|--------|-------|
| Health stable | | |
| No false zero | | |
| RPC failure → availability state | | not zero, no `balance_read` |
| Live chain zero → valid zero | | `observed: true`, `live` |
| Replay deterministic | | |
| No attachment mutation on failure | | |
| Stale detection correct | | |

**Soak date:** _YYYY-MM-DD_  
**Operator:** _@handle_  
**RPC endpoint:** _redacted or internal ref_

---

## Rollback

```env
SETTLEMENT_ADAPTER_POLYGON_ENABLED=false
```

Observation path hidden; attachments and identity unaffected.
