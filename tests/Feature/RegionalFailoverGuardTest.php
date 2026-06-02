<?php

namespace Tests\Feature;

use App\Exceptions\WriterAuthorityException;
use App\Services\Continuity\WriterAuthorityGuard;
use App\Services\Mutation\MutationDedupGuard;
use App\Services\Mutation\MutationIdentityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RegionalFailoverGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_writer_guard_rejects_stale_region_in_enforce_mode(): void
    {
        config([
            'mutation.region' => 'br',
            'mutation.writer_region' => 'eu',
            'mutation.writer_epoch' => '2',
            'mutation.writer_guard_mode' => 'enforce',
        ]);

        $identity = app(MutationIdentityResolver::class)->resolve(
            actor: 'http:test',
            action: 'order.checkout',
            entityType: 'order',
            entityId: 1,
            idempotencyKey: 'checkout-1',
            mutationPath: 'order.checkout',
        );

        $this->expectException(WriterAuthorityException::class);

        app(MutationDedupGuard::class)->check(
            identity: $identity,
            mutationPath: 'order.checkout',
            mode: 'shadow',
            guardKey: 'test:checkout:1',
        );
    }

    public function test_promoted_region_can_pass_writer_guard(): void
    {
        config([
            'mutation.region' => 'eu',
            'mutation.writer_guard_mode' => 'enforce',
        ]);

        Artisan::call('marketplace:writer-authority:promote', [
            'region' => 'eu',
            'epoch' => '3',
        ]);

        $identity = app(MutationIdentityResolver::class)->resolve(
            actor: 'http:test',
            action: 'order.checkout',
            entityType: 'order',
            entityId: 2,
            idempotencyKey: 'checkout-2',
            mutationPath: 'order.checkout',
        );

        $decision = app(MutationDedupGuard::class)->check(
            identity: $identity,
            mutationPath: 'order.checkout',
            mode: 'shadow',
            guardKey: 'test:checkout:2',
        );

        $this->assertTrue($decision['allowed']);
        $this->assertSame('eu', app(WriterAuthorityGuard::class)->authority()['writer_region']);
        $this->assertSame('3', app(WriterAuthorityGuard::class)->authority()['writer_epoch']);
    }

    public function test_readiness_reports_region_epoch_and_db_writability(): void
    {
        config(['mutation.region' => 'eu']);

        Artisan::call('marketplace:writer-authority:heartbeat', [
            '--region' => 'eu',
            '--epoch' => '4',
            '--json' => true,
        ]);

        $exitCode = Artisan::call('marketplace:db-continuity-readiness', ['--json' => true]);
        $payload = json_decode(trim(Artisan::output()), true);

        $this->assertSame(0, $exitCode);
        $this->assertSame('eu', $payload['current_region']);
        $this->assertSame('eu', $payload['writer_region']);
        $this->assertSame('4', $payload['writer_epoch']);
        $this->assertTrue($payload['db_writable']);
        $this->assertArrayHasKey('failover_allowed', $payload);
    }

    public function test_failover_preflight_requires_target_writer_region(): void
    {
        config(['mutation.region' => 'eu']);

        Artisan::call('marketplace:writer-authority:promote', [
            'region' => 'eu',
            'epoch' => '5',
        ]);

        $go = Artisan::call('marketplace:failover:preflight', [
            '--target-region' => 'eu',
            '--min-confidence' => '0',
            '--json' => true,
        ]);
        $this->assertSame(0, $go);

        $noGo = Artisan::call('marketplace:failover:preflight', [
            '--target-region' => 'br',
            '--min-confidence' => '0',
            '--json' => true,
        ]);
        $this->assertSame(1, $noGo);
    }
}
