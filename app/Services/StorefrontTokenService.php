<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class StorefrontTokenService
{
    /**
     * @param  array<string, mixed>  $identity
     * @return array<string, mixed>
     */
    public function issue(array $identity, array $scopes = ['storefront:read']): array
    {
        $ttl = max(60, (int) config('storefront.token_ttl_seconds', 900));
        $token = 'sft_'.Str::random(64);
        $issuedAt = now();
        $expiresAt = $issuedAt->copy()->addSeconds($ttl);
        $entityAddress = strtolower((string) data_get($identity, 'entity_l1_address'));
        $resolvedUser = app(MarketplaceIdentityResolver::class)->resolveFromIdentity($identity);
        $user = $resolvedUser instanceof User ? $resolvedUser : User::findByEntityL1Address($entityAddress);
        $username = User::normalizeUsername(data_get($identity, 'username'))
            ?: $user?->username
            ?: ($resolvedUser instanceof User ? $resolvedUser->username : null);
        $displayAlias = data_get($identity, 'display_alias') ?: $user?->publicUsername();
        if ($resolvedUser instanceof User) {
            $entityAddress = strtolower((string) ($resolvedUser->sovereignIdentityAddress() ?: $entityAddress));
        }
        $session = [
            'type' => 'storefront_token',
            'issuer' => config('storefront.token_issuer', 'meanly-storefront'),
            'audience' => config('storefront.token_audience', 'regional-frontends'),
            'identity' => [
                'protocol' => 'simple-l1',
                'entity_l1_address' => $entityAddress,
                'key_l1_address' => data_get($identity, 'key_l1_address') ?: $user?->key_l1_address,
                'username' => $username,
                'alias' => data_get($identity, 'alias'),
                'display_alias' => $displayAlias,
                'proof_token_hash' => data_get($identity, 'proof_token_hash'),
            ],
            'scopes' => array_values(array_unique($scopes)),
            'issued_at' => $issuedAt->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        Cache::put($this->cacheKey($token), $session, $expiresAt);

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'session' => $session,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(?string $token): ?array
    {
        $token = trim((string) $token);
        if ($token === '' || ! str_starts_with($token, 'sft_')) {
            return null;
        }

        $session = Cache::get($this->cacheKey($token));

        return is_array($session) ? $session : null;
    }

    private function cacheKey(string $token): string
    {
        return 'storefront:token:'.hash('sha256', $token);
    }
}
