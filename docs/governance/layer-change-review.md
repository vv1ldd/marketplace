# Layer Change Review

This document turns the platform's architecture invariants into a practical review policy for product, frontend, API, authority, and ledger changes.

## Meta-Invariant

The closer a layer is to causality, the more stable it must be.

## Escalation Rule

If a change crosses a layer boundary, it requires a higher review class.

If a change crosses a layer boundary, it requires escalation to the review class of the lower layer.

Review class is determined by the deepest layer the change touches, not by the layer where the change started.

## Change Risk Is Layer-Based

Change size does not determine change risk.

A large presentation refactor may have lower risk than a small authority change.

Risk model:

```text
Change Size != Change Risk
Change Risk ~= Causal Proximity
```

The closer a change is to causality, authority, and replay semantics, the higher its review requirements.

## Dependency Rule

Upper layers may depend on lower layers.

Lower layers must not depend on upper layers.

Examples:

```text
Presentation may render authority outcomes.
Authority must not depend on presentation.

Brand may evolve.
Ledger must not care.

Market profiles may vary.
Authority semantics must not vary by market profile.
```

## Review Guidance

Do not ask:

```text
Is this a big change?
```

Ask:

```text
Which layer does this change touch?
Does it cross a layer boundary?
Does it move closer to causality?
```

Examples:

```text
Presentation change
        -> Product Review

Presentation change that affects Capability
        -> Authority Contract Review

Capability change that affects Authority
        -> Architectural Review

Authority change that affects Ledger semantics
        -> Strict Causal Review
```

## Layer Hierarchy

```text
Brand
        -> Presentation
        -> Projection
        -> Capability
        -> Authority
        -> Ledger
```

## Review Matrix

### Brand

Examples:

- Naming
- Marketing copy
- Slogans
- Consumer-facing terminology

Review:

- Brand review

Question:

- Is the language understandable and consistent?

Escalation trigger:

- Brand language starts implying authority, trust, verification, or ledger semantics.

### Presentation

Examples:

- Themes
- Market profiles
- Homepage composition
- Navigation
- Support channel presentation
- Legal copy presentation

Review:

- Product review

Question:

- Does this affect rendering only?

Escalation triggers:

- Affects capability visibility semantics
- Affects authority outcomes
- Turns market profile into authority policy

### Projection

Examples:

- DTO fields
- Storefront API read models
- Projection contracts
- Market Profile DTO

Review:

- Contract review

Question:

- Is this display information or capability?

Escalation triggers:

- DTO begins to imply permissions
- DTO becomes authority-bearing
- Frontend could infer eligibility from projected state

### Capability

Examples:

- Action contracts
- Capability scope
- Capability lifetime
- Capability endpoint shape
- Blocking reason semantics

Review:

- Authority contract review

Question:

- Is this capability authority-issued and scoped?

Escalation triggers:

- Capability semantics change
- Capability affects authority evaluation
- Capability can be minted or upgraded by a non-authority layer

### Authority

Examples:

- Trust evaluation
- Identity binding
- Eligibility rules
- Checkout decisions
- Verification outcomes
- Fulfillment decisions

Review:

- Architectural review

Questions:

- Can this decision be replay-verified?
- Does this preserve authority semantics?
- Does this preserve market-invariant causality?

Escalation triggers:

- Changes causal semantics
- Changes replay outcomes
- Changes identity binding semantics
- Changes trust thresholds or verification rules

### Ledger

Examples:

- Event schema
- Causal history
- Replay rules
- Ledger write semantics
- Event identity or ordering semantics

Review:

- Strict causal review

Questions:

- Is this durable causal history?
- Can replay still reconstruct equivalent truth?
- Is the change backward-compatible with existing causal records?

Escalation triggers:

- Replay incompatibility
- Causal history incompatibility
- Event meaning changes after the fact
- Existing ledger records can no longer be interpreted consistently

## Core Invariant

Presentation may vary by market.

Authority semantics must not vary by presentation.

## Practical Review Questions

UI copy:

```text
Would a buyer understand this?
```

Projection DTO:

```text
Is this display information or capability?
```

Capability:

```text
Is this authority-granted and scoped?
```

Market profile:

```text
Does this affect rendering only?
```

Authority:

```text
Can this decision be replay-verified?
```

Ledger:

```text
Is this durable causal history?
```

## Related Documents

- `docs/adr/0016-market-profile-separation.md`
- `docs/adr/0017-storefront-projection-contract.md`
- `docs/adr/0018-authority-action-contracts.md`
- `docs/adr/0019-capability-contract-semantics.md`
- `docs/adr/0020-replay-verifiable-authority-decisions.md`
- `docs/adr/0021-consumer-brand-and-kernel-language.md`
- `docs/brand/meanly-language-guidelines.md`
