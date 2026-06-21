<?php

namespace App\Services;

use App\Models\SimpleL1IdentityKey;
use App\Models\User;

class SimpleL1EntityKeyRegistryService
{
    public function entityForKey(?string $keyAddress): ?string
    {
        $keyAddress = $this->normalizeKeyAddress($keyAddress);
        if ($keyAddress === null) {
            return null;
        }

        $record = SimpleL1IdentityKey::query()
            ->where('key_l1_address', $keyAddress)
            ->whereNull('revoked_at')
            ->first();

        return $record instanceof SimpleL1IdentityKey
            ? strtolower((string) $record->entity_l1_address)
            : null;
    }

    /**
     * Resolve the canonical SL1E for a connect proof.
     *
     * Priority:
     * 1. Existing active key registration (sl1_ -> sl1e_)
     * 2. Existing marketplace user entity (@username or current session user)
     * 3. Proof entity from SL1 provider
     */
    public function resolveCanonicalEntity(
        string $proofEntityAddress,
        ?string $keyAddress,
        ?User $marketplaceUser = null,
    ): string {
        $proofEntityAddress = strtolower(trim($proofEntityAddress));
        abort_unless(
            preg_match('/^sl1e_[a-f0-9]{39}$/', $proofEntityAddress) === 1,
            422,
            'Simple L1 entity address is malformed.',
        );

        $registeredEntity = $this->entityForKey($keyAddress);
        if ($registeredEntity !== null) {
            abort_unless(
                hash_equals($registeredEntity, $proofEntityAddress),
                409,
                'Simple L1 key is already bound to another entity.',
            );

            return $registeredEntity;
        }

        $userEntity = $marketplaceUser?->sovereignIdentityAddress();
        if (is_string($userEntity) && preg_match('/^sl1e_[a-f0-9]{39}$/i', $userEntity) === 1) {
            return strtolower($userEntity);
        }

        return $proofEntityAddress;
    }

    /**
     * @param  array<string, mixed>  $proofResponse
     */
    public function registerKey(
        User $user,
        string $entityAddress,
        ?string $keyAddress,
        array $proofResponse,
        string $enrolledVia = 'wildflow_connect',
    ): ?SimpleL1IdentityKey {
        $entityAddress = strtolower(trim($entityAddress));
        $keyAddress = $this->normalizeKeyAddress($keyAddress);
        if ($keyAddress === null) {
            return null;
        }

        $existing = SimpleL1IdentityKey::query()->where('key_l1_address', $keyAddress)->first();
        if ($existing instanceof SimpleL1IdentityKey
            && ! hash_equals(strtolower((string) $existing->entity_l1_address), $entityAddress)) {
            abort(409, 'Simple L1 key is already registered to a different entity.');
        }

        $publicKey = trim((string) (
            data_get($proofResponse, 'proof.keyPublicKey')
            ?: data_get($proofResponse, 'identity.keyPublicKey')
            ?: ''
        ));

        return SimpleL1IdentityKey::query()->updateOrCreate(
            ['key_l1_address' => $keyAddress],
            [
                'user_id' => $user->id,
                'entity_l1_address' => $entityAddress,
                'key_type' => (string) (data_get($proofResponse, 'proof.keyType') ?: 'webauthn_passkey'),
                'public_key' => $publicKey !== '' ? $publicKey : 'registry:'.$keyAddress,
                'public_key_hash' => hash('sha256', $publicKey !== '' ? $publicKey : $keyAddress),
                'trust_level' => 'device_user_presence',
                'device_name' => (string) (data_get($proofResponse, 'proof.deviceName') ?: 'Passkey'),
                'enrolled_via' => $enrolledVia,
                'first_seen_at' => $existing?->first_seen_at ?? now(),
                'last_used_at' => now(),
                'metadata' => [
                    'proof_type' => data_get($proofResponse, 'proof.type'),
                    'client_id' => data_get($proofResponse, 'proof.clientId'),
                    'username' => data_get($proofResponse, 'identity.username') ?: data_get($proofResponse, 'proof.username'),
                ],
            ],
        );
    }

    private function normalizeKeyAddress(?string $keyAddress): ?string
    {
        $keyAddress = strtolower(trim((string) $keyAddress));

        return preg_match('/^sl1_[a-f0-9]{40}$/', $keyAddress) === 1 ? $keyAddress : null;
    }
}
