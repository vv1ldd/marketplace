<?php

namespace App\Console\Commands;

use App\Models\IdentityBinding;
use App\Models\LegalEntity;
use App\Models\SimpleL1IdentityKey;
use App\Models\User;
use App\Models\VaultIdentity;
use App\Services\MarketplaceIdentityResolver;
use App\Services\VaultIdentityService;
use App\Services\WalletBindingService;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class AuditMarketplaceIdentityCommand extends Command
{
    protected $signature = 'meanly:audit-marketplace-identity
                            {identity? : @username, entity sl1e_..., or numeric user id}
                            {--wallet= : Look up which sl1e anchor owns this wallet binding (0x... or bc1...)}
                            {--restore : Re-bind the username account to a target SL1E}
                            {--entity= : Target sl1e_* address for restore}
                            {--auto : Pick the highest-scored SL1E candidate during restore}
                            {--all : Include every SL1E found in the database, not just the target identity}
                            {--grant-access : Also run founder + storefront owner linking after restore}
                            {--revoke-wallet= : Revoke active wallet binding (polygon, bitcoin) for this identity}
                            {--apply : Write changes (default is audit-only)}';

    protected $description = 'Audit SL1E identity, vault bindings, Ops/Merchant access, and optionally restore a username account';

    public function handle(
        MarketplaceIdentityResolver $identityResolver,
        VaultIdentityService $vaultIdentities,
    ): int {
        $walletLookup = trim((string) $this->option('wallet'));
        if ($walletLookup !== '') {
            return $this->printWalletLookup($walletLookup);
        }

        $identityArg = trim((string) ($this->argument('identity') ?? ''));
        if ($identityArg === '') {
            $this->error('Pass an identity (@username / sl1e_... / user id) or use --wallet=0x...');

            return self::FAILURE;
        }
        $username = $this->resolveUsername($identityArg);
        $primaryUser = $this->resolveUser($identityArg);

        $revokeWallet = trim((string) $this->option('revoke-wallet'));
        if ($revokeWallet !== '') {
            return $this->revokeWalletBindings($primaryUser, $revokeWallet);
        }

        $candidates = $this->collectCandidates($username, $primaryUser, (bool) $this->option('all'));
        $this->printAudit($identityArg, $username, $primaryUser, $candidates);
        $this->printOrphanedPolygonVaults($username);
        $this->printPrivilegedUsers($username);

        if (! $this->option('restore')) {
            $this->comment('Audit only. Re-run with --restore --auto --apply or --restore --entity=sl1e_... --apply');

            return self::SUCCESS;
        }

        if (! $this->option('apply')) {
            $this->warn('Restore plan is dry-run only. Add --apply to write changes.');

            return self::SUCCESS;
        }

        $targetEntity = $this->resolveTargetEntity($candidates);
        if ($targetEntity === null) {
            $this->error('Could not determine a target sl1e_* address. Pass --entity=sl1e_... or use --auto.');

            return self::FAILURE;
        }

        $canonicalUser = $this->resolveCanonicalUser($username, $primaryUser, $candidates, $targetEntity);
        if (! $canonicalUser instanceof User) {
            $this->error('Could not resolve a canonical marketplace user to restore.');

            return self::FAILURE;
        }

        $previousEntity = $canonicalUser->sovereignIdentityAddress();
        $this->line("Restoring {$canonicalUser->publicUsername()} (#{$canonicalUser->id}) -> {$targetEntity}");

        $this->absorbPrivilegesFromCandidates($canonicalUser, $candidates, $targetEntity);
        $this->releaseEntityFromDuplicateOccupant($canonicalUser, $targetEntity);

        if ($previousEntity !== null && ! hash_equals(strtolower($previousEntity), strtolower($targetEntity))) {
            $vaultIdentities->migrateAnchorIfNeeded($previousEntity, $targetEntity, $canonicalUser);
        }

        $this->consolidateWalletBindings($canonicalUser, $candidates, $targetEntity);

        $identityResolver->reconcileRotatedEntity($canonicalUser, [
            'entity_l1_address' => $targetEntity,
            'username' => $canonicalUser->username,
        ]);

        SimpleL1IdentityKey::query()
            ->where('entity_l1_address', $targetEntity)
            ->update(['user_id' => $canonicalUser->id]);

        VaultIdentity::query()
            ->where('anchor_address', $targetEntity)
            ->update(['owner_user_id' => $canonicalUser->id]);

        if ($this->option('grant-access')) {
            $this->grantFounderAndStorefront($canonicalUser);
        }

        $canonicalUser->refresh();
        $this->info('Restore complete.');
        $this->line("User #{$canonicalUser->id} {$canonicalUser->publicUsername()} -> {$canonicalUser->sovereignIdentityAddress()}");
        $this->line('Roles: '.$canonicalUser->getRoleNames()->join(', '));
        $this->line('Ops: '.($canonicalUser->hasOpsSovereignAccess() ? 'yes' : 'no'));
        $this->line('Merchant: '.($canonicalUser->isMerchantNode() ? 'yes' : 'no'));
        $this->line('Legal entities: '.$canonicalUser->legalEntities()->count().' owned, '.$canonicalUser->managedLegalEntities()->count().' managed');
        $this->line('Polygon binding: '.$this->polygonBindingSummary($targetEntity));

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectCandidates(?string $username, ?User $primaryUser, bool $includeAll = false): array
    {
        $addresses = collect();

        if ($includeAll) {
            User::query()->orderBy('id')->get()->each(function (User $user) use ($addresses): void {
                if ($user->sovereignIdentityAddress()) {
                    $addresses->push(strtolower((string) $user->sovereignIdentityAddress()));
                }
            });
            VaultIdentity::query()->pluck('anchor_address')->each(
                fn (?string $address) => $addresses->push(strtolower((string) $address)),
            );
            SimpleL1IdentityKey::query()->pluck('entity_l1_address')->each(
                fn (?string $address) => $addresses->push(strtolower((string) $address)),
            );
        }

        if ($primaryUser instanceof User && $primaryUser->sovereignIdentityAddress()) {
            $addresses->push(strtolower((string) $primaryUser->sovereignIdentityAddress()));
        }

        if ($username !== null) {
            User::query()
                ->where('username_key', $username)
                ->orWhere('username', $username)
                ->get()
                ->each(function (User $user) use ($addresses): void {
                    if ($user->sovereignIdentityAddress()) {
                        $addresses->push(strtolower((string) $user->sovereignIdentityAddress()));
                    }
                });

            SimpleL1IdentityKey::query()
                ->whereHas('user', fn ($query) => $query
                    ->where('username_key', $username)
                    ->orWhere('username', $username))
                ->pluck('entity_l1_address')
                ->each(fn (?string $address) => $addresses->push(strtolower((string) $address)));
        }

        VaultIdentity::query()
            ->when($username !== null, function ($query) use ($username): void {
                $query->whereHas('owner', fn ($ownerQuery) => $ownerQuery
                    ->where('username_key', $username)
                    ->orWhere('username', $username));
            })
            ->pluck('anchor_address')
            ->each(fn (?string $address) => $addresses->push(strtolower((string) $address)));

        IdentityBinding::query()
            ->where('binding_type', IdentityBinding::TYPE_WALLET)
            ->whereIn('binding_key', ['polygon', 'ethereum'])
            ->with('vault.owner')
            ->get()
            ->each(function (IdentityBinding $binding) use ($addresses, $username): void {
                $ownerUsername = $binding->vault?->owner?->username;
                if ($username !== null && $ownerUsername !== null && $ownerUsername !== $username) {
                    return;
                }

                $addresses->push(strtolower((string) ($binding->vault?->anchor_address ?? '')));
            });

        return $addresses
            ->filter(fn (string $address): bool => preg_match('/^sl1e_[a-f0-9]{39}$/', $address) === 1)
            ->unique()
            ->values()
            ->map(fn (string $address): array => $this->describeCandidate($address))
            ->sortByDesc('score')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function describeCandidate(string $entityAddress): array
    {
        $user = User::findByEntityL1Address($entityAddress);
        $vault = VaultIdentity::query()->where('anchor_address', $entityAddress)->first();
        $polygonBinding = $vault
            ? $vault->bindings()
                ->where('binding_type', IdentityBinding::TYPE_WALLET)
                ->where('binding_key', 'polygon')
                ->whereIn('verification_state', IdentityBinding::ACTIVE_STATES)
                ->latest('id')
                ->first()
            : null;

        $roles = $user instanceof User ? $user->getRoleNames()->values()->all() : [];
        $legalEntities = $user instanceof User ? $user->legalEntities()->count() : 0;
        $managedLegalEntities = $user instanceof User ? $user->managedLegalEntities()->count() : 0;
        $identityKeys = SimpleL1IdentityKey::query()->where('entity_l1_address', $entityAddress)->get();
        $registeredKeys = $identityKeys
            ->whereNull('revoked_at')
            ->pluck('key_l1_address')
            ->filter()
            ->values()
            ->all();

        $score = 0;
        if ($polygonBinding) {
            $score += 100;
        }
        if ($user?->hasOpsSovereignAccess()) {
            $score += 50;
        }
        if ($user?->isMerchantNode()) {
            $score += 50;
        }
        $score += min(30, $legalEntities * 15);
        $score += min(30, $managedLegalEntities * 15);
        $score += min(20, count($registeredKeys) * 5);

        return [
            'entity' => $entityAddress,
            'score' => $score,
            'user_id' => $user?->id,
            'username' => $user?->username,
            'roles' => $roles,
            'legal_entities' => $legalEntities,
            'managed_legal_entities' => $managedLegalEntities,
            'identity_keys' => count($registeredKeys),
            'registered_keys' => $registeredKeys,
            'vault_id' => $vault?->id,
            'polygon' => $polygonBinding?->binding_value_normalized,
            'polygon_state' => $polygonBinding?->verification_state,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     */
    private function printAudit(
        string $identityArg,
        ?string $username,
        ?User $primaryUser,
        array $candidates,
    ): void {
        $this->info("Marketplace identity audit for {$identityArg}");
        if ($primaryUser instanceof User) {
            $this->line("Primary user #{$primaryUser->id} {$primaryUser->publicUsername()} -> {$primaryUser->sovereignIdentityAddress()}");
            $this->line('Roles: '.$primaryUser->getRoleNames()->join(', '));
        } else {
            $this->warn('No primary marketplace user matched this identity argument.');
        }

        if ($candidates === []) {
            $this->warn('No SL1E candidates found for this identity.');

            return;
        }

        $this->newLine();
        $this->table(
            ['Score', 'SL1E', 'User', 'Roles', 'Polygon', 'LE', 'SL1 keys'],
            collect($candidates)->map(fn (array $row): array => [
                $row['score'],
                $row['entity'],
                $row['user_id'] ? '#'.$row['user_id'].' @'.$row['username'] : '—',
                implode(',', $row['roles'] ?: ['—']),
                $row['polygon'] ? substr((string) $row['polygon'], 0, 14).'… ('.$row['polygon_state'].')' : '—',
                $row['legal_entities'].'/'.$row['managed_legal_entities'],
                $row['identity_keys'] > 0
                    ? $row['identity_keys'].' ['.implode(', ', array_map(
                        fn (string $key): string => substr($key, -8),
                        $row['registered_keys'] ?? [],
                    )).']'
                    : '0',
            ])->all(),
        );

        $best = $candidates[0];
        $this->info('Best candidate: '.$best['entity'].' (score '.$best['score'].')');
    }

    private function revokeWalletBindings(?User $user, string $networkKey): int
    {
        if (! $user instanceof User) {
            $this->error('Could not resolve a marketplace user for this identity.');

            return self::FAILURE;
        }

        $networkKey = strtolower(trim($networkKey));
        if (! in_array($networkKey, ['polygon', 'bitcoin'], true)) {
            $this->error('Only polygon and bitcoin wallet bindings can be revoked through this command.');

            return self::FAILURE;
        }

        $entityAddress = strtolower((string) ($user->sovereignIdentityAddress() ?? ''));
        $vaultQuery = VaultIdentity::query()->where('owner_user_id', $user->id);
        if ($entityAddress !== '') {
            $vaultQuery->orWhere('anchor_address', $entityAddress);
        }

        $vaults = $vaultQuery->get()->unique('id');
        if ($vaults->isEmpty()) {
            $this->warn("No vault identities found for {$user->publicUsername()} (#{$user->id}).");

            return self::SUCCESS;
        }

        $bindings = IdentityBinding::query()
            ->whereIn('vault_id', $vaults->pluck('id'))
            ->where('binding_type', IdentityBinding::TYPE_WALLET)
            ->where('binding_key', $networkKey)
            ->whereIn('verification_state', IdentityBinding::ACTIVE_STATES)
            ->with('vault')
            ->orderByDesc('id')
            ->get();

        if ($bindings->isEmpty()) {
            $this->warn("No active {$networkKey} binding found for {$user->publicUsername()}.");

            return self::SUCCESS;
        }

        $this->info("Active {$networkKey} bindings for {$user->publicUsername()} (#{$user->id}):");
        $this->table(
            ['Network', 'Address', 'State', 'SL1E anchor'],
            $bindings->map(fn (IdentityBinding $binding): array => [
                (string) $binding->binding_key,
                (string) $binding->binding_value_normalized,
                (string) $binding->verification_state,
                (string) ($binding->vault?->anchor_address ?? '—'),
            ])->all(),
        );

        if (! $this->option('apply')) {
            $this->warn('Dry run only. Re-run with --apply to revoke these bindings.');

            return self::SUCCESS;
        }

        $walletBindings = app(WalletBindingService::class);
        foreach ($bindings as $binding) {
            $vault = $binding->vault;
            if (! $vault instanceof VaultIdentity) {
                continue;
            }

            $walletBindings->revokeBinding($vault, $binding);
            $this->line("Revoked {$networkKey} {$binding->binding_value_normalized} on {$vault->anchor_address}.");
        }

        $this->info('Wallet binding revoke complete.');

        return self::SUCCESS;
    }

    private function printWalletLookup(string $walletAddress): int
    {
        $normalized = strtolower(trim($walletAddress));
        if ($normalized === '') {
            $this->error('Wallet address is empty.');

            return self::FAILURE;
        }

        $bindings = IdentityBinding::query()
            ->where('binding_type', IdentityBinding::TYPE_WALLET)
            ->whereRaw('LOWER(binding_value_normalized) = ?', [$normalized])
            ->with(['vault.owner'])
            ->orderByDesc('id')
            ->get();

        if ($bindings->isEmpty()) {
            $this->warn("No wallet bindings found for {$walletAddress} in this database.");

            return self::SUCCESS;
        }

        $this->info("Wallet binding history for {$walletAddress}:");
        $this->table(
            ['Network', 'State', 'SL1E anchor', 'Vault owner', 'Bound', 'Verified', 'Revoked'],
            $bindings->map(function (IdentityBinding $binding): array {
                $owner = $binding->vault?->owner;

                return [
                    (string) $binding->binding_key,
                    (string) $binding->verification_state,
                    (string) ($binding->vault?->anchor_address ?? '—'),
                    $owner instanceof User
                        ? '#'.$owner->id.' @'.$owner->username
                        : 'orphan',
                    $binding->bound_at?->toDateTimeString() ?? '—',
                    $binding->verified_at?->toDateTimeString() ?? '—',
                    $binding->revoked_at?->toDateTimeString() ?? '—',
                ];
            })->all(),
        );

        $active = $bindings->first(
            fn (IdentityBinding $binding): bool => in_array(
                (string) $binding->verification_state,
                IdentityBinding::ACTIVE_STATES,
                true,
            ),
        );

        if ($active instanceof IdentityBinding) {
            $this->info('Active anchor: '.($active->vault?->anchor_address ?? '—'));
        } else {
            $latest = $bindings->first();
            $this->comment('No active binding. Latest record was '.$latest?->verification_state.' on '.($latest?->vault?->anchor_address ?? '—'));
        }

        return self::SUCCESS;
    }

    private function printOrphanedPolygonVaults(?string $username): void
    {
        $bindings = IdentityBinding::query()
            ->where('binding_type', IdentityBinding::TYPE_WALLET)
            ->where('binding_key', 'polygon')
            ->whereIn('verification_state', IdentityBinding::ACTIVE_STATES)
            ->with('vault.owner')
            ->latest('id')
            ->limit(20)
            ->get();

        if ($bindings->isEmpty()) {
            $this->warn('No active Polygon wallet bindings found in this database.');

            return;
        }

        $this->newLine();
        $this->info('Active Polygon bindings in database:');
        $this->table(
            ['SL1E anchor', 'Owner', 'Address', 'State'],
            $bindings->map(fn (IdentityBinding $binding): array => [
                $binding->vault?->anchor_address ?? '—',
                $binding->vault?->owner
                    ? '#'.$binding->vault->owner->id.' @'.$binding->vault->owner->username
                    : 'orphan',
                (string) $binding->binding_value_normalized,
                $binding->verification_state,
            ])->all(),
        );
    }

    private function printPrivilegedUsers(?string $username): void
    {
        $users = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [
                User::ROLE_SOVEREIGN_VALIDATOR,
                User::ROLE_MERCHANT_NODE,
            ]))
            ->orWhereHas('legalEntities')
            ->orWhereHas('managedLegalEntities')
            ->orderBy('id')
            ->limit(20)
            ->get();

        if ($users->isEmpty()) {
            $this->warn('No Ops/Merchant/legal-entity users found in this database.');

            return;
        }

        $this->newLine();
        $this->info('Users with Ops/Merchant/legal-entity access:');
        $this->table(
            ['User', 'SL1E', 'Roles', 'LE owned/managed'],
            $users->map(fn (User $user): array => [
                '#'.$user->id.' @'.$user->username.($username !== null && $user->username === $username ? ' ← target' : ''),
                $user->sovereignIdentityAddress() ?? '—',
                $user->getRoleNames()->join(','),
                $user->legalEntities()->count().'/'.$user->managedLegalEntities()->count(),
            ])->all(),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     */
    private function resolveTargetEntity(array $candidates): ?string
    {
        $entity = trim((string) $this->option('entity'));
        if ($entity !== '') {
            return strtolower($entity);
        }

        if ($this->option('auto') && $candidates !== []) {
            return (string) $candidates[0]['entity'];
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     */
    private function resolveCanonicalUser(
        ?string $username,
        ?User $primaryUser,
        array $candidates,
        string $targetEntity,
    ): ?User {
        if ($username !== null) {
            $byUsername = User::query()->where('username_key', $username)->first();
            if ($byUsername instanceof User) {
                return $byUsername;
            }
        }

        if ($primaryUser instanceof User) {
            return $primaryUser;
        }

        $targetCandidate = collect($candidates)->firstWhere('entity', $targetEntity);

        return isset($targetCandidate['user_id'])
            ? User::find((int) $targetCandidate['user_id'])
            : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     */
    private function releaseEntityFromDuplicateOccupant(User $canonicalUser, string $targetEntity): void
    {
        $occupant = User::findByEntityL1Address($targetEntity);
        if (! $occupant instanceof User || (int) $occupant->id === (int) $canonicalUser->id) {
            return;
        }

        LegalEntity::query()->where('user_id', $occupant->id)->update(['user_id' => $canonicalUser->id]);

        $canonicalUser->managedLegalEntities()->syncWithoutDetaching(
            $occupant->managedLegalEntities()->pluck('legal_entities.id')->mapWithKeys(
                fn ($id) => [$id => ['role' => 'owner']]
            )->all(),
        );

        foreach ($occupant->getRoleNames() as $roleName) {
            Role::findOrCreate((string) $roleName, 'web');
            if (! $canonicalUser->hasRole($roleName)) {
                $canonicalUser->assignRole((string) $roleName);
            }
        }

        $replacementEntity = app(\App\Services\L1IdentityService::class)->newEntityAddress();
        $occupant->forceFill([
            'entity_l1_address' => $replacementEntity,
            'key_l1_address' => null,
        ])->save();

        $this->warn("Released {$targetEntity} from user #{$occupant->id} @{$occupant->username} (re-homed to {$replacementEntity}).");
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     */
    private function consolidateWalletBindings(User $canonicalUser, array $candidates, string $targetEntity): void
    {
        $targetVault = VaultIdentity::query()->firstOrCreate(
            ['anchor_address' => $targetEntity],
            [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'owner_user_id' => $canonicalUser->id,
                'vault_kind' => VaultIdentity::KIND_PERSONAL,
            ],
        );

        $targetVault->forceFill(['owner_user_id' => $canonicalUser->id])->save();

        $entityAddresses = collect($candidates)
            ->pluck('entity')
            ->filter()
            ->map(fn (string $address): string => strtolower($address))
            ->unique()
            ->values();

        foreach (['polygon', 'bitcoin'] as $networkKey) {
            $bindings = IdentityBinding::query()
                ->where('binding_type', IdentityBinding::TYPE_WALLET)
                ->where('binding_key', $networkKey)
                ->whereIn('verification_state', IdentityBinding::ACTIVE_STATES)
                ->whereHas('vault', fn ($query) => $query->whereIn('anchor_address', $entityAddresses))
                ->with('vault')
                ->get()
                ->sortByDesc(function (IdentityBinding $binding) use ($candidates): int {
                    $entity = strtolower((string) ($binding->vault?->anchor_address ?? ''));
                    $candidate = collect($candidates)->firstWhere('entity', $entity);

                    $score = (int) ($candidate['score'] ?? 0);
                    if ($binding->verification_state === IdentityBinding::STATE_VERIFIED) {
                        $score += 1000;
                    }

                    return $score;
                });

            $preferred = $bindings->first();
            if (! $preferred instanceof IdentityBinding) {
                continue;
            }

            $current = $targetVault->bindings()
                ->where('binding_type', IdentityBinding::TYPE_WALLET)
                ->where('binding_key', $networkKey)
                ->whereIn('verification_state', IdentityBinding::ACTIVE_STATES)
                ->latest('id')
                ->first();

            if ($current instanceof IdentityBinding
                && hash_equals(
                    strtolower((string) $current->binding_value_normalized),
                    strtolower((string) $preferred->binding_value_normalized),
                )) {
                continue;
            }

            if ($current instanceof IdentityBinding) {
                $current->forceFill([
                    'verification_state' => IdentityBinding::STATE_REVOKED,
                    'revoked_at' => now(),
                ])->save();
            }

            if ((string) $preferred->vault_id !== (string) $targetVault->id) {
                $preferred->forceFill(['vault_id' => $targetVault->id])->save();
            }

            $this->warn(sprintf(
                'Consolidated %s binding %s onto %s.',
                $networkKey,
                substr((string) $preferred->binding_value_normalized, 0, 18).'…',
                $targetEntity,
            ));
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     */
    private function absorbPrivilegesFromCandidates(User $canonicalUser, array $candidates, string $targetEntity): void
    {
        $target = collect($candidates)->firstWhere('entity', $targetEntity) ?? [];
        $roles = collect($target['roles'] ?? []);

        foreach ($candidates as $candidate) {
            if ((int) ($candidate['user_id'] ?? 0) === (int) $canonicalUser->id) {
                continue;
            }

            $duplicate = User::find((int) ($candidate['user_id'] ?? 0));
            if (! $duplicate instanceof User) {
                continue;
            }

            $roles = $roles->merge($duplicate->getRoleNames());

            LegalEntity::query()->where('user_id', $duplicate->id)->update(['user_id' => $canonicalUser->id]);
            $canonicalUser->managedLegalEntities()->syncWithoutDetaching(
                $duplicate->managedLegalEntities()->pluck('legal_entities.id')->mapWithKeys(
                    fn ($id) => [$id => ['role' => 'owner']]
                )->all(),
            );
        }

        foreach ($roles->unique()->filter()->all() as $roleName) {
            Role::findOrCreate((string) $roleName, 'web');
            if (! $canonicalUser->hasRole($roleName)) {
                $canonicalUser->assignRole((string) $roleName);
            }
        }
    }

    private function grantFounderAndStorefront(User $user): void
    {
        $this->call('meanly:grant-founder-access', [
            'identity' => (string) $user->id,
        ]);
        $this->call('meanly:link-storefront-owner', [
            'identity' => (string) $user->id,
        ]);
    }

    private function polygonBindingSummary(string $entityAddress): string
    {
        $vault = VaultIdentity::query()->where('anchor_address', $entityAddress)->first();
        if (! $vault) {
            return 'none';
        }

        $binding = $vault->bindings()
            ->where('binding_key', 'polygon')
            ->whereIn('verification_state', IdentityBinding::ACTIVE_STATES)
            ->latest('id')
            ->first();

        return $binding instanceof IdentityBinding
            ? (string) $binding->binding_value_normalized
            : 'none';
    }

    private function resolveUsername(string $identity): ?string
    {
        if (preg_match('/^sl1e_[a-f0-9]{39}$/i', $identity)) {
            return User::findByEntityL1Address($identity)?->username;
        }

        return User::normalizeUsername($identity);
    }

    private function resolveUser(string $identity): ?User
    {
        $identity = trim($identity);
        if ($identity === '') {
            return null;
        }

        if (ctype_digit($identity)) {
            return User::find((int) $identity);
        }

        if (preg_match('/^sl1e_[a-f0-9]{39}$/i', $identity)) {
            return User::findByEntityL1Address($identity);
        }

        $username = User::normalizeUsername($identity);
        if ($username === null) {
            return null;
        }

        return User::query()
            ->where('username_key', $username)
            ->orWhere('username', $username)
            ->first();
    }
}
