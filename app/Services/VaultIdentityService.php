<?php

namespace App\Services;

use App\Models\User;
use App\Models\VaultIdentity;
use Illuminate\Support\Str;

class VaultIdentityService
{
    /**
     * @param array<string, mixed> $identity
     */
    public function resolveForStorefront(array $identity, ?User $user = null): VaultIdentity
    {
        $anchorAddress = strtolower(trim((string) ($identity['entity_l1_address'] ?? '')));
        abort_if($anchorAddress === '', 403);

        $vault = VaultIdentity::query()->firstOrCreate(
            ['anchor_address' => $anchorAddress],
            [
                'id' => (string) Str::uuid(),
                'owner_user_id' => $user?->id,
                'vault_kind' => VaultIdentity::KIND_PERSONAL,
            ],
        );

        if ($user instanceof User && $vault->owner_user_id === null) {
            $vault->forceFill(['owner_user_id' => $user->id])->save();
        }

        return $vault->refresh();
    }

    public function migrateAnchorIfNeeded(?string $previousAddress, string $nextAddress, User $user): void
    {
        $previousAddress = strtolower(trim((string) $previousAddress));
        $nextAddress = strtolower(trim((string) $nextAddress));

        if ($previousAddress === '' || $previousAddress === $nextAddress) {
            return;
        }

        if (! preg_match('/^sl1e_[a-f0-9]{39}$/', $previousAddress)
            || ! preg_match('/^sl1e_[a-f0-9]{39}$/', $nextAddress)) {
            return;
        }

        $previousVault = VaultIdentity::query()->where('anchor_address', $previousAddress)->first();
        if (! $previousVault instanceof VaultIdentity) {
            return;
        }

        $nextVault = VaultIdentity::query()->where('anchor_address', $nextAddress)->first();
        if (! $nextVault instanceof VaultIdentity) {
            $previousVault->forceFill([
                'anchor_address' => $nextAddress,
                'owner_user_id' => $previousVault->owner_user_id ?? $user->id,
            ])->save();

            return;
        }

        if ((int) ($nextVault->owner_user_id ?? 0) === 0) {
            $nextVault->forceFill(['owner_user_id' => $user->id])->save();
        }

        if ($nextVault->bindings()->count() > 0) {
            return;
        }

        foreach ([
            'bindings',
            'bindingChallenges',
            'bindingEvents',
            'bindingProofs',
            'verificationEvents',
            'settlementAuditEvents',
        ] as $relation) {
            $previousVault->{$relation}()->update(['vault_id' => $nextVault->id]);
        }

        $previousVault->delete();
    }

    public function assertOwnedByUser(VaultIdentity $vault, User $user): void
    {
        abort_unless(
            $vault->owner_user_id === null || (int) $vault->owner_user_id === (int) $user->id,
            403,
        );
    }
}
