# Writer Authority Readiness

## Purpose

Writer Authority Readiness answers:

> Why is this writer allowed to emit accepted transitions?

It protects the marketplace from split-brain histories where two writers emit conflicting accepted transitions for the same authority scope.

## Principle

Exactly one authority holder may emit accepted transitions per scope and epoch.

The state `no active writer` is safer than `two active writers` for the same authority scope.

## Authority Scope

Writer authority must be scoped. Initial candidate scopes:

- `marketplace:global`
- `legal_entity:{id}`
- `shop:{id}`
- `wallet:user:{id}:asset:{asset}`
- `order:{id}`
- `provider:{id}`
- `catalog:{provider}:{market}`
- `settlement:{id}`

Scopes should be narrow enough to permit partitioned writers, but stable enough to prevent ambiguous ownership.

## Readiness Object

Each scope must expose:

- `scope`
- `authority_holder`
- `authority_epoch`
- `fencing_status`
- `conflict_status`
- `last_heartbeat_at`
- `last_transition_id`
- `last_transition_hash`
- `last_anchor_hash`

Example:

```yaml
scope: wallet:user:42:asset:RUBT
authority_holder: node-br-1
authority_epoch: 2026-06-02T16:00:00Z:0007
fencing_status: fenced_previous_holder
conflict_status: none
last_heartbeat_at: 2026-06-02T16:01:12Z
last_transition_id: wallet-ledger-entry:1842
last_transition_hash: sha256:...
last_anchor_hash: sha256:...
```

## Fencing Requirements

Before a new writer emits Class A accepted transitions for a scope, it must prove one of:

- the previous holder is explicitly fenced;
- the previous epoch has expired under the configured lease policy;
- an authority decision supersedes the previous holder;
- a manual operator decision grants emergency authority.

Fencing evidence must be append-only and auditable.

## Conflict States

- `none`: exactly one authority holder for the scope and epoch.
- `no_holder`: no writer currently has authority.
- `conflict`: more than one holder claims the same scope and epoch.
- `stale_heartbeat`: holder exists but heartbeat is stale.
- `fencing_pending`: failover is in progress and writes should pause.
- `emergency_override`: operator or authority decision explicitly granted temporary authority.

## Continuity Readiness Impact

Writer authority conflict means continuity unhealthy, even if all infrastructure checks are green.

Examples:

```text
DB healthy
API healthy
Queue healthy
WriterAuthorityReadiness = conflict
Result: Infrastructure Healthy, Continuity Unhealthy
```

```text
DB unavailable
Standby promoted
Previous writer fenced
Replay verified
Result: Infrastructure Recovering, Continuity Healthy
```

## Compatibility Test

Any move to multi-writer SQL, distributed queues, active-active regions, or new provider settlement workers must answer:

- What is the authority scope?
- Who is the authority holder?
- What is the authority epoch?
- How is the old writer fenced?
- How is conflict detected?
- Which authority decision grants write rights?

If these answers are unavailable, the change is not compatible with the continuity architecture.
