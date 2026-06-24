# Staging evidence records

Fillable artifacts produced **after** staging drills — not design docs.

## Proof sequence status

| Layer | Status |
|-------|--------|
| v1 Ownership durability | PASS — [2026-06-22](./multi-rail-level-3-run-2026-06-22.md) |
| v2 Economic durability | playbook ready (staging pending) |
| v3a ResolveRecipient contract | implemented + tested |
| v3a ResolveRecipient evidence | **pending** — [template](./multi-rail-level-3-v3a-TEMPLATE.md) |
| v3b Payment / routing / accounting | not started |
| Provider Independence Drill | backlog — [playbook](./provider-interface-independence-drills.md) |
| Storefront Independence Drill | backlog — [playbook](./provider-interface-independence-drills.md) |

Gate for **Provider Interface v1** (after Phase 3 link/unlink): both independence drills PASS without identity schema changes.

**v3a four invariants:** `identity_id` stable · existing `binding_id` stable · new binding additive · not address lookup.

Capture: `level3-resolve-recipient-capture.sh` · compare: `level3-validate-resolve.sh compare …`

---

## Accounting model shift

**Crypto default — unit of account:**

```text
Address A  owns  Balance U
```

**Target — unit of account:**

```text
Identity Alice  owns  Economic State U
```

Address is one **provable path** of observation and execution:

```text
Alice → Vault → Binding → Instrument → Address → Observed State
```

**Without v2:** Alice exists · recovered · has instruments.

**After v2:** **This economic state belongs to Alice** — a different class of system.

**v3b** is almost an **accounting** task: *Transfer economic ownership from Alice to Bob* — then infrastructure finds how to execute.

```text
Identity → Ownership → Economic Ownership → Intent → Execution
```

not `Address → Transaction → Hope`.

**Greatest risk is not routing or settlement — it is v2:**

> After surface destruction, is this still the **same economic ownership**, or merely the same address with the same balance?

If v2 is evidence-closed like v1, recipient resolution · payment intents · routing · settlement become **application layers** on proven property.

Then **`Alice owns 10 USDC`** is the system's primary assertion; *10 USDC on address S on network N* is implementation detail.

**«Кому принадлежат эти 10 USDC?»**

| Model | Answer |
|-------|--------|
| Wallet-centric | They are on `0xABC…` |
| Identity-centric | They belong to **Alice** — currently hosted on a settlement instrument |

v2 + v3a change **accounting semantics**, not only UX.

**Architectural breakpoint:** v2 (not v3b). v2 enables: *Economic ownership survives settlement surface changes.* v3b enables payment — but only on top of proven ownership.

**One sentence:** v1 — **Alice survives.** v2 — **Alice's money survives as Alice's money.**

**Observed State** vs **Owned State:** U on S vs U attached to B through durable graph.

**v2 goal:** Prove U is attached to **B**, not merely observable on **S**.

| After | System can say |
|-------|----------------|
| (before v1) | This is Alice |
| **v1** | This is Alice · she has this ownership graph |
| **v2** | This is Alice · **this economic state belongs to Alice** |
| v3a | `@alice` → that subject (not an address) |
| v3b | `Debit(Alice, 10)` · `Credit(Bob, 10)` — not `Send(0x…, 10)` |

**Three terms (usually conflated):**

```text
Identity         = who     (Alice)
Instrument       = where   (Polygon Safe)
Economic State   = what    (10 USDC)
```

**«Принадлежит Alice»** means: through `Identity → Vault → Binding → Instrument → Observed U` — not «who controls the key».

**Evolution of accounting unit:**

```text
Address-centric → Identity-centric → Ownership-centric → Intent-centric
```

v2 sits at the center: it links **identity** to **economic reality**. Without v2, `@alice` is a pretty name. With v2, it references a real economic subject — and v3a/v3b build on that.

### Evolution of the unit of trust

