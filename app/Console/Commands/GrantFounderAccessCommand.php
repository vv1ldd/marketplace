<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class GrantFounderAccessCommand extends Command
{
    protected $signature = 'meanly:grant-founder-access
                            {identity : @username, entity sl1e_..., or numeric user id}
                            {--ops-only : Grant only sovereign_validator (full ops gate bypass)}
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Grant founder / ops sovereign access to a Meanly SL1E identity';

    public function handle(): int
    {
        $user = $this->resolveUser((string) $this->argument('identity'));
        if (! $user instanceof User) {
            $this->error('User not found for identity: '.$this->argument('identity'));

            return self::FAILURE;
        }

        if (! $user->hasSovereignIdentity()) {
            $this->error("User #{$user->id} has no bound sl1e_* entity address. Connect Simple L1 first.");

            return self::FAILURE;
        }

        $roles = $this->option('ops-only')
            ? [User::ROLE_SOVEREIGN_VALIDATOR, User::ROLE_WALLET_HOLDER]
            : array_values(array_unique([
                ...User::SYSTEM_ROLES,
                ...User::PARTNER_ROLES,
                User::ROLE_WALLET_HOLDER,
            ]));

        $this->line("User #{$user->id} {$user->publicUsername()} ({$user->sovereignIdentityAddress()})");
        $this->line('Current roles: '.$user->getRoleNames()->join(', '));
        $this->line('Target roles: '.implode(', ', $roles));

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes written.');

            return self::SUCCESS;
        }

        foreach ($roles as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        $user->syncRoles($roles);

        $user->refresh();

        $this->info('Updated roles: '.$user->getRoleNames()->join(', '));
        $this->info('Ops access: '.($user->hasOpsSovereignAccess() ? 'yes' : 'no'));
        $this->info('Merchant access: '.($user->isMerchantNode() ? 'yes' : 'no'));

        return self::SUCCESS;
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
