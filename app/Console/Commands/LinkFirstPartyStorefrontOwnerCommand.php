<?php

namespace App\Console\Commands;

use App\Models\Seller;
use App\Models\User;
use App\Services\MeanlyFirstPartyStorefrontService;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class LinkFirstPartyStorefrontOwnerCommand extends Command
{
    protected $signature = 'meanly:link-storefront-owner
                            {identity : @username, entity sl1e_..., or numeric user id}
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Link the first-party Meanly Store legal entity and shop to an SL1E user';

    public function handle(MeanlyFirstPartyStorefrontService $storefront): int
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

        $entity = $storefront->legalEntity();
        $shop = $storefront->shop();

        $this->line("Target user: #{$user->id} {$user->publicUsername()} ({$user->sovereignIdentityAddress()})");
        $this->line("Legal entity: #{$entity->id} {$entity->name}");
        $this->line("Shop: #{$shop->id} {$shop->name}");

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes written.');

            return self::SUCCESS;
        }

        $seller = $entity->seller_id ? Seller::query()->find($entity->seller_id) : null;
        if (! $seller) {
            $seller = Seller::query()->create([
                'first_name' => $user->first_name ?: $user->publicUsername(),
                'last_name' => $user->last_name,
                'middle_name' => $user->middle_name,
                'is_active' => true,
            ]);
            $this->info("Created seller #{$seller->id}");
        }

        $merchantRole = Role::findOrCreate(User::ROLE_MERCHANT_NODE, 'sellers');
        if (! $seller->hasRole(User::ROLE_MERCHANT_NODE)) {
            $seller->assignRole($merchantRole);
        }

        if (! $user->hasRole(User::ROLE_MERCHANT_NODE)) {
            $user->assignRole(Role::findOrCreate(User::ROLE_MERCHANT_NODE, 'web'));
        }

        $entity->forceFill([
            'user_id' => $user->id,
            'seller_id' => $seller->id,
        ])->save();

        $user->managedLegalEntities()->syncWithoutDetaching([
            $entity->id => ['role' => 'owner', 'seller_id' => $seller->id],
        ]);

        $seller->managedLegalEntities()->syncWithoutDetaching([
            $entity->id => ['role' => 'owner', 'user_id' => $user->id],
        ]);

        $user->managedShops()->syncWithoutDetaching([
            $shop->id => ['role' => 'owner'],
        ]);

        $user->forceFill(['shop_id' => $shop->id])->save();

        $this->info("Linked {$user->publicUsername()} to Meanly Store (#{$shop->id}).");
        $this->info('Merchant legal entity: '.$entity->fresh()->name);

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
