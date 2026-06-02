<?php

namespace Tests\Feature;

use App\Exceptions\DuplicateMutationException;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\Mutation\MutationContext;
use App\Services\Mutation\MutationDedupGuard;
use App\Services\Mutation\MutationIdentityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MutationGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_mutation_identity_is_deterministic(): void
    {
        $resolver = app(MutationIdentityResolver::class);

        $first = $resolver->resolve(
            actor: 'cli:operator',
            action: 'wallet.mint',
            entityType: 'wallet',
            entityId: 10,
            idempotencyKey: 'mint-1',
            context: ['amount' => 100, 'asset' => 'RUBT'],
            mutationPath: 'wallet.mint.cli',
        );
        $second = $resolver->resolve(
            actor: 'cli:operator',
            action: 'wallet.mint',
            entityType: 'wallet',
            entityId: 10,
            idempotencyKey: 'mint-1',
            context: ['asset' => 'RUBT', 'amount' => 100],
            mutationPath: 'wallet.mint.cli',
        );

        $this->assertSame($first['mutation_id'], $second['mutation_id']);
    }

    public function test_dedup_guard_rejects_duplicate_in_enforce_mode(): void
    {
        $identity = app(MutationIdentityResolver::class)->resolve(
            actor: 'job:retry',
            action: 'payment.retry',
            entityType: 'order_item',
            entityId: 1,
            idempotencyKey: 'retry:1',
            mutationPath: 'payment.retry_job',
        );

        app(MutationDedupGuard::class)->check(
            identity: $identity,
            mutationPath: 'payment.retry_job',
            mode: 'enforce',
            guardKey: 'retry:order_item:1',
        );

        $this->expectException(DuplicateMutationException::class);

        app(MutationDedupGuard::class)->check(
            identity: $identity,
            mutationPath: 'payment.retry_job',
            mode: 'enforce',
            guardKey: 'retry:order_item:1',
        );
    }

    public function test_ledger_dedup_guard_blocks_duplicate_append_for_same_mutation(): void
    {
        config(['mutation.ledger_guard_mode' => 'enforce']);

        $identity = app(MutationIdentityResolver::class)->resolve(
            actor: 'test',
            action: 'ledger.test',
            entityType: 'ledger',
            entityId: 'global',
            idempotencyKey: 'ledger-test-1',
            mutationPath: 'ledger.test',
        );

        MutationContext::bind($identity, function (): void {
            app(LedgerService::class)->recordGlobal('MUTATION_GUARD_TEST', null, ['amount' => 1]);
        });

        $this->expectException(DuplicateMutationException::class);

        MutationContext::bind($identity, function (): void {
            app(LedgerService::class)->recordGlobal('MUTATION_GUARD_TEST', null, ['amount' => 1]);
        });
    }

    public function test_wallet_mint_cli_rejects_duplicate_operator_action(): void
    {
        config([
            'mutation.cli_guard_mode' => 'enforce',
            'mutation.ledger_guard_mode' => 'shadow',
        ]);

        $user = User::factory()->create(['email' => 'buyer@example.test']);

        $first = Artisan::call('wallet:mint', [
            'user' => (string) $user->id,
            'amount' => '10',
            '--operator' => 'test-operator',
            '--idempotency-key' => 'mint-duplicate-test',
        ]);
        $second = Artisan::call('wallet:mint', [
            'user' => (string) $user->id,
            'amount' => '10',
            '--operator' => 'test-operator',
            '--idempotency-key' => 'mint-duplicate-test',
        ]);

        $this->assertSame(0, $first);
        $this->assertSame(1, $second);
    }
}
