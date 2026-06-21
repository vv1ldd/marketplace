<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

class MarketplaceIdentityResolver
{
    public function resolveFromEntityAddress(?string $entityAddress): ?User
    {
        return User::findByEntityL1Address($entityAddress);
    }

    /**
     * @param  array<string, mixed>|null  $identity
     */
    public function resolveFromIdentity(?array $identity, ?User $authenticatedUser = null): ?User
    {
        $entityAddress = strtolower(trim((string) (
            data_get($identity, 'entity_l1_address')
            ?: data_get($identity, 'l1_address')
            ?: ''
        )));

        $user = $this->resolveFromEntityAddress($entityAddress);
        if ($user instanceof User) {
            return $user;
        }

        if ($authenticatedUser instanceof User) {
            $authAddress = $authenticatedUser->sovereignIdentityAddress();
            if ($authAddress !== null && ($entityAddress === '' || hash_equals(strtolower($authAddress), $entityAddress))) {
                return $authenticatedUser;
            }
        }

        foreach ($this->usernameCandidatesFromIdentity($identity) as $username) {
            $candidate = User::query()->where('username_key', $username)->first();
            if ($candidate instanceof User) {
                return $this->reconcileRotatedEntity($candidate, $identity);
            }
        }

        return null;
    }

    /**
     * Resolve an existing marketplace user or create a minimal one for an active SL1 identity.
     *
     * @param  array<string, mixed>|null  $identity
     */
    public function ensureUserFromIdentity(?array $identity): ?User
    {
        $user = $this->resolveFromIdentity($identity);
        if ($user instanceof User) {
            return $user;
        }

        $entityAddress = strtolower(trim((string) (
            data_get($identity, 'entity_l1_address')
            ?: data_get($identity, 'l1_address')
            ?: ''
        )));

        if (! preg_match('/^sl1e_[a-f0-9]{39}$/', $entityAddress)) {
            return null;
        }

        $username = null;
        foreach ($this->usernameCandidatesFromIdentity($identity) as $candidate) {
            $username = User::makeUniqueUsername($candidate);
            break;
        }

        $username ??= User::makeUniqueUsername(User::usernameCandidateFromEntityAddress($entityAddress));
        $displayName = trim((string) (
            data_get($identity, 'display_alias')
            ?: data_get($identity, 'alias')
            ?: $username
        ));
        $displayName = ltrim($displayName, '@');
        $profileName = $displayName !== '' ? $displayName : 'Vault';

        try {
            return User::create([
                'first_name' => $profileName,
                'last_name' => 'Wallet',
                'username' => $username,
                'username_key' => $username,
                'entity_l1_address' => $entityAddress,
                'key_l1_address' => data_get($identity, 'key_l1_address'),
                'identity_provider' => 'identity_wildflow',
                'meta' => [
                    'registration_source' => 'storefront_vault',
                    'username' => $username,
                    'display_name' => $profileName,
                ],
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return $this->resolveFromIdentity($identity)
                ?? User::findByEntityL1Address($entityAddress);
        }
    }

    /**
     * @param  array<string, mixed>|null  $identity
     * @return array<int, string>
     */
    private function usernameCandidatesFromIdentity(?array $identity): array
    {
        $candidates = [];

        foreach ([
            data_get($identity, 'username'),
            data_get($identity, 'display_alias'),
            data_get($identity, 'alias'),
        ] as $value) {
            $username = User::normalizeUsername($value);
            if ($username !== null) {
                $candidates[] = $username;
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @param  array<string, mixed>|null  $identity
     */
    public function reconcileRotatedEntity(User $user, ?array $identity): User
    {
        $entityAddress = strtolower(trim((string) (
            data_get($identity, 'entity_l1_address')
            ?: data_get($identity, 'l1_address')
            ?: ''
        )));

        if ($entityAddress === '') {
            return $user;
        }

        $previousAddress = $user->sovereignIdentityAddress();
        if ($previousAddress !== null && hash_equals(strtolower($previousAddress), $entityAddress)) {
            return $user;
        }

        if ($previousAddress !== null) {
            app(VaultIdentityService::class)->migrateAnchorIfNeeded(
                $previousAddress,
                $entityAddress,
                $user,
            );
        }

        $updates = ['entity_l1_address' => $entityAddress];
        $keyAddress = data_get($identity, 'key_l1_address');
        if (is_string($keyAddress) && trim($keyAddress) !== '') {
            $updates['key_l1_address'] = strtolower(trim($keyAddress));
        }

        $user->forceFill($updates)->save();

        return $user->refresh();
    }

    public function resolveFromRequest(Request $request): ?User
    {
        $sessionIdentity = $request->session()->get('simple_l1_identity');
        $identity = is_array($sessionIdentity) ? $sessionIdentity : null;

        if ($identity === null) {
            $tokenIdentity = (array) $request->attributes->get('storefront_identity', []);
            $identity = $tokenIdentity !== [] ? $tokenIdentity : null;
        }

        return $this->resolveFromIdentity($identity, $request->user());
    }

    public function findExistingUserByUsernameCandidate(?string $usernameCandidate): ?User
    {
        $username = User::normalizeUsername($usernameCandidate);
        if ($username === null) {
            return null;
        }

        return User::query()->where('username_key', $username)->first();
    }
}
