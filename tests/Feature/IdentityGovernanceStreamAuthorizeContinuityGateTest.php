<?php

namespace Tests\Feature;

use App\Models\IdentityGovernanceProjectionCache;
use App\Models\IdentityGovernanceStreamEvent;
use App\Models\User;
use App\Services\Identity\Governance\IdentityGovernanceStreamAssertionVerifier;
use App\Services\Identity\Governance\IdentityGovernanceVaultStreamProducer;
use App\Services\Identity\Governance\IdentityGovernanceWebAuthnPayload;
use App\Services\L1IdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelPasskeys\Models\Passkey;
use Tests\TestCase;

/**
 * Architecture completion gate:
 *
 * DELETE EVERYTHING except stream → replay → authorize works
 */
class IdentityGovernanceStreamAuthorizeContinuityGateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function delete_everything_except_stream_rebuilds_authorize_options(): void
    {
        config(['app.domain' => 'localhost']);
        config(['passkeys.relying_party.id' => 'localhost']);

        [$entityAddress, $rawCredentialId] = $this->registerIdentityOnStream();

        IdentityGovernanceProjectionCache::query()->delete();
        Passkey::query()->delete();
        User::query()->delete();

        $this->assertSame(0, IdentityGovernanceProjectionCache::query()->count());
        $this->assertSame(0, Passkey::query()->count());
        $this->assertGreaterThan(0, IdentityGovernanceStreamEvent::query()->where('stream_id', $entityAddress)->count());

        config(['identity_governance.stream_enabled' => true]);
        config(['identity_governance.stream_authorize_enabled' => true]);

        $response = $this->postJson('/api/sl1e/authorize/options', [
            'entityAddress' => $entityAddress,
            'clientId' => 'meanly.test',
            'redirectUri' => 'https://meanly.test/simple-l1/callback',
            'state' => 'continuity-state',
            'nonce' => 'continuity-nonce',
        ]);

        $response->assertOk()
            ->assertJsonPath('entityAddress', $entityAddress)
            ->assertJsonStructure(['flowId', 'options']);

        $allowCredentials = $response->json('options.allowCredentials');
        $this->assertCount(1, $allowCredentials);
        $this->assertSame(
            rtrim(strtr(base64_encode($rawCredentialId), '+/', '-_'), '='),
            $allowCredentials[0]['id'],
        );
    }

    #[Test]
    public function authorize_verify_loads_credentials_from_stream_projection_only(): void
    {
        config(['app.domain' => 'localhost']);
        config(['passkeys.relying_party.id' => 'localhost']);

        [$entityAddress, $rawCredentialId] = $this->registerIdentityOnStream();

        IdentityGovernanceProjectionCache::query()->delete();
        Passkey::query()->delete();
        User::query()->delete();

        $options = $this->postJson('/api/sl1e/authorize/options', [
            'entityAddress' => $entityAddress,
            'clientId' => 'meanly.test',
            'redirectUri' => 'https://meanly.test/simple-l1/callback',
            'state' => 'continuity-state',
            'nonce' => 'continuity-nonce',
        ])->assertOk();

        $flowId = (string) $options->json('flowId');

        $this->mock(IdentityGovernanceStreamAssertionVerifier::class, function ($mock): void {
            $mock->shouldReceive('verify')->once()->andReturn(true);
        });

        $this->assertSame(0, Passkey::query()->count());

        $verify = $this->postJson('/api/sl1e/authorize/verify', [
            'flowId' => $flowId,
            'clientId' => 'meanly.test',
            'redirectUri' => 'https://meanly.test/simple-l1/callback',
            'state' => 'continuity-state',
            'nonce' => 'continuity-nonce',
            'authenticationResponse' => [
                'id' => rtrim(strtr(base64_encode($rawCredentialId), '+/', '-_'), '='),
                'rawId' => rtrim(strtr(base64_encode($rawCredentialId), '+/', '-_'), '='),
                'type' => 'public-key',
                'response' => [
                    'clientDataJSON' => 'e30',
                    'authenticatorData' => 'e30',
                    'signature' => 'e30',
                ],
            ],
        ]);

        $verify->assertOk()
            ->assertJsonPath('active', true)
            ->assertJsonPath('entityAddress', $entityAddress);
    }

    #[Test]
    public function producer_writes_replay_ready_webauthn_material_to_stream(): void
    {
        config(['identity_governance.stream_enabled' => true]);

        $user = User::factory()->create([
            'entity_l1_address' => null,
            'meta' => [],
        ]);
        $passkey = Passkey::factory()->create(['authenticatable_id' => $user->id]);
        $webauthn = IdentityGovernanceWebAuthnPayload::fromPasskey($passkey);

        app(L1IdentityService::class)->bindUserToEntityIdentity($user, $passkey);

        $bound = IdentityGovernanceStreamEvent::query()
            ->where('event_type', 'credential.bound')
            ->firstOrFail();

        $this->assertSame($webauthn['credential_id'], $bound->payload['webauthn']['credential_id'] ?? null);
        $this->assertSame($webauthn['public_key'], $bound->payload['webauthn']['public_key'] ?? null);
        $this->assertSame($webauthn['sign_count'], $bound->payload['webauthn']['sign_count'] ?? null);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function registerIdentityOnStream(): array
    {
        config(['identity_governance.stream_enabled' => true]);

        $user = User::factory()->create([
            'entity_l1_address' => null,
            'meta' => [],
        ]);
        $passkey = Passkey::factory()->create(['authenticatable_id' => $user->id]);
        $rawCredentialId = $passkey->data->publicKeyCredentialId;

        $identity = app(L1IdentityService::class)->bindUserToEntityIdentity($user, $passkey);

        return [strtolower($identity['entity_l1_address']), $rawCredentialId];
    }
}
