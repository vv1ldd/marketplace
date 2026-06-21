<?php

namespace Tests\Feature;

use App\Models\IdentityGovernanceStreamEvent;
use App\Services\Identity\Governance\GovernanceEventTypes;
use App\Services\Identity\Governance\IdentityGovernanceProjectionCacheStore;
use App\Services\Identity\Governance\IdentityGovernanceStreamReadModel;
use App\Services\Identity\Governance\IdentityGovernanceStreamWriter;
use App\Services\Identity\Governance\IdentityGovernanceVaultStreamProducer;
use App\Services\L1IdentityService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelPasskeys\Models\Passkey;
use Tests\TestCase;

class IdentityGovernanceFirstRealProducerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['identity_governance.stream_enabled' => true]);
    }

    #[Test]
    public function vault_creation_emits_genesis_username_and_credential_bound_sequence(): void
    {
        $user = User::factory()->create([
            'email' => 'vault-producer@example.test',
            'entity_l1_address' => null,
            'meta' => [],
        ]);
        $passkey = Passkey::factory()->create([
            'authenticatable_id' => $user->id,
            'credential_id' => base64_encode('first-real-producer-credential'),
        ]);

        $identity = app(L1IdentityService::class)->bindUserToEntityIdentity($user, $passkey);
        $streamId = $identity['entity_l1_address'];
        $factorId = IdentityGovernanceVaultStreamProducer::deterministicFactorId('passkey', (string) $passkey->id);

        $events = IdentityGovernanceStreamEvent::query()
            ->where('stream_id', $streamId)
            ->orderBy('version')
            ->get();

        $this->assertCount(3, $events);
        $this->assertSame(GovernanceEventTypes::IDENTITY_CREATED, $events[0]->event_type);
        $this->assertSame(GovernanceEventTypes::IDENTITY_USERNAME_ASSIGNED, $events[1]->event_type);
        $this->assertSame(GovernanceEventTypes::CREDENTIAL_BOUND, $events[2]->event_type);
        $this->assertSame('vault-create:user:'.$user->id.':identity.created', $events[0]->event_id);
        $this->assertSame($user->refresh()->username, $events[1]->payload['username'] ?? null);
        $this->assertSame($factorId, $events[2]->payload['factor_id'] ?? null);

        $projection = app(IdentityGovernanceStreamReadModel::class)->read($streamId);

        $this->assertTrue($projection->registry->exists);
        $this->assertSame($user->username, $projection->registry->username);
        $this->assertCount(1, $projection->registry->bindings);
        $this->assertSame($factorId, $projection->registry->bindings[0]['factor_id'] ?? null);
        $this->assertSame('bronze', $projection->governance->protectionTier);
    }

    #[Test]
    public function create_restart_replay_authorize_from_stream_only(): void
    {
        $user = User::factory()->create([
            'email' => 'vault-restart@example.test',
            'entity_l1_address' => null,
            'meta' => [],
        ]);
        $passkey = Passkey::factory()->create([
            'authenticatable_id' => $user->id,
            'credential_id' => base64_encode('restart-milestone-credential'),
        ]);

        $identity = app(L1IdentityService::class)->bindUserToEntityIdentity($user, $passkey);
        $streamId = $identity['entity_l1_address'];
        $factorId = IdentityGovernanceVaultStreamProducer::deterministicFactorId('passkey', (string) $passkey->id);

        $live = app(IdentityGovernanceStreamReadModel::class)->read($streamId);

        app(IdentityGovernanceProjectionCacheStore::class)->forget($streamId);

        $replayed = app(IdentityGovernanceStreamWriter::class)->replayAfterRestart($streamId);
        $fromStreamOnly = app(IdentityGovernanceStreamReadModel::class)->replayFromStreamOnly($streamId);

        $this->assertTrue($live->equals($replayed));
        $this->assertTrue($live->equals($fromStreamOnly));

        $readModel = app(IdentityGovernanceStreamReadModel::class);

        $this->assertTrue($readModel->canAuthorize($streamId));
        $this->assertTrue($readModel->canAuthorize($streamId, $factorId));
        $this->assertFalse($readModel->canAuthorize($streamId, '00000000-0000-4000-8000-000000000000'));
    }

    #[Test]
    public function vault_creation_is_idempotent_on_retry(): void
    {
        $user = User::factory()->create([
            'email' => 'vault-idempotent@example.test',
            'entity_l1_address' => null,
            'meta' => [],
        ]);
        $passkey = Passkey::factory()->create([
            'authenticatable_id' => $user->id,
            'credential_id' => base64_encode('idempotent-credential'),
        ]);

        $service = app(L1IdentityService::class);
        $first = $service->bindUserToEntityIdentity($user, $passkey);
        $second = $service->bindUserToEntityIdentity($user->refresh(), $passkey);

        $this->assertSame($first['entity_l1_address'], $second['entity_l1_address']);

        $count = IdentityGovernanceStreamEvent::query()
            ->where('stream_id', $first['entity_l1_address'])
            ->count();

        $this->assertSame(3, $count);
    }

    #[Test]
    public function existing_entity_rebind_does_not_emit_new_stream(): void
    {
        $existingEntity = 'sl1e_'.str_repeat('c', 39);

        $user = User::factory()->create([
            'email' => 'existing-entity@example.test',
            'entity_l1_address' => $existingEntity,
            'meta' => ['entity_l1_address' => $existingEntity],
        ]);
        $passkey = Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);

        app(L1IdentityService::class)->bindUserToEntityIdentity($user, $passkey);

        $this->assertSame(
            0,
            IdentityGovernanceStreamEvent::query()->where('stream_id', strtolower($existingEntity))->count(),
        );
    }
}
