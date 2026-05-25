<?php

namespace App\Services;

use App\Models\LegalEntity;
use App\Models\LegalEntityMigrationPill;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class LegalEntityMigrationPillService
{
    public function issueForOwner(
        LegalEntity $legalEntity,
        ?string $targetDomain = null,
        ?User $issuedBy = null,
        ?string $issuedIp = null,
        ?\DateTimeInterface $expiresAt = null,
    ): array {
        return DB::transaction(function () use ($legalEntity, $targetDomain, $issuedBy, $issuedIp, $expiresAt) {
            $legalEntity = LegalEntity::query()->lockForUpdate()->findOrFail($legalEntity->id);
            [$user, $seller] = $this->resolveOwnerPrincipal($legalEntity);

            LegalEntityMigrationPill::query()
                ->where('legal_entity_id', $legalEntity->id)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->get()
                ->each(function (LegalEntityMigrationPill $activePill) {
                    $activePill->forceFill([
                        'expires_at' => now(),
                        'metadata' => array_merge($activePill->metadata ?? [], [
                            'revoked_reason' => 'superseded',
                        ]),
                    ])->save();
                });

            $token = Str::random(64);
            $pill = LegalEntityMigrationPill::create([
                'legal_entity_id' => $legalEntity->id,
                'user_id' => $user->id,
                'issued_by_user_id' => $issuedBy?->id,
                'token_hash' => $this->hashToken($token),
                'target_domain' => $targetDomain,
                'expires_at' => $expiresAt ?? now()->addDays(7),
                'issued_ip' => $issuedIp,
                'metadata' => [
                    'seller_id' => $seller?->id,
                    'purpose' => 'owner_production_passkey_enrollment',
                ],
            ]);

            $this->recordLedger('MIGRATION_PILL_ISSUED', $pill, [
                'legal_entity_id' => $legalEntity->id,
                'user_id' => $user->id,
                'target_domain' => $targetDomain,
                'expires_at' => $pill->expires_at?->toIso8601String(),
            ]);

            return [$pill, $token];
        });
    }

    public function findConsumableByToken(string $token): ?LegalEntityMigrationPill
    {
        return LegalEntityMigrationPill::query()
            ->with(['legalEntity', 'user'])
            ->where('token_hash', $this->hashToken($token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function consume(string $token, int $passkeyId, ?string $usedIp = null): LegalEntityMigrationPill
    {
        return DB::transaction(function () use ($token, $passkeyId, $usedIp) {
            $pill = LegalEntityMigrationPill::query()
                ->where('token_hash', $this->hashToken($token))
                ->lockForUpdate()
                ->first();

            if (! $pill || ! $pill->isConsumable()) {
                throw new \RuntimeException('Migration pill is invalid, expired, or already used.');
            }

            $pill->forceFill([
                'used_at' => now(),
                'used_by_passkey_id' => $passkeyId,
                'used_ip' => $usedIp,
            ])->save();

            $this->recordLedger('MIGRATION_PILL_CONSUMED', $pill, [
                'legal_entity_id' => $pill->legal_entity_id,
                'user_id' => $pill->user_id,
                'passkey_id' => $passkeyId,
                'target_domain' => $pill->target_domain,
            ]);

            return $pill->refresh();
        });
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function migrationUrl(string $token, ?string $targetDomain = null): string
    {
        $path = '/migration-pill/'.$token;

        if ($targetDomain) {
            $scheme = str_starts_with($targetDomain, 'http://') || str_starts_with($targetDomain, 'https://')
                ? ''
                : 'https://';

            return rtrim($scheme.$targetDomain, '/').$path;
        }

        return url($path);
    }

    private function resolveOwnerPrincipal(LegalEntity $legalEntity): array
    {
        $user = $legalEntity->user
            ?? $legalEntity->managers()->wherePivotIn('role', ['owner', 'admin'])->first()
            ?? $legalEntity->managers()->first()
            ?? $this->createOwnerUser($legalEntity);

        $this->ensurePartnerRole($user);

        $seller = $legalEntity->seller
            ?? Seller::findByEmail((string) $user->email)
            ?? $this->createOwnerSeller($legalEntity, $user);

        $legalEntity->forceFill([
            'user_id' => $user->id,
            'seller_id' => $seller?->id,
        ])->saveQuietly();

        $user->managedLegalEntities()->syncWithoutDetaching([
            $legalEntity->id => ['role' => 'owner', 'seller_id' => $seller?->id],
        ]);

        if ($seller) {
            $seller->managedLegalEntities()->syncWithoutDetaching([
                $legalEntity->id => ['role' => 'owner', 'user_id' => $user->id],
            ]);
        }

        return [$user, $seller];
    }

    private function createOwnerUser(LegalEntity $legalEntity): User
    {
        $email = $legalEntity->email ?: "legal-entity-{$legalEntity->id}@migration.meanly.local";

        return User::create([
            'first_name' => 'Legal',
            'last_name' => 'Owner '.$legalEntity->id,
            'email' => $email,
            'password' => Hash::make(Str::random(64)),
            'password_login_enabled' => false,
        ]);
    }

    private function createOwnerSeller(LegalEntity $legalEntity, User $user): Seller
    {
        $seller = Seller::create([
            'first_name' => $user->first_name ?: 'Legal',
            'last_name' => $user->last_name ?: 'Owner '.$legalEntity->id,
            'middle_name' => $user->middle_name,
            'email' => $legalEntity->seller?->email ?: $user->email,
            'password' => Hash::make(Str::random(64)),
            'is_active' => true,
            'password_login_enabled' => false,
        ]);

        try {
            $seller->assignRole('b2b_partner');
        } catch (\Throwable) {
        }

        return $seller;
    }

    private function ensurePartnerRole(User $user): void
    {
        Role::firstOrCreate(['name' => 'b2b_partner', 'guard_name' => 'web']);

        if (! $user->hasRole('b2b_partner')) {
            $user->assignRole('b2b_partner');
        }
    }

    private function recordLedger(string $eventType, LegalEntityMigrationPill $pill, array $payload): void
    {
        try {
            app(LedgerService::class)->record(
                shop: null,
                eventType: $eventType,
                entity: $pill,
                payload: $payload,
                legalEntity: $pill->legalEntity,
                triggerSource: 'DID:MIGRATION:PILL',
                inputData: ['token_hash' => $pill->token_hash]
            );
        } catch (\Throwable $e) {
            \Log::warning("Ledger record failed for {$eventType}: ".$e->getMessage());
        }
    }
}
