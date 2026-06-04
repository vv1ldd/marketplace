<?php

namespace App\Services;

use App\Models\SimpleL1IdentityKey;
use App\Models\User;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SimpleL1IdentityRegistryService
{
    public function assertNativeDirectProofCanAuthenticate(array $proofResponse, ?User $currentUser = null): void
    {
        $proof = data_get($proofResponse, 'proof');
        abort_unless(is_array($proof), 422, 'Simple L1 native proof is missing.');

        $entityAddress = $this->entityAddress($proof);
        $keyAddress = $this->keyAddress($proof);
        $publicKey = $this->publicKey($proof);
        $requestHost = $this->requestHost($proof);
        $clientId = (string) data_get($proof, 'clientId', '');
        $redirectHost = parse_url((string) data_get($proof, 'redirectUri', ''), PHP_URL_HOST);
        $expectedKeyAddress = app(L1IdentityService::class)->keyAddressFromPublicKey($publicKey);

        abort_unless(hash_equals($expectedKeyAddress, $keyAddress), 403, 'Simple L1 native key address mismatch.');
        abort_unless(is_string($redirectHost) && hash_equals($requestHost, Str::lower($redirectHost)), 403, 'Simple L1 native request host mismatch.');

        $registeredKey = SimpleL1IdentityKey::query()
            ->where('key_l1_address', $keyAddress)
            ->first();

        if ($registeredKey) {
            abort_if($registeredKey->revoked_at !== null, 403, 'Simple L1 native key is revoked.');
            abort_unless(hash_equals($registeredKey->entity_l1_address, $entityAddress), 403, 'Simple L1 native key is bound to another entity.');
            $this->assertRegisteredKeyAllowsRequest($registeredKey, $requestHost, $clientId);

            return;
        }

        $existingEntityUser = User::findByEntityL1Address($entityAddress);
        if ($existingEntityUser instanceof User) {
            abort_unless(
                $currentUser instanceof User && (int) $currentUser->id === (int) $existingEntityUser->id,
                403,
                'Simple L1 native key is not enrolled for this entity.'
            );

            return;
        }

        abort_unless(
            hash_equals($this->bootstrapEntityAddressForPublicKey($publicKey), $entityAddress),
            403,
            'Simple L1 native entity bootstrap mismatch.'
        );
    }

    public function recordNativeDirectProof(array $proofResponse, User $user, ?array $verificationResult = null): SimpleL1IdentityKey
    {
        $proof = data_get($proofResponse, 'proof');
        if (! is_array($proof)) {
            throw new HttpException(422, 'Simple L1 native proof is missing.');
        }

        $entityAddress = $this->entityAddress($proof);
        $keyAddress = $this->keyAddress($proof);
        $publicKey = $this->publicKey($proof);
        $requestHost = $this->requestHost($proof);
        $clientId = (string) data_get($proof, 'clientId', '');
        $now = now();
        $metadata = [
            'signature_algorithm' => data_get($proof, 'signatureAlgorithm'),
            'proof_type' => data_get($proof, 'type'),
            'policy_version' => data_get($proof, 'policyVersion'),
            'last_routing_decision_id' => data_get($proof, 'routingDecisionId'),
            'last_routing_decision' => is_array(data_get($proof, 'routingDecision')) ? data_get($proof, 'routingDecision') : null,
            'last_client_id' => data_get($proof, 'clientId'),
            'allowed_relying_parties' => [$requestHost],
            'allowed_clients' => $clientId !== '' ? [$clientId] : [],
        ];
        if ($verificationResult !== null) {
            $metadata['last_verification_result_id'] = data_get($verificationResult, 'verification_result_id');
            $metadata['last_verification_result'] = $verificationResult;
        }

        $record = SimpleL1IdentityKey::query()->updateOrCreate(
            ['key_l1_address' => $keyAddress],
            [
                'user_id' => $user->id,
                'entity_l1_address' => $entityAddress,
                'key_type' => (string) data_get($proof, 'keyType', 'native_macos_p256'),
                'public_key' => $publicKey,
                'public_key_hash' => hash('sha256', $publicKey),
                'trust_level' => 'device_user_presence',
                'device_name' => (string) data_get($proof, 'deviceName', 'macOS Native Agent'),
                'enrolled_via' => User::findByEntityL1Address($entityAddress)?->id === $user->id
                    ? 'native_direct_login'
                    : 'native_direct_bootstrap',
                'first_seen_at' => SimpleL1IdentityKey::query()->where('key_l1_address', $keyAddress)->value('first_seen_at') ?: $now,
                'last_used_at' => $now,
                'metadata' => $metadata,
            ],
        );

        $this->syncUserPrimaryIdentity($user, $entityAddress, $keyAddress);

        return $record;
    }

    public function bootstrapEntityAddressForPublicKey(string $publicKey): string
    {
        return 'sl1e_'.substr(hash('sha256', 'simple-l1:v1:entity:native:'.$publicKey), 0, 39);
    }

    private function syncUserPrimaryIdentity(User $user, string $entityAddress, string $keyAddress): void
    {
        $meta = $user->meta ?? [];
        $meta['entity_l1_address'] = $entityAddress;
        $meta['key_l1_address'] = $keyAddress;
        $meta['simple_l1'] = array_merge($meta['simple_l1'] ?? [], [
            'protocol' => 'simple-l1',
            'identity_rule' => 'entity_with_registered_keys',
            'native_key_registered_at' => now()->toIso8601String(),
        ]);

        $user->forceFill([
            'entity_l1_address' => $entityAddress,
            'key_l1_address' => $keyAddress,
            'identity_provider' => 'identity_wildflow',
            'meta' => $meta,
        ])->save();
    }

    private function entityAddress(array $proof): string
    {
        $value = Str::lower((string) data_get($proof, 'entityAddress', ''));
        abort_unless(preg_match('/^sl1e_[a-f0-9]{39}$/', $value) === 1, 422, 'Simple L1 entity address is malformed.');

        return $value;
    }

    private function keyAddress(array $proof): string
    {
        $value = Str::lower((string) data_get($proof, 'keyAddress', ''));
        abort_unless(preg_match('/^sl1_[a-f0-9]{40}$/', $value) === 1, 422, 'Simple L1 key address is malformed.');

        return $value;
    }

    private function publicKey(array $proof): string
    {
        $value = trim((string) data_get($proof, 'keyPublicKey', ''));
        abort_unless(str_starts_with($value, 'base64url:'), 422, 'Simple L1 native public key is malformed.');

        return $value;
    }

    private function requestHost(array $proof): string
    {
        $value = Str::lower(trim((string) data_get($proof, 'requestHost', '')));
        abort_unless($value !== '' && filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME), 422, 'Simple L1 native request host is malformed.');

        return $value;
    }

    private function assertRegisteredKeyAllowsRequest(SimpleL1IdentityKey $key, string $requestHost, string $clientId): void
    {
        $metadata = is_array($key->metadata) ? $key->metadata : [];
        $allowedHosts = array_map('strtolower', array_filter((array) ($metadata['allowed_relying_parties'] ?? []), 'is_string'));
        $allowedClients = array_filter((array) ($metadata['allowed_clients'] ?? []), 'is_string');

        abort_unless(
            in_array($requestHost, $allowedHosts, true) || ($clientId !== '' && in_array($clientId, $allowedClients, true)),
            403,
            'Simple L1 native key is not eligible for this relying party.'
        );
    }
}
