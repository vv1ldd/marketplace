# ADR 0031: Authority Mesh Membership Lifecycle

**Scope:** This ADR documents the authority-mesh membership model for the
Sovereign node network (`lena`, `lena-1-gcl`, and future peers). It records
the vocabulary, invariants, lifecycle phases, and current baseline **before**
the first admission between hosts. It does not authorize runtime or operational
changes.

Related but distinct decisions:

- [ADR 0028](0028-canonical-webauthn-rp-boundary.md) — WebAuthn RP / issuer /
  ceremony host boundaries for login
- [ADR 0029](0029-connect-origin-ux-boundary.md) — connect-origin UX contract
- [ADR 0030](0030-storefront-login-uses-issuer-par-and-ceremony-origin.md) —
  storefront PAR alignment with issuer ceremony origin

ADR 0031 is **not** a comment on those rollouts. It defines a separate
authority-topology boundary.

## Status

Proposed — **2026-06**

## Context

Two full Simple L1 runtimes now exist and are reachable:

```text
lena         (RU, root)   → simple-l1 + Sovereign Coolify (ops.meanly.one)
lena-1-gcl   (GCP, peer)  → simple-l1 + Sovereign Coolify (ops-gcl.meanly.one)
```

Both hosts can serve identity ceremony, issuer endpoints, and local ledger
state. It is easy to summarize this as "we have two nodes" and lose the
distinction between **runtime existence**, **discovery evidence**,
**membership admission**, **replication verification**, and **failover
validation**.

The Sovereign mesh UI intentionally separates these layers. In the current
`OBSERVE_ONLY` policy mode the projection is locked: remote evidence may be
evaluated without mutating local authority projection. Counters such as
`ADMITTED`, `VERIFIED`, `EVIDENCE`, and `DISCOVERY` may all read zero even when
both runtimes are healthy. That is a **pre-mesh** state, not a broken mesh.

### What is replicated

The replicated object is not a passkey, credential secret, or login session.
Per RFC-0048 (Federated Identity Event Replication), replication targets a
causally ordered graph of **public authority events**:

```text
passkey (private key)
  → never leaves device

binding event
  (entity ↔ controller public key)

ledger event
  (ENTITY_CREATED, CONTROLLER_ADDED, BINDING_ATTESTED, PROOF_OBSERVED, …)

replicated / observed by admitted peers
```

Failover therefore preserves **authority continuity**, not merely service
availability:

```text
Node A dies
  → authority graph survives on admitted peers
  → controller history survives
  → revocations survive
  → entity continuity survives
```

Login may still work through a single surviving runtime, but that is a
consequence of ceremony routing — not the constitutional guarantee. The
guarantee is continuity of the public authority graph.

### Core invariants

```text
authority continuity     ≠  service availability
discovery                ≠  admission
observation              ≠  authority
replication              ≠  promotion
join request             ≠  peer admission
bridge visibility        ≠  peer trust
dns allocation           ≠  peer admission
```

These invariants are already enforced in code and companion decisions:

- Simple L1 runtime: `join_request != peer_admission`,
  `dns_allocation != peer_admission`,
  `bridge_request_visibility != peer_trust`
- Sovereign Coolify ADR-0008 (Observation Does Not Imply Authority):
  evidence stores facts; decisions store authority; health evidence is not
  authority; recovery does not imply promotion
- `Sl1AuthorityPolicyService` in `OBSERVE_ONLY` mode:
  `projection_allowed = false` — remote evidence is evaluated but cannot mutate
  local authority projection

### Architectural safeguard: OBSERVE_ONLY + PROJECTION_LOCKED

In the current policy mode the mesh UI is a **read-only projection**:

```text
remote evidence
  → may be evaluated
  → may advance discovery / evidence / verification counters
  → must not mutate local authority projection
  → must not create membership by observation alone
```

This explains why the appearance of a second runtime does not automatically
create admitted membership. Admission remains a deliberate host-authority
decision.

## Decision question

How should mesh membership be described, staged, and claimed **before** the
first admission between `lena` and `lena-1-gcl`?

## Lifecycle phases

Mesh membership is established through explicit phases. Skipping a phase must
not be described as "mesh complete".

### Phase 0 — Publish node identity

Local runtime exists and publishes a durable node identity (node id, public
runtime endpoint, capability advertisement). Until this phase completes, the
host is a standalone runtime, not a mesh participant.

**Current signal:** `Local runtime: NOT_INITIALIZED`, `Node ID: not published
yet`.

### Phase 1 — Discovery evidence

Bridge-visible or operator-initiated evidence shows that another candidate
runtime exists. Discovery records **existence**, not trust.

**Counter:** `DISCOVERY`

### Phase 2 — Admission decision

Host authority evaluates the candidate and admits or rejects membership.
Admission is local and deliberate. It is not implied by discovery, DNS, join
requests, or health probes.

**Counter:** `ADMITTED`

### Phase 3 — Replication verification

Admitted peers exchange and reconcile public authority events. Replication
success means the authority event graph is observed consistently across admitted
members — not that two login buttons exist.

**Counters:** `EVIDENCE`, `VERIFIED`

Honest claim after Phase 3:

```text
authority continuity is replicated across admitted peers
```

### Phase 4 — Failover validation

Controlled failure or promotion exercise validates that authority continuity and
traffic/control decisions behave as designed under ADR-0008 (observation →
recommendation → decision → control action). Promotion requires an authorized
decision; recovery does not imply promotion.

Honest claim after Phase 4:

```text
failover has been validated under the observation/election model
```

## Current baseline (2026-06)

As of this ADR, the honest statement is:

```text
two runtimes exist
two runtimes are reachable
membership not established
replication not verified
failover not validated
```

Do **not** shorten this to "we have two nodes" without naming the phase.
"Two nodes" is ambiguous and commonly collapses into:

```text
discovered?
admitted?
replicating?
failover-tested?
```

## Relationship to login / connect rollouts

```text
ADR 0028 / 0029 / 0030 rollout
  = identity host boundaries, connect UX, storefront PAR alignment

ADR 0031 mesh lifecycle
  = authority topology vocabulary and admission phases
```

Runtime stabilization and login repair do not imply mesh membership changes.
Mesh admission is a separate workstream and must not be mixed into rollout
fixes, protocol migration, or UX migration.

## Recommendation

Defer all admission actions (`lena` ↔ `lena-1-gcl`) until a dedicated
change-set explicitly targets Phase 1–4 work. Use this ADR as the reference
model for that future work.

When admission work begins, each phase should produce an auditable artifact
(node publication record, discovery evidence package, admission decision,
replication check report, failover exercise report) rather than relying on UI
counters alone.

## Non-decision

This ADR does not:

- admit, reject, or discover any peer
- change `OBSERVE_ONLY` / `PROJECTION_LOCKED` policy mode
- mutate DNS, failover targets, or control-plane actions
- alter Simple L1 runtime behavior on `lena` or `lena-1-gcl`
- lift the current rollout freeze for mesh membership work

It documents vocabulary and phases only, so future admission and failover
decisions can cite an existing model instead of defining it retroactively.
