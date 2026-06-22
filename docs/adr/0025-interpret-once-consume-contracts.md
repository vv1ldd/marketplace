# ADR 0025: Interpret Once, Consume Contracts

## Status

Accepted

## Constitutional Scope

This ADR is a **constitutional** document. It does not describe a specific module, payment flow, or technology choice.

Domain ADRs answer: *What is true?* (authority, identity, immutability, replay).

ADR-0025 answers: *Who may interpret truth?* (ownership, boundaries, when a new contract is justified).

It constrains how future architectural decisions may be made. Violations are not style disagreements; they break repository law:

- **History must not be rewritten.**
- **Meaning must not be duplicated.**

### What this ADR does not do

ADR-0025 does not prescribe how to build Treasury, Tax, Compliance, or a new rail. It prescribes what is **forbidden**:

- Do not create a second interpreter.
- Do not reinterpret an existing contract.
- Do not make a projection authoritative.
- Do not make a consumer responsible for meaning.

This is not an implementation guide. It is a boundary on the space of admissible decisions.

Domain ADRs define truths. ADR-0025 defines **allowed transformations of truths**.

**Allowed:** new features, new truths, new contracts with their own interpretation boundary.

**Not allowed:** duplicate meaning.

History diverges slowly when facts are rewritten. Meaning diverges immediately when interpretation is duplicated.

### Burden of proof

Most ADRs read: *Because of X, we chose Y.*

ADR-0025 also works as a **rejection criterion**: *If a proposal violates this, the burden of proof is on the proposal.*

Without it, every new shortcut must be defended against an implicit "why not?"

With it, the default presumption is:

**Existing ownership is correct until proven otherwise.**

A second interpreter is an architectural defect whether or not the code works.

### ADR levels in this repository

```text
Level 1 — Reality        (what is true)
    ADR-0012, 0017, 0019, 0020, …

Level 2 — Constitution     (who may decide; allowed transformations)
    ADR-0025

Level 3 — Implementations (consumers and projections)
    Timeline, Statement, Exports, Treasury, UI, …
```

ADR-0025 does not define truth. It does not implement truth. It defines **rules for handling truth**.

### Ownership defect vs behavioral defect

| Type | When it shows |
|------|----------------|
| Behavioral defect | Runtime (wrong value, failed test) |
| Ownership defect | At merge (duplicate meaning authority) |

Violations of ADR-0025 create **architectural debt**:

```text
Works today.
Changes meaning tomorrow.
```

A second interpreter may pass all tests on day one. Debt exists from the moment of merge because two places can diverge in meaning later.

Append-only redefined data corruption: not only wrong values, but **rewriting history**.

ADR-0025 redefines meaning problems: not only inconsistent results, but **duplicated authority**.

### Design review sequence

Before PHP, React, SQL, caching, or performance:

1. What truth is involved?
2. Who owns that truth?
3. Is ownership changing?
4. Is a new contract being created?
5. Or is existing meaning being reinterpreted?

Technology is secondary to meaning ownership.

### Two axes of integrity

Most systems protect data integrity only (append-only, audit log). Facts can remain perfect while meaning diverges: *same facts, different interpretations.*

| Axis | Protects | Violation |
|------|----------|-----------|
| History | Append-only | Fact rewritten |
| Meaning | Interpret-once | Meaning duplicated |

Systems degrade without a single wrong line when **two correct-looking places both believe they own the answer**.

### Delete-module test

For a new module, ask:

**If I delete this module, does another module already own this meaning?**

- **Yes** → likely projection or duplicate interpreter.
- **No** → may be a legitimate new contract with its own authority boundary.

Example: delete `IdentityStatementService` — `AccountingEvent.entries` still owns accounting truth → statement is projection.

Example: delete `NewRiskDecisionService` — nobody owns risk semantics → may be new authority (if justified).

### Singular authority, plural contracts

**Authority must be singular. Contracts may be plural.**

Plural contracts does **not** mean plural authorities.

```text
One authority
    → many contracts
    → many projections
    → many consumers
```

Good:

```text
AccountingEvent.entries
    ├── Statement
    ├── CSV export
    ├── Tax report
    ├── Treasury view
    └── Customer balance UI
```

Bad (even if formulas match today):

```text
PaymentIntent
    ├── Statement calculates balance
    ├── Treasury calculates balance
    └── Export calculates balance
```

Three interpreters → three truths (`Statement truth`, `Treasury truth`, `Export truth`). Divergence is a matter of time.

**Single-writer principle for meaning:** not one file or one service, but **one interpretation owner** per concern. In data: two writers → conflict. In meaning: two interpreters → semantic conflict.

### Final review algorithm

1. What truth is involved?
2. Who owns that truth?
3. Is this module allowed to interpret it?
4. If not: create a **new contract**, or **consume an existing one**.
5. **Does this introduce a second interpreter?** (decisive)

If step 5 is yes, the defect exists even when tests pass, formulas match, and production works. The failure is structural: duplicated authority can diverge in meaning later.

**Follow-up:** If authority changes tomorrow, should this module receive a new contract, or update as a consumer? Copied meaning → duplication. New contract → evolution.

```text
Many contracts + many interpreters = semantic drift
One authority + many projections   = zero semantic drift
```

ADR-0025 protects not code structure, but **singularity of meaning origin**.

## Context

As the system evolved from wallet-centric flows to identity, payment, accounting, timeline, statement, and UI projections, a recurring architectural pattern emerged.

