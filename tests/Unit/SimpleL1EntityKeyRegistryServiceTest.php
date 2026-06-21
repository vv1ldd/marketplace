<?php

namespace Tests\Unit;

use App\Models\SimpleL1IdentityKey;
use App\Models\User;
use App\Services\SimpleL1EntityKeyRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpleL1EntityKeyRegistryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_key_resolves_canonical_entity(): void
    {
        $entity = 'sl1e_'.str_repeat('a', 39);
        $key = 'sl1_'.str_repeat('b', 40);

        SimpleL1IdentityKey::query()->create([
            'user_id' => User::factory()->create()->id,
            'entity_l1_address' => $entity,
            'key_l1_address' => $key,
            'public_key' => 'base64url:test',
            'public_key_hash' => hash('sha256', 'test'),
        ]);

        $service = app(SimpleL1EntityKeyRegistryService::class);

        $this->assertSame($entity, $service->entityForKey($key));
        $this->assertSame(
            $entity,
            $service->resolveCanonicalEntity($entity, $key, null),
        );
    }

    public function test_existing_marketplace_user_entity_wins_over_new_proof_entity(): void
    {
        $existingEntity = 'sl1e_'.str_repeat('c', 39);
        $proofEntity = 'sl1e_'.str_repeat('d', 39);
        $key = 'sl1_'.str_repeat('e', 40);

        $user = User::factory()->create([
            'username' => 'selim_dev',
            'username_key' => 'selim_dev',
            'entity_l1_address' => $existingEntity,
        ]);

        $resolved = app(SimpleL1EntityKeyRegistryService::class)->resolveCanonicalEntity(
            $proofEntity,
            $key,
            $user,
        );

        $this->assertSame($existingEntity, $resolved);
    }

    public function test_register_key_rejects_conflicting_entity_binding(): void
    {
        $entityA = 'sl1e_'.str_repeat('f', 39);
        $entityB = 'sl1e_'.str_repeat('9', 39);
        $key = 'sl1_'.str_repeat('8', 40);
        $user = User::factory()->create(['entity_l1_address' => $entityA]);

        SimpleL1IdentityKey::query()->create([
            'user_id' => $user->id,
            'entity_l1_address' => $entityA,
            'key_l1_address' => $key,
            'public_key' => 'base64url:test',
            'public_key_hash' => hash('sha256', 'test'),
        ]);

        $this->expectExceptionMessage('Simple L1 key is already registered to a different entity.');

        app(SimpleL1EntityKeyRegistryService::class)->registerKey(
            $user,
            $entityB,
            $key,
            ['proof' => ['type' => 'sl1e.login.proof.v1']],
        );
    }
}
