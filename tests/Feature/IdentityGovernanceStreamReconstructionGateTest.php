<?php

namespace Tests\Feature;

use App\Models\IdentityGovernanceProjectionCache;
use App\Models\IdentityGovernanceStreamEvent;
use App\Services\Identity\Governance\GovernanceEventTypes;
use App\Services\Identity\Governance\GovernanceFactor;
use App\Services\Identity\Governance\IdentityGovernanceProjectionCacheStore;
use App\Services\Identity\Governance\IdentityGovernanceProjectionRebuilder;
use App\Services\Identity\Governance\IdentityGovernanceStreamReadModel;
use App\Services\Identity\Governance\IdentityGovernanceStreamWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Gate: can governance provider state be reconstructed from the stream alone?
 *
 * Stream is truth; projection cache is disposable performance layer.
 */
class IdentityGovernanceStreamReconstructionGateTest extends TestCase
{
    use RefreshDatabase;

    private const STREAM = 'sl1e_reconstruction_gate';

    private const FACTOR_A = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';

    private const FACTOR_B = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';

    private const USERNAME = 'alice';

    /** @var array<string, mixed> */
    private const POLICY_PAYLOAD = [
        'version' => 1,
        'rule' => 'all',
        'required_factor_classes' => ['possession'],
        'minimum_independent_dimensions' => 1,
    ];

    #[Test]
    public function provider_state_reconstructs_from_stream_after_projection_cache_destroyed(): void
    {
        $writer = app(IdentityGovernanceStreamWriter::class);
        $cache = app(IdentityGovernanceProjectionCacheStore::class);
        $rebuilder = app(IdentityGovernanceProjectionRebuilder::class);

        $this->seedRealisticStream($writer);

        $live = $writer->read(self::STREAM);

        IdentityGovernanceProjectionCache::query()->delete();
        $this->assertSame(0, IdentityGovernanceProjectionCache::query()->count());
        $this->assertNull($cache->read(self::STREAM));

        $reconstructed = $rebuilder->replayFull(self::STREAM);

        $this->assertTrue($live->equals($reconstructed));
        $this->assertSame(6, IdentityGovernanceStreamEvent::query()->where('stream_id', self::STREAM)->count());

        $this->assertTrue($reconstructed->registry->exists);
        $this->assertSame(self::USERNAME, $reconstructed->registry->username);
        $this->assertSame(
            self::POLICY_PAYLOAD['rule'],
            $reconstructed->governance->currentPolicy?->rule,
        );
        $this->assertSame(
            self::POLICY_PAYLOAD['required_factor_classes'],
            $reconstructed->governance->currentPolicy?->requiredFactorClasses,
        );

        $activeFactorIds = array_column($reconstructed->governance->activeFactors, 'id');
        $this->assertSame([self::FACTOR_B], $activeFactorIds);

        $activeBindingIds = array_column(
            array_filter(
                $reconstructed->registry->bindings,
                static fn (array $binding): bool => ($binding['status'] ?? null) === GovernanceFactor::STATUS_ACTIVE,
            ),
            'factor_id',
        );
        $this->assertSame([self::FACTOR_B], $activeBindingIds);

        $this->assertSame(1, $reconstructed->governance->activeFactorCount);
        $this->assertSame(6, $reconstructed->throughVersion);
    }

    #[Test]
    public function reconstructed_state_supports_authorize_for_active_factor_only(): void
    {
        $writer = app(IdentityGovernanceStreamWriter::class);

        $this->seedRealisticStream($writer);
        $live = $writer->read(self::STREAM);

        IdentityGovernanceProjectionCache::query()->delete();

        $readModel = app(IdentityGovernanceStreamReadModel::class);
        $reconstructed = $readModel->replayFromStreamOnly(self::STREAM);

        $this->assertTrue($live->equals($reconstructed));

        $this->assertTrue($readModel->canAuthorize(self::STREAM, self::FACTOR_B));
        $this->assertFalse($readModel->canAuthorize(self::STREAM, self::FACTOR_A));
    }

    #[Test]
    public function parent_revocation_does_not_invalidate_active_child_factor(): void
    {
        $writer = app(IdentityGovernanceStreamWriter::class);
        $rebuilder = app(IdentityGovernanceProjectionRebuilder::class);

        $this->seedRealisticStream($writer);

        IdentityGovernanceProjectionCache::query()->delete();

        $projection = $rebuilder->replayFull(self::STREAM);

        $this->assertSame([self::FACTOR_B], array_column($projection->governance->activeFactors, 'id'));
        $this->assertTrue($projection->registry->exists);
        $this->assertSame(self::USERNAME, $projection->registry->username);
    }

    private function seedRealisticStream(IdentityGovernanceStreamWriter $writer): void
    {
        $writer->append(
            streamId: self::STREAM,
            expectedVersion: 0,
            eventId: 'gate-genesis',
            eventType: GovernanceEventTypes::IDENTITY_CREATED,
        );

        $writer->append(
            streamId: self::STREAM,
            expectedVersion: 1,
            eventId: 'gate-username',
            eventType: GovernanceEventTypes::IDENTITY_USERNAME_ASSIGNED,
            payload: ['username' => self::USERNAME],
        );

        $writer->append(
            streamId: self::STREAM,
            expectedVersion: 2,
            eventId: 'gate-policy',
            eventType: GovernanceEventTypes::POLICY_DECLARED,
            payload: self::POLICY_PAYLOAD,
        );

        $writer->append(
            streamId: self::STREAM,
            expectedVersion: 3,
            eventId: 'gate-bind-a',
            eventType: GovernanceEventTypes::CREDENTIAL_BOUND,
            payload: [
                'factor_id' => self::FACTOR_A,
                'class' => 'possession',
                'type' => 'passkey',
                'purpose' => 'daily',
            ],
        );

        $writer->append(
            streamId: self::STREAM,
            expectedVersion: 4,
            eventId: 'gate-bind-b',
            eventType: GovernanceEventTypes::CREDENTIAL_BOUND,
            payload: [
                'factor_id' => self::FACTOR_B,
                'class' => 'possession',
                'type' => 'passkey',
                'purpose' => 'daily',
            ],
        );

        $writer->append(
            streamId: self::STREAM,
            expectedVersion: 5,
            eventId: 'gate-revoke-a',
            eventType: GovernanceEventTypes::CREDENTIAL_REVOKED,
            payload: ['factor_id' => self::FACTOR_A],
        );
    }
}