| Typical crypto | This line |
|----------------|-----------|
| **Address** (center) | trust unit **shifts** per layer |
| Address + Alias / Recovery / AA — still address-centric | |

```text
v1   Identity
v2   Economic Ownership
v3a  Recipient Identity
v3b  Payment Intent
```

**Balance check is not v2.** It only proves `Instrument S observes U`. **v2** must prove `Alice owns U` through the **same graph** that survived v1 recovery.

**Proof order (internally consistent):**

```text
v1  Prove subject continuity
 ↓
v2  Prove economic ownership continuity
 ↓
v3a Prove recipient identity continuity
 ↓
v3b Execute transfer of economic ownership
```

**v3b object:** contract between two identity graphs — `Debit(Alice)` · `Credit(Bob)` — Polygon/Base/Ethereum = chosen execution path only.

### Primary fact vs low-level observation

| Model | «Base fact» |
|-------|-------------|
| Address-centric | `0xABC owns 10 USDC` |
| Identity-centric | **`Alice owns 10 USDC`** |

Low-level observation (not primary):

```text
10 USDC observed on instrument S
```

Proof chain for the primary fact:

```text
Alice → Identity → Vault → Binding → Instrument → Observed Economic State
```

**v2 introduces a new primary accounting object:** `Economic Ownership` — not `Address Balance`.

### Ledger language (target after v2)

**Blockchain-native (secondary detail):**

```text
Transfer: 0xAAA → 0xBBB · 10 USDC · Polygon
```

**Identity-centric (primary event):**

```text
Debit:  Alice · 10 USDC
Credit: Bob   · 10 USDC

Executed via: Polygon · instrument B14 · settlement S14   ← execution detail
```

Network becomes **explanation of execution**, not the event itself.

### Stage names (ownership model, not wallet evolution)

| Stage | Continuity / action | Question |
|-------|---------------------|----------|
| v1 | continuity of **ownership** | Who owns? |
| v2 | continuity of **economic ownership** | What belongs to them? |
| v3a | continuity of **recipient identity** | Who is the recipient? |
| v3b | **transfer** of economic ownership | Transfer ownership. |

```text
Who owns? → What belongs? → Who is the recipient? → Transfer ownership.
```

Not: wallet recovery → balance recovery → send money.

**v3b** completes the proof model — not «add transfers». First experiment: move value **between two identity graphs**; Polygon/Base/Ethereum = transport layer only.

**PaymentIntent (accounting intent):**

```text
Debit(Alice, 10 USDC) · Credit(Bob, 10 USDC)
        ↓
How to execute? → RoutingDecision → SettlementAdapter → Execution
```

Crypto: `0xAlice → 0xBob` · This model: **`Alice → Bob`** (addresses = correspondent account numbers).

---

## Proof invariants (separate from implementation)

Each layer must be **evidence-closed** before the next relies on it. Order: **v1 → v2 → v3a → v3b**.

### After v1 (PASS)

```text
Identity → Binding → Settlement Instrument
```

**Proven:** a durable ownership subject exists.

### After v2 (target)

```text
Identity → Binding → Settlement Instrument → Economic State
```

**Must prove:** funds belong to the **subject** through the ownership graph — not to an address as identity.

**Most important step in the chain.**

### After v3a (contract ✓ · evidence pending)

```text
Alias → Identity          ✓
Alias → Address           ✗
```

**Must prove:** alias references a subject, not a coordinate.

### After v3b (future)

```text
Sender Identity → Payment Intent → Recipient Identity → Routing → Settlement → Accounting
```

**Must prove:** value moves **between subjects** via their graphs.

---

## Proof sequence (do not conflate)

Each variant is a **separate hypothesis** with its own language and PASS criteria.

**Build order (inverted vs typical crypto):**

```text
Most systems:  Send → Address → Transaction → (+ recovery, aliases, AA…)
This line:     Identity → Ownership → Economic Ownership → Recipient → Transfer
```

Each layer answers a question the previous could not prove:

| | Question |
|---|----------|
| v1 | Кто владелец? |
| v2 | Что ему принадлежит? |
| v3a | Кому мы собираемся отправить? |
| v3b | Как передать ценность этому субъекту? |

**Before v3b** the system only establishes facts (Alice exists · owns instruments · owns economic state · `@alice` → Alice). **v3b** is the first layer with **payment intent**: *Alice intends to transfer value to Bob.*

**Center of gravity (v3b):**

```text
PaymentIntent → Policy → Routing → Execution
```

not `Wallet → Address → Chain`.

**v3b design question (after v2 + v3a evidence):** not «на какой адрес?» but *какие правила преобразуют намерение Alice заплатить Bob в AccountingEvent и SettlementExecution?* — payment-system language, not wallet language.

```text
v1  Ownership durability     A → V → B → S
v2  Economic durability      A → V → B → S → U
v3  Routing / execution      @alice → … → settlement
```

| Layer | Question | Check order |
|-------|----------|-------------|
| **v1** | Кто владеет? | `A → V → B → S` |
| **v2** | Что принадлежит этому субъекту? | `A → V → B → S → U` (never U-first) |
| **v3** | Как субъект переводит ценность другому? | alias → identity graph → execution |

**v2 = last wallet experiment.** After PASS: **identity-to-identity settlement**, not wallet engineering.

`address continuity ≠ economic continuity` — `S` and `U` can match while `B` is new → FAIL.

---

## Identity-centric settlement (architectural outcome)

**System object:** not a wallet — a **subject with controlled settlement surfaces**.

**Not ENS:** `@alice` is not an alias **for an address**.

```text
alice.eth → address              (lookup)
@alice    → identity → many instruments   (economic subject)
```

Alice can add Ethereum, remove Polygon, create new Base — and **remain Alice**. Instrument lifecycle ≠ identity lifecycle.

**Real breakpoint:** not Send — the moment the system **stops treating address as the subject**.

| Address-centric | Identity-centric |
|-----------------|------------------|
| `0xABC owns 10 USDC` — name, wallet, recovery are layers on **address** | **`Alice owns 10 USDC`** — address is a **property of state**, not bearer of ownership |

```text
Alice → Ownership Graph → Instrument → Observed State
```

v2 first allows: not «10 USDC on S» but «**10 USDC belongs to Alice**» through proven graph.

**Four questions — transfer only at the end:**

```text
v1  Who is the owner?
v2  What belongs to the owner?
v3a Who is the recipient?
v3b Transfer ownership.          ← payment appears here only
```

**Roadmap shape:** building a **payment protocol**, not evolving a wallet.

```text
Wallet:   Address → Receive → Send
This line: Identity → Ownership → Economic Ownership → Recipient → Payment Intent → Settlement
```

**Product surface (after v3b):** user sees **economic state**, not infrastructure devices:

```text
Assets: USDC 125 · BTC 0.02
Activity: Sent 10 USDC to @alice · Received 25 from @bob
```

not Polygon `0x08f32…` / Ethereum `0x58db9…` per screen.

Address ceasing to be the primary object may matter more than the send screen itself.

### Trust anchor moved

**Wallet-centric:**

```text
key → address → funds
```

**Identity-centric:**

```text
identity → ownership graph → settlement instruments → economic state → execution
```

**Core property:** `instrument can change` · `identity persists`

Three Safes are not three users:

```text
identity
   ├── instrument A   (e.g. Polygon)
   ├── instrument B   (e.g. Ethereum)
   └── instrument C   (e.g. Base)
```

### Coordinate vs owner

```text
address is a coordinate     →  where does state live?
identity is the owner       →  whom does that state belong to?
```

**Invariants:**

```text
same identity  ≠  same address
same address   ≠  same ownership
same ownership =  same durable graph
```

### Alias layer (must stay thin)

**Wrong** — alias becomes address:

```text
@alice → 0x1234
```

**Right** — alias resolves to graph:

```text
@alice → identity → ownership graph → available routes → execution
```

Rail changes do not change the person. **v3 must not break v1/v2.**

### Product shift

**Today:** «Send me USDC — here is my Polygon address»

**Tomorrow:** «Send me 10 USDC» — system knows recipient, instruments, optimal rail, settlement.

Users work with **subjects**; system works with **routes**.

### Layer stack

```text
Identity Layer
    ↓
Trust Layer         (ownership graph — v1/v2 proofs)
    ↓
Economic Layer      (observed state — v2)
    ↓
Routing Layer       (v3)
    ↓
Settlement Layer    (SettlementAdapter: Polygon, Ethereum, Base, BTC, …)
```

Not: `Wallet → Network → Address`

**Wallet / Safe** = adapter — not the product face.

### What multi-rail v2 actually proves

Not: «Safe on three networks».

But: **the person exists before their wallets** — identity and ownership graph precede any settlement surface. That is what makes v3 possible.

**Anti-test (FAIL, green UI):** `A → V → new B → same S → same U`

**Correct:** `A → V → same B → same S → same observation path`

---

## Evidence artifacts

| Proof | Artifact pattern | Status |
|-------|------------------|--------|
| Single-rail L3 | `level-3-run-YYYY-MM-DD.md` | PASS — [2026-06-22](./level-3-run-2026-06-22.md) |
| Multi-rail L3 **v1** (ownership) | `multi-rail-level-3-run-YYYY-MM-DD.md` | PASS — [2026-06-22](./multi-rail-level-3-run-2026-06-22.md) |
| Multi-rail L3 **v2** (economic) | `multi-rail-level-3-v2-run-YYYY-MM-DD.md` | playbook ready |
| Multi-rail L3 **v3a** (resolve boundary) | `multi-rail-level-3-v3a-run-YYYY-MM-DD.md` | API ready · [template](./multi-rail-level-3-v3a-TEMPLATE.md) |
| Multi-rail L3 **v3b** (payment) | `multi-rail-level-3-v3-run-YYYY-MM-DD.md` | [playbook](./multi-rail-level-3-v3-playbook.md) · not started |

---

## Single-rail Level 3

1. Read [`level-3-run-playbook.md`](level-3-run-playbook.md)
2. Copy [`level-3-run-TEMPLATE.md`](level-3-run-TEMPLATE.md) → `level-3-run-YYYY-MM-DD.md`
3. `scripts/level3-evidence-capture.sh before` → drill → `after` → `db`
4. Fill MATCH CHECK + RESULT (redact tokens)

**Runner:** `scripts/level3-run-staging.sh` · **Template:** `level-3-run-TEMPLATE.md` · **Sheet:** [`managed-wallet-v0-level-3-execution-sheet.md`](../managed-wallet-v0-level-3-execution-sheet.md)

---

## Multi-rail Level 3

**v1 (CLOSED):** [evidence](./multi-rail-level-3-run-2026-06-22.md)

**v2 (next):** [`multi-rail-level-3-v2-playbook.md`](./multi-rail-level-3-v2-playbook.md) · [`multi-rail-level-3-v2-TEMPLATE.md`](./multi-rail-level-3-v2-TEMPLATE.md)

```text
Fund → BEFORE → GRAPH + OBSERVATION GATE → surface drill → AFTER → compare
```

`LEVEL3_REQUIRE_OBSERVATION=1 ./scripts/level3-validate-graph.sh gate|compare …`

**v3a (next evidence):** [`multi-rail-level-3-v3a-TEMPLATE.md`](./multi-rail-level-3-v3a-TEMPLATE.md) · capture `scripts/level3-resolve-recipient-capture.sh`

Additive drill: B14+B15 → resolve → +B16 → resolve → compare (`identity_id` stable).

**v3b:** payment intent + routing — after v3a PASS · [`multi-rail-level-3-v3-playbook.md`](./multi-rail-level-3-v3-playbook.md)