Multiple components need access to the same underlying reality:

- identity state
- capability state
- payment decisions
- accounting history
- API payloads

Without clear ownership, multiple parts of the system begin interpreting the same source independently. That leads to divergence, duplicated logic, inconsistent explanations, and reconciliation problems.

ADR 0017 defines storefront projection boundaries. ADR 0012 defines storage as non-authority. This ADR generalizes the ownership rule that now appears across payment protocol, accounting projections, and frontend normalization.

## Decision

The repository adopts the following architectural rule:

**Consumers do not interpret reality. Consumers consume contracts.**

Interpretation of a concern must occur exactly once.

The result of interpretation becomes a contract that may be reused by many consumers.

## Ownership Map

| Concern | Interpreter | Contract |
|---------|-------------|----------|
| Capabilities | Capability Evaluation | Capability Matrix |
| Routing | Routing Service | Routing Decision Snapshot |
| Limits | Limit Evaluator | Limit Decision |
| Fees | Fee Quote Service | Fee Decision |
| Accounting Semantics | Accounting | Accounting Events |
| Payment Causality | Timeline Service | Timeline Projection |
| Period Summary | Statement Service | Statement Projection |
| UI State | `VaultWalletContent` | Normalized View Model |

All other components consume these contracts.

## Review Questions

Every architectural change must answer:

1. **Does this change create a second interpreter for an existing concern?**
2. **Is this introducing a new contract, or reinterpreting an existing one?**

If a concern already has an owner, new consumers must reuse the existing contract.

## Examples

### Good

- Timeline consumes accounting and dispute history.
- Statement consumes accounting entries.
- UI consumes normalized view models from `VaultWalletContent`.

### Bad

- Statement recalculates balances from payments.
- Timeline re-evaluates routing decisions.
- Execute re-checks current limits after freeze.
- Presentation components derive business state from raw API payloads.

## Repository Pattern

```text
Mutable Source
    → Interpret Once
    → Contract
    → Projection
    → Consumer
```

### Payment and accounting

```text
Identity State
    → Capability Evaluation
    → Capability Contract
    → Intent Snapshot
    → Settlement
    → Accounting
    → Timeline / Statement
```

### Frontend

```text
Wallet API
    → View Model Normalization (`VaultWalletContent`)
    → Presentation Components
```

See also: `.cursor/rules/ui-state-normalization.mdc`.

## Consequences

### Benefits

- Single ownership of interpretation.
- Stable causality.
- Explainable history.
- Predictable evolution.
- Reduced reconciliation surface.

### Trade-offs

- More explicit boundaries.
- Additional projection layers.
- Greater emphasis on contract design.

## Principle

Architectural constitution (compact):

```text
Protect facts.
Protect meaning.
Authority is singular.
Contracts are plural.
Interpret once.
Consume many times.
```

**Facts have owners. Meaning has owners. Consumers have neither.**

Consumers may display, aggregate per contract, filter, format, and export. They must not answer again: *what does this mean?*

### Stable chain

```text
Reality → Authority → Contract → Projection → Presentation
```

| Transition | Question |
|------------|----------|
| Reality → Authority | What counts as a fact? |
| Authority → Contract | How is that fact explained? |
| Contract → Projection | How is it presented for a specific question? |
| Projection → Presentation | How is it shown to a human? |

### Future domains

Treasury, Compliance, Tax, Risk, Analytics use the same filter:

```text
Need new truth?              → new authority
Need new way to consume?     → new contract / projection
Need to recalculate meaning? → reject
```

Everything else in the repository should follow as consequence, not as local exception. This rule governs how architecture may continue to appear in any future domain.

## Related Invariants

ADR-0025 is a meta-rule. It does not compete with domain ADRs that define what is true (identity ownership, capability semantics, replay, projection contracts). It defines who may interpret that truth.

| Invariant | Protects |
|-----------|----------|
| Append-only | History (facts are not rewritten) |
| Interpret-once | Meaning (facts are not re-derived) |

These are orthogonal constraints:

- Without append-only, historical truth can be lost (`Payment A` later rewritten).
- Without interpret-once, facts may survive while meaning diverges (`Accounting says X`, `Statement says Y`, `Treasury says Z`).

### Two axes

```text
History axis                    Meaning axis
────────────                    ────────────
Authority                       Interpreter
    ↓                               ↓
Append-only facts               Contract
    ↓                               ↓
Immutable history               Consumers
```

Examples:

| Artifact | Axis |
|----------|------|
| PaymentIntent snapshot | History protection |
| AccountingEvent | History protection |
| Capability evaluation | Meaning ownership |
| Statement projection | Meaning consumption |
| UI normalization | Meaning consumption |

### Review compass

**History**

1. Can this mutate an existing fact? If yes → problem.

**Meaning**

2. Does this introduce a second interpreter? If yes → problem.
3. Is this a new contract with its own boundary, or reinterpretation of an existing one?

These questions are domain-agnostic. They apply equally to payments, accounting, treasury, compliance, exports, and frontend.

Together they prevent:

- duplicate interpreters
- live re-evaluation of frozen facts
- projections becoming authority
- presentation becoming business logic

Architectural review should prefer ownership questions over mechanics questions:

- Who owns interpretation of this concern?
- Which contract is the source for this decision?

Not: "Can we recalculate balance here?" or "Can we read payments directly?"
