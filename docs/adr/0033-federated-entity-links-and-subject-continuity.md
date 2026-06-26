# ADR 0033: Federated Entity Links and Subject Continuity

**Scope:** Ontology and architectural vocabulary for subject continuity across
sovereign authority contours. This ADR does not define runtime behavior, event
schemas, protocols, or implementation details.

Extends the architectural model established by:

- RFC-0048 (Federated Identity Event Replication)
- [ADR 0031](0031-authority-mesh-membership-lifecycle.md) — Authority Mesh
  Membership Lifecycle
- [ADR 0032](0032-sovereign-identity-infrastructure-split.md) — Sovereign
  Identity Infrastructure Split

```text
RFC-0048
    ↓
ADR-0031  how authorities relate
    ↓
ADR-0032  where authority lives
    ↓
ADR-0033  how continuity exists across authorities
```

## Status

Proposed — **2026-06**

Architectural direction only. No runtime implementation is implied by this ADR.

## Context

[ADR 0032](0032-sovereign-identity-infrastructure-split.md) established sovereign
authority contours. After the sovereign split, each contour owns its own:

- ledger
- state root
- rp_id
- controller bindings
- authority policies

As a consequence:

```text
identity.meanly.ru  -> entity_ru
identity.meanly.one -> entity_one
```

represent two authority-local entities.

This is not a synchronization failure. It is the expected consequence of
sovereign authority boundaries. The missing capability is not entity
synchronization but subject continuity across authorities. This ADR defines the
conceptual model for that continuity.

## Vocabulary

**Human**

A real-world person. Humans are outside the scope of SL1 and are not directly
modeled by the protocol.

**Subject**

An inferred continuity relationship across authority-local entities. A subject
is not a ledger object.

**Entity**

An authority-local identity assertion. Entities exist within the authority that
created them.

**Controller Binding**

A realm-local proof that a controller may act for an entity. Controller
bindings are tied to authority-local WebAuthn realms and rp_id boundaries.

**Relationship Evidence**

Attestable evidence describing a relationship between authority-local entities.
Relationship evidence may be exchanged across authorities.

**Policy Acceptance**

A local authority decision regarding relationship evidence. Acceptance is
authority-local and policy-dependent.

## Two Planes

### Authority Mesh

Concerned with:

- admission
- replication
- delegation
- failover
- federation membership
- authority continuity

Authority Mesh is defined by
[ADR 0031](0031-authority-mesh-membership-lifecycle.md). Authority Mesh
membership requires authority-level participation and admission decisions.

### Subject Continuity Mesh

Concerned with:

- relationship evidence
- continuity assessments
- assurance levels
- continuity acceptance policies

Subject Continuity Mesh operates independently from Authority Mesh membership.
Relationship evidence may exist between entities whose authorities do not share
admission, replication, or federation membership.

## Decision

SL1 models:

- entities
- controller bindings
- relationship evidence

SL1 does not model a global subject object.

Subject continuity emerges from policy evaluation of federated relationship
evidence.

Federation does not create a global account. Federation exchanges and evaluates
relationship evidence between authority-local entities.

## Invariants

1. **Human is outside the system.** SL1 does not directly model humans.

2. **Entity is authority-local.** Entities belong to the authority that created
   them.

3. **Subject is not a ledger object.** Subject continuity is inferred rather
   than stored as a canonical object.

4. **Federation evaluates relationship evidence, not entity equality.**
   Federation does not establish entity equality. Federation establishes and
   evaluates relationship evidence.

5. **Subject continuity is policy-dependent.** Different authorities may evaluate
   the same evidence differently.

6. **Relationship evidence does not imply relationship acceptance.**
   Observation does not imply authority. Relationship evidence does not imply
   acceptance.

7. **Subject continuity is assessment, not consensus.** Authorities may
   disagree about continuity while remaining internally consistent. No global
   consensus is required.

8. **No entity is canonical for the subject.** There is no primary entity,
   merged entity, or global identity root.

9. **Subject Continuity Mesh does not require Authority Mesh membership.**
   Relationship evidence may be exchanged and evaluated without shared admission,
   replication, or federation membership.

## Epistemological Continuity

[ADR 0031](0031-authority-mesh-membership-lifecycle.md) established:

```text
observation does not imply authority
```

This ADR extends that principle:

```text
relationship evidence does not imply relationship acceptance
subject continuity does not imply consensus
```

Continuity is a policy outcome rather than a globally enforced fact.

## Non-decision

This ADR intentionally does not define:

- `ENTITY_LINK_ATTESTED` event schemas
- wire protocols
- assurance taxonomies
- relationship lifecycle mechanics
- recovery procedures
- revocation semantics
- conflict-resolution protocols

Potential future work may be documented in:

- ADR-0034 — Relationship Evidence Lifecycle
- RFC-005x — Relationship Evidence Event Model

This ADR does not change:

- runtime behavior
- source code
- DNS topology
- authority policies
- deployment configuration
- federation membership
