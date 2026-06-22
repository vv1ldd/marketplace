# Multi-rail Level 3 v3 Evidence Record

| Field | Value |
|-------|-------|
| **Run ID** | |
| **Date (UTC)** | |
| **Environment** | staging |
| **Backend commit** | |
| **Operator** | |
| **Recipient** | `@alice` |
| **Prerequisites** | v1 + v2 PASS (links) |

**v3a:** `ResolveRecipient` = boundary **Identity Layer → Settlement Intent Layer**. Sends nothing.

**Promise:** `send(value, identity)` — not `send(value, address)`.

**v3a proves:** Alice exists as economic subject + receiving surface. Addresses in `instruments{}` are internal — not resolve root.

---

## Phase v3a — ResolveRecipient (identity resolution boundary)

**Not wallet lookup.** Answers: same Alice? which receive capabilities belong to her?

### API capture

```http
POST /api/storefront/v1/settlement/resolve-recipient
Authorization: Bearer <storefront:vault token>
{ "alias": "@alice" }
```

### Canonical contract (paste responses)

```json

```

Fields required: `alias`, `identity_id`, `ownership.bindings[]` / `receiving_capabilities[]` with `binding_id`. **No root `address` key.**

### Strongest test: B14 + B15 → add B16

| Check | Resolve after B16 | PASS |
|-------|-------------------|------|
| `identity_id` | = identity_alice (stable) | ☐ |
| B14 | unchanged | ☐ |
| B15 | unchanged | ☐ |
| B16 | additive | ☐ |
| NOT `@alice → new address → new identity` | | ☐ |

**Principle:** instrument lifecycle ≠ identity lifecycle

**v3a:** PASS | FAIL

---

## Phase v3b — PaymentIntent (skip if v3a FAIL)

### PaymentIntent

```json
{
  "from_identity": "",
  "to_identity": "alice_identity",
  "asset": "USDC",
  "amount": "10"
}
```

Must **not** use `{ "to": "0x…" }` as primary recipient.

### Routing + settlement

| Field | Value |
|-------|-------|
| capability chosen (binding_id) | |
| network | |
| SettlementAdapter / tx | |

### Accounting (identity-native)

| Narrative | Value |
|-----------|-------|
| Product | e.g. `selim_dev → alice : 10 USDC` |
| sender_identity | |
| receiver_identity | |
| Not primary | `0x6038… → 0xabc… : Polygon tx` |

**v3b:** PASS | FAIL | SKIPPED

---

## RESULT

```text
v3a (boundary, no send):  PASS | FAIL
v3b (intent → settlement): PASS | FAIL | SKIPPED
Multi-rail L3 v3:          PASS | FAIL
```

**Approved by:**  
**Date (UTC):**
