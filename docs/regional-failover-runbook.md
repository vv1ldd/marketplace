# Regional Failover Runbook

This runbook keeps DB promotion, writer authority, and edge routing separate.

## Current Safe Default

- Single writer region per epoch.
- Warm standby region can read/replay but must not write.
- Edge/DNS routing is the last step, not the failover trigger.
- Readiness JSON is observability evidence, not the authority that promotes regions.

## Preflight

Run these checks before moving traffic:

```bash
php artisan marketplace:db-continuity-readiness --json
php artisan marketplace:verify-balances --json
php artisan marketplace:failover:preflight --target-region="${TARGET_REGION}" --json
```

Required signals:

- `continuity_status` is not `unhealthy`.
- `recovery_confidence` meets the selected threshold.
- `db_writable` is true on the target primary.
- `writer_region` equals the target region.
- `writer_epoch` is known.

## Manual Brazil to EU Failover

1. Freeze Brazil writes if reachable.
2. Promote the EU DB replica to primary through the database provider.
3. Promote writer authority with a monotonic epoch:

```bash
php artisan marketplace:writer-authority:promote eu "${NEXT_EPOCH}" --json
```

4. Publish a heartbeat from EU:

```bash
php artisan marketplace:writer-authority:heartbeat --region=eu --epoch="${NEXT_EPOCH}" --json
```

5. Rebuild and verify core projections:

```bash
php artisan marketplace:rebuild-balances --json
php artisan marketplace:verify-balances --json
```

6. Run preflight:

```bash
php artisan marketplace:failover:preflight --target-region=eu --json
```

7. Switch edge/DNS only after preflight returns `GO`.

## Rollback

Rollback traffic, not ledger truth.

- If preflight fails before edge switch, keep traffic on the current region.
- If edge switch has happened and EU is writer for epoch `N+1`, do not re-enable Brazil writes on epoch `N`.
- To move writes back, perform another controlled promotion with epoch `N+2`.
- Run `marketplace:verify-balances --json` before resuming broad financial mutations.

## Hard Stop Signals

- Duplicate accepted `mutation_id`.
- Ledger verification failure.
- Writer conflict in readiness.
- DB not writable on target primary.
- Retry/webhook guard shows duplicate hard-fail pressure that cannot be explained.
- Balance replay/verify mismatch.

## Operator Notes

- DNS does not decide writer authority.
- DB primary status does not by itself grant writer authority.
- Writer authority without DB writability is not safe.
- Readiness JSON informs the operator/control plane but does not mutate state.
