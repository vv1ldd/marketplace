# Multi-rail Level 3 v3 Playbook (identity protocol layer)

**Not a wallet feature.** Crypto wallet stops being the product model — it becomes an **internal settlement layer**.

```text
Before v3:  send(value, address)     →  send("0x58db9...", 10 USDC)
After v3:   send(value, identity)   →  send("@alice", 10 USDC)
```

### What exists today (enables alias path)

| Capability | Status |
|------------|--------|
| Identity · Vault · Managed bindings | ✓ |
| Multi-rail ownership graph (v1) | ✓ PASS |
| `ResolveRecipient(@alice)` → capabilities (v3a) | ✓ implemented + tested |
| v3a staging evidence | pending |

System can answer: **«Кто такой @alice и какие instruments принадлежат этому субъекту?»**

### What v3b adds (Identity → Identity payment)

```text
PaymentIntent
    ↓
RoutingDecision
    ↓
SettlementExecution
    ↓
AccountingEvent
```

Not yet built. System knows **whom** to pay — not yet product **execution** between identities.

**Product target (internal details hidden from user):**

```text
@selim_dev → Send 10 USDC → @alice
```

No Polygon / Base / Ethereum / address / gas choice in UX — routing layer decides.

---

## v3a boundary (fixed)

`ResolveRecipient` is **not** wallet lookup. It is the **identity resolution boundary**:

```text
External intent
      |
      v
ResolveRecipient(@alice)
      |
      v
Identity
      |
      v
Ownership graph
      |
      v
Receiving capabilities
      |
      v
Routing candidates
```

**v3a answers first:**

> Это тот же Alice? И какие способы приёма ей принадлежат?

**Only then (v3b):**

> Через какой rail исполнить?

**v3a sends nothing.** Last phase that proves **whom** before **how**.

---

## v3a contract (canonical)

```json
{
  "alias": "@alice",
  "resolved_identity": "identity_alice",
  "receiving_capabilities": [
    {
      "binding_id": "B14",
      "asset": "USDC",
      "network": "base",
      "status": "receive_enabled"
    },
    {
      "binding_id": "B15",
      "asset": "USDC",
      "network": "polygon",
      "status": "receive_enabled"
    }
  ]
}
```

**Key rule:**

```text
binding_id  = ownership evidence
address     = settlement coordinate     (internal — not resolve primary key)
```

Not the other way around. Addresses live in adapters / settlement layer (`binding_id → S`), not as the resolver’s main answer.

Optional `routing_candidates[]` may follow capabilities in the same response.

---

## Principle: instrument lifecycle ≠ identity lifecycle

You may:

- add a rail
- remove a rail
- replace a settlement instrument

But:

```text
Alice ≠ address
Alice = subject with controlled graph
```

```text
Instrument lifecycle:  S1 → S2 → S3  (change)
Identity lifecycle:    Alice → Alice → Alice  (persist)
```

---

## Strongest v3a test

**Start:**

```text
@alice · identity_alice
B14 → Base → S1
B15 → Polygon → S2
```

**Add:**

```text
B16 → Ethereum → S3
```

**After resolve — PASS:**

```text
identity_alice
B14 · B15 · B16
```

**FAIL:**

```text
@alice → new address → new identity assumption
```

Even if a transfer to the new address would work.

### Anti-patterns (FAIL)

```text
alias → cached address → send()     (ENS-over-wallet)
@alice → S_new only
```

---

## Staging drill v3a

**API:** `POST /api/storefront/v1/settlement/resolve-recipient`  
`{ "alias": "@alice" }` · bearer `storefront:vault`. Response: `resolve-recipient` v3a — **no root `address`**.

**Anti-coupling:** `ResolveRecipient != SelectNetwork` — resolver answers *who + what can receive*; routing answers *how to deliver*.

```text
1. Create @alice / identity_alice
2. Attach B14 (Base), B15 (Polygon) — resolve → evidence
3. Attach B16 (Ethereum) — resolve → evidence
4. Compare: identity_id stable; B14/B15 unchanged; B16 additive
5. Optional: logout/re-auth → resolve (same identity)
```

CI reference: `tests/Feature/StorefrontResolveRecipientTest.php`

**Evidence:** copy [`multi-rail-level-3-v3a-TEMPLATE.md`](./multi-rail-level-3-v3a-TEMPLATE.md) → `multi-rail-level-3-v3a-run-YYYY-MM-DD.md`

Capture: `scripts/level3-resolve-recipient-capture.sh @alice`

### Staging evidence shape (additive drill)

```text
BEFORE   @alice → identity X → B14 Base, B15 Polygon
ACTION   Create Ethereum instrument
AFTER    @alice → identity X → B14, B15, B16 Ethereum

PASS: same identity · old bindings unchanged · new capability additive
```

---

## v3b — after v3a PASS

Does **not** start with addresses.

### PaymentIntent

```json
{
  "from_identity": "identity_selim",
  "to_identity": "identity_alice",
  "asset": "USDC",
  "amount": "10"
}
```

### Flow

```text
PaymentIntent
      ↓
ResolveRecipient(to_identity)
      ↓
RoutingDecision
      ↓
SettlementAdapter
      ↓
AccountingEvent
```

### Identity-native accounting

**Product reality:**

```text
selim_dev → alice : 10 USDC
```

**Not primary ledger narrative:**

```text
0x6038… → 0xabc… : Polygon tx
```

Event keys: `sender_identity`, `receiver_identity`, `amount`, `asset`, `route`, `settlement_reference`, `binding_id`.

---

## Full chain (v1 → v3b)

```text
Identity
   ↓
Ownership Graph        (v1)
   ↓
Economic State         (v2)
   ↓
Recipient Resolution   (v3a — boundary)
   ↓
Payment Intent         (v3b)
   ↓
Routing → Settlement → Accounting
```

---

## Artifacts

- [`multi-rail-level-3-v3-TEMPLATE.md`](./multi-rail-level-3-v3-TEMPLATE.md)
- [v1](./multi-rail-level-3-run-2026-06-22.md) · [v2](./multi-rail-level-3-v2-playbook.md)

---

## After v3 PASS

`Send 10 USDC to @alice` = top layer on proven identity protocol — alias is an **identity primitive**, not wallet DNS.
