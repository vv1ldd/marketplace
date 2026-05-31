# Ops Panel Parity

## Current Rule

Legacy operational panels are consolidated into the Ops Center. A legacy panel redirect must not mean lost functionality.

```text
/tribunal -> /ops
/treasury -> /ops
/kernel   -> /ops
/support  -> /ops
```

## Parity Map

| Legacy panel | Ops destination | Status |
| --- | --- | --- |
| `/support` | Ops `Support` tab | Available: ticket list, ticket details, admin replies |
| `/tribunal` | Ops `Ledger Tribunal` tab | Restored: chain validation and Audit Oracle routes |
| `/treasury` | Ops finance/ledger surfaces | Needs explicit parity audit |
| `/kernel` | Ops Simple L1 trace / system surfaces | Needs explicit parity audit |

## Restored Tribunal Surface

Routes:

```text
POST /ops/dashboard/tribunal/validate-chain
POST /ops/dashboard/tribunal/chat
```

UI:

```text
/ops?tab=tribunal
```

Access:

- `super_admin`
- `auditor` for tribunal validation/chat routes

## Guardrail

Legacy redirects are allowed only when the corresponding operational function is reachable inside Ops.
