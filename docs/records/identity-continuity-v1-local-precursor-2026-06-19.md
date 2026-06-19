# Identity Continuity v1 — Local precursor run

**Status** (do not read as production sign-off)

```text
Identity Continuity v1
Local precursor:        PASSED
Production readiness:   PENDING
Certificate:            NOT ISSUED
```

Implementation (local/dev only):

```text
  ✅ stream append contract (event_id idempotency, commit 9b88741)
  ✅ replay reconstruction
  ✅ authorize continuity
  ✅ local restore drill
```

Production (staging — not started):

```text
  ⏳ staging durability
  ⏳ destructive restore
  ⏳ 24h soak
  ⏳ certificate
```

**Not** a filled certificate. This record documents a **local precursor run** only.

| Field | Value |
|-------|-------|
| **Run type** | Local precursor (CI gates + MySQL restore drill) |
| **Date** | 2026-06-19 |
| **Commit** | `9b88741` (event_id contract; pushed) · precursor run on `d5bd0d8` |
| **Environment** | local (`marketplace` MySQL 127.0.0.1) |
| **Migration batch** | identity_governance tables batch 2; event_id widen batch 3 |
| **Owner** | local dev run |

## Flags (runtime for drill)

```env
IDENTITY_GOVERNANCE_STREAM_ENABLED=true        # set at runtime; not yet in .env
IDENTITY_GOVERNANCE_STREAM_AUTHORIZE_ENABLED=true
```

Add both to `.env` before real app traffic / staging soak.

## Results

### 1. Governance test suite

```bash
php artisan test tests/Unit/Governance/ tests/Feature/IdentityGovernance*.php
```

**46 passed** (2351 assertions) — includes chaos soak gate, authorize continuity, restore gates.

### 2. Local restore drill (projection wipe)

Stream: `sl1e_2ac41db041e1c11845bd15b5131ead4ea63f3c5`

| Check | Result |
|-------|--------|
| Events on stream | 3 (genesis → username → credential.bound) |
| Delete projections + passkeys + user row | OK |
| Head unchanged after wipe | 3 → 3 |
| Convergence violations | 0 |
| `can_authorize` after replay | **yes** |
| `authorize/options` HTTP (tinker) | 422 redirectUri policy — not stream; **gate tests pass** (46) |
| Head after authorize attempt | **3** (stream unchanged) |
| Replay budget | full ~0.4ms, ms/1k ~138, within budget |

### 3. Artisan ops checks

```bash
php artisan identity-governance:check-convergence   # All converged
php artisan identity-governance:replay-budget       # OK
```

### 4. Bug found and fixed during run

MySQL migration had `event_id` as `uuid`; producers use deterministic string idempotency keys
(`vault-create:user:N:identity.created`). Fixed in commit `fix(identity-stream): align event_id storage with producer idempotency contract`:
migrations widen to `VARCHAR(255) UNIQUE`, append-boundary guard `IdentityGovernanceStreamEventId`.

## Not done (requires staging)

- [ ] Stream durability check (DB/storage restart)
- [ ] Full destructive restore (backup stream → destroy DB → restore stream only)
- [ ] 24h soak with real traffic
- [ ] Filled [`identity-continuity-v1-certificate.template.md`](../identity-continuity-v1-certificate.template.md)

## Next step

1. Add governance flags to `.env` on staging  
2. Fill retention runbook §0 (Scheduled start, Owner, RPO/RTO)  
3. Run order: durability → restore drill → **24h clock** → certificate
