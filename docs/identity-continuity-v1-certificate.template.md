# Identity Continuity v1 Certificate

Internal milestone record — **not** a security audit or external certification.

This certificate does **not** claim “the system is secure.” It claims:

> **If derived state is destroyed, can identity be restored from history?**

After pass, the answer is **yes**:

```text
stream → replay → projection → authorize
```

### Proof model (two objects)

Six months later, someone should not ask “where is the proof this worked?” — they find **two
different artifacts**:

| Object | Answers |
|--------|---------|
| **Repository** | What the system **guarantees** (reducers, invariants, CI precursors, templates) |
| **Filled certificate + execution record** | What **happened** when we ran it (env + commit + soak result) |

```text
identity-continuity-v1-certificate.template.md
        ↓  (copy, fill at sign-off)
filled execution record + this certificate
        ↓
specific environment + commit + soak result
```

The launch date is **not** part of the identity system — it lives only in ops records.

```text
Code          → guarantees (reducers, invariants, tests)
Ops execution → proves production behavior (soak record + this certificate)
```

Do not commit a filled certificate until sign-off is complete. Copy this template to e.g.
`docs/records/identity-continuity-v1-<env>-<YYYY-MM-DD>.md` or store in your ops wiki.

---

## Identity Continuity v1

| Field | Value |
|-------|-------|
| **Status** | Operationally Proven |
| **Commit** | `<git SHA>` |
| **Environment** | `<staging \| production>` |
| **Soak start** | `<YYYY-MM-DD HH:MM TZ>` |
| **Soak end** | `<YYYY-MM-DD HH:MM TZ>` |
| **Owner** | `<name / team>` |
| **Soak execution record** | retention runbook §0 (linked or attached) |

### Simple Phone contract (foundation)

```text
Device
  |
  | credential.bound
  v
Identity Stream
  |
  | replay
  v
Current Identity State
```

Not `Phone = identity`. **Phone = active credential / factor.**

**Device lifecycle ≠ identity lifecycle.** Simple Phone may fail, be replaced, get a new secure
element, or add a new passkey — each is `credential.bound(...)` on the **same stream**. Replay
yields: same identity, new factor, continuous history.

Login does not mutate history:

```text
assertion → verify → allow     (authorize ≠ append)
```

New device is a separate historical action:

```text
new device → credential.bound → append event
```

---

## Passed

| Gate | Result |
|------|--------|
| Stream durability | ✓ last committed event survives storage restart |
| Restore from stream | ✓ backup stream only → rebuild → authorize |
| Projection convergence | ✓ drift = 0 for soak window |
| Replay budget | ✓ within SLO (p95 recorded) |
| Authorize after reconstruction | ✓ head unchanged after login |
| 24h soak | ✓ completed without stop conditions |

**Core invariant verified:** `authorize ≠ append` — no unexpected stream append during authorize.

---

## Guarantees (v1 scope)

- ✓ state reconstructible from stream  
- ✓ authorization recoverable after projection loss  
- ✓ projections disposable  
- ✓ schema evolution supported (`schema_version`)  
- ✓ replay measurable (Invariant 13)

---

## Known limitations (not failures — out of v1 scope)

- Recovery policy v1 — producer path not fully productized  
- Guardian — not implemented  
- Root authority — not implemented  
- Phase C persisted snapshots — optional accelerator; not required for v1 pass  
- Legacy identity paths may still exist when stream flags off — document env flag state above

Further work = **new event producers** on the same stream — not a new foundation:

```text
Phase 1 — Identity Continuity v1 (foundation closed after this certificate)
  |
  ├── Simple Phone devices      → credential.bound
  ├── Passkeys                  → credential.bound
  ├── Recovery                  → evidence → policy → event
  ├── Guardians                 → attestation → event
  └── Root authority            → authority.mode_changed
```

Identity is not an “account” — it is a **historical object with reproducible state**. The phone
is a replaceable carrier of participation in identity, not identity itself.

---

## Change policy after this certificate

Core identity **closed**. Accepted:

- producer changes  
- policy changes  
- operational changes  

Not accepted without new evidence from production load:

- new core identity ADRs  
- parallel identity stores  
- hidden roots of trust  

---

## References

- [`identity-continuity-v1-soak-gate.md`](identity-continuity-v1-soak-gate.md)
- [`identity-continuity-v1-retention-replay-runbook.md`](identity-continuity-v1-retention-replay-runbook.md)
- [`governance-reducer-invariants.md`](governance-reducer-invariants.md)
- [`identity-claims-model.md`](identity-claims-model.md)

Signed off by: _________________________ Date: __________
