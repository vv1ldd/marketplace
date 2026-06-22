# Multi-rail Level 3 v3a Evidence Record

| Field | Value |
|-------|-------|
| **Run ID** | |
| **Date (UTC)** | |
| **Environment** | staging |
| **Backend commit** | |
| **Operator** | |
| **Recipient alias** | `@alice` |
| **Prerequisites** | v1 PASS · v2 PASS (recipient) |

---

## Status context

```text
v1  Ownership durability                 PASS (prerequisite)
v2  Economic durability                  PASS (prerequisite)
v3a ResolveRecipient contract            Implemented + Tested
v3a ResolveRecipient evidence            ← this record
v3b PaymentIntent / Routing / Accounting Not started
```

**Proves:** `Alias continuity > instrument continuity` — `@alice` resolves to the **same subject** as instruments are added.

---

## Four invariants (all required for PASS)

| Check | Why |
|-------|-----|
| `identity_id` unchanged | alias remains the subject |
| existing `binding_id` unchanged | no hidden graph reconstruction |
| new `binding_id` additive only | additive ownership model |
| response not address lookup | identity-centric model preserved |

---

## Graph evidence (not «three networks exist»)

### BEFORE

```text
Resolve(@alice)
identity X
B14 Base
B15 Polygon
```

**Capture:**

```bash
./scripts/level3-resolve-recipient-capture.sh @alice | tee /tmp/v3a-resolve-before.log
./scripts/level3-validate-resolve.sh gate /tmp/v3a-resolve-before.log
```

```json

```

| Field | Value |
|-------|-------|
| `identity_id` | |
| B14 `binding_id` | |
| B15 `binding_id` | |
| Root `address`? | NO |

---

### ACTION

```text
Add Ethereum receiving instrument (B16) on @alice vault — additive only
```

---

### AFTER

```text
Resolve(@alice)
identity X
B14 Base
B15 Polygon
B16 Ethereum
```

**Capture:**

```bash
./scripts/level3-resolve-recipient-capture.sh @alice | tee /tmp/v3a-resolve-after.log
./scripts/level3-validate-resolve.sh compare /tmp/v3a-resolve-before.log /tmp/v3a-resolve-after.log
```

```json

```

**Compare output (paste):**

```text

```

---

## Anti-case (must FAIL compare — reference)

Recreated graph — even if networks/addresses look similar:

```text
BEFORE   identity X · B14 · B15
AFTER    identity X · B17 · B18 · B19
```

That is **not** recovery — it is graph replacement.

---

## PASS summary

| Invariant | PASS |
|-----------|------|
| `identity_id` stable | ☐ |
| B14 unchanged | ☐ |
| B15 unchanged | ☐ |
| B16 additive | ☐ |
| No address lookup | ☐ |
| compare script PASS | ☐ |

---

## RESULT

```text
Multi-rail L3 v3a evidence:  PASS | FAIL
Manual repair:               NO | YES
```

After PASS, v3b may rely on:

```text
PaymentIntent → ResolveRecipient → identity + capabilities → RoutingDecision
```

**Approved by:**  
**Date (UTC):**
