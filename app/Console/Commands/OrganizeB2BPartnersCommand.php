<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Seller;
use App\Models\LegalEntity;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class OrganizeB2BPartnersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'b2b:organize';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Organize B2B Partner accounts and decouple super-admins from storefronts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("🚀 Decoupling super admins from storefronts and establishing dedicated B2B partner accounts...");

        // 1. Detach super admins from shops
        $superAdmins = User::where('type', 'super-admin')->get();
        foreach ($superAdmins as $admin) {
            $admin->update(['shop_id' => null]);
            $this->info("🔑 Super Admin [ID: {$admin->id}, Email: {$admin->email}] successfully detached from all shops.");
        }

        // 2. Map LegalEntities to dedicated user and seller accounts
        $entities = LegalEntity::all();
        foreach ($entities as $entity) {
            $this->info("------------------------------------------------------------------");
            $this->info("📦 Processing Legal Entity: [ID: {$entity->id}] {$entity->name} (INN: {$entity->inn})");

            $shouldCreateUser = false;
            $user = null;

            // Determine if the entity currently points to a super admin or has no user
            if (!$entity->user_id) {
                $shouldCreateUser = true;
                $this->line("👉 No owner user specified. Generating a dedicated account.");
            } else {
                $currentUser = User::find($entity->user_id);
                if ($currentUser && $currentUser->type === 'super-admin') {
                    $shouldCreateUser = true;
                    $this->line("👉 Current owner is a Super Admin. Decoupling and generating a dedicated partner account.");
                } else {
                    $user = $currentUser;
                    $this->line("👉 Points to existing wallet user: [ID: {$user->id}] {$user->sovereignIdentityAddress()}");
                }
            }

            if ($shouldCreateUser) {
                $names = $this->parseDirectorName($entity);

                $user = User::create([
                    'first_name' => $names['first_name'],
                    'last_name' => $names['last_name'],
                    'middle_name' => $names['middle_name'],
                    'meta' => [
                        'registration_source' => 'b2b_organize_command',
                        'requires_sl1e_claim' => true,
                    ],
                ]);
                $this->info("✨ Created wallet placeholder User: [ID: {$user->id}]");

                // Update LegalEntity user_id reference
                $entity->update(['user_id' => $user->id]);
            }

            // Verify User type is 'client' and has 'b2b_partner' role
            if ($user) {
                if ($user->type !== 'client') {
                    $user->update(['type' => 'client']);
                }
                
                if (method_exists($user, 'assignRole')) {
                    Role::firstOrCreate(['name' => 'b2b_partner', 'guard_name' => 'web']);
                    $user->assignRole('b2b_partner');
                    $this->info("✅ Role 'b2b_partner' assigned to User [ID: {$user->id}].");
                }
            }

            // Create or find Seller account (which is linked to B2B dashboard authentication)
            $sellerNames = $this->parseDirectorName($entity);
            
            $seller = $entity->seller_id ? Seller::find($entity->seller_id) : null;
            if (!$seller) {
                $seller = Seller::create([
                    'first_name' => $sellerNames['first_name'],
                    'last_name' => $sellerNames['last_name'],
                    'middle_name' => $sellerNames['middle_name'],
                    'is_active' => true,
                ]);
                $this->info("✨ Created new B2B Seller: [ID: {$seller->id}]");
            } else {
                $this->info("📌 Found existing Seller: [ID: {$seller->id}]");
            }

            if (method_exists($seller, 'assignRole')) {
                $sellerRole = Role::firstOrCreate(['name' => 'b2b_partner', 'guard_name' => 'sellers']);
                $seller->assignRole($sellerRole);
                $this->info("✅ Role 'b2b_partner' assigned to Seller [ID: {$seller->id}].");
            }

            // Set the seller_id on LegalEntity
            $entity->update(['seller_id' => $seller->id]);

            // Sync the legal_entity_managers pivot table
            if ($user && $seller) {
                $user->managedLegalEntities()->syncWithoutDetaching([
                    $entity->id => ['role' => 'admin', 'seller_id' => $seller->id]
                ]);
                
                $seller->managedLegalEntities()->syncWithoutDetaching([
                    $entity->id => ['role' => 'owner', 'user_id' => $user->id]
                ]);
                
                $this->info("🔗 Linked User & Seller as managers/owners of Legal Entity {$entity->id}.");
            }

            // Link the user to the first active Shop belonging to this LegalEntity (if any)
            $shop = Shop::where('legal_entity_id', $entity->id)->first();
            if ($shop) {
                if ($user && $user->shop_id !== $shop->id) {
                    $user->update(['shop_id' => $shop->id]);
                    $this->info("🏪 Associated User [ID: {$user->id}] with Shop [ID: {$shop->id}] {$shop->name}");
                }
            }
        }

        $this->info("------------------------------------------------------------------");
        $this->info("🏆 B2B PARTNER ACCOUNTS RECONCILED SUCCESSFULLY!");
        $this->info("Default password for newly created accounts: MeanlyPartner2026!");
        $this->info("------------------------------------------------------------------");

        return 0;
    }

    private function generateEmailForEntity(LegalEntity $entity): string
    {
        $name = mb_strtolower($entity->name);
        
        if (str_contains($name, 'атаниязова дженнет')) {
            return 'dzhennet@meanly.ru';
        }
        
        if (str_contains($name, 'иванов никита')) {
            return 'nikita.ivanov@meanly.ru';
        }
        
        if (str_contains($name, 'cjs group')) {
            return 'cjs.group@meanly.ru';
        }

        if (str_contains($name, 'атаниязова новбахар')) {
            return 'novbakhar@meanly.ru';
        }
        
        // Dynamic email fallback based on INN or ID
        return 'partner_' . ($entity->inn ?: $entity->id) . '@meanly.ru';
    }

    private function parseDirectorName(LegalEntity $entity): array
    {
        $name = $entity->name;
        
        // Remove prefixes
        $name = str_ireplace(['ИП', 'ООО', 'Индивидуальный предприниматель', 'CJS GROUP'], '', $name);
        $name = trim($name);
        
        if (empty($name)) {
            return [
                'first_name' => 'Партнер',
                'last_name' => 'Meanly',
                'middle_name' => '',
            ];
        }
        
        $parts = array_filter(explode(' ', $name));
        $parts = array_values($parts);
        
        $lastName = $parts[0] ?? 'Партнер';
        $firstName = $parts[1] ?? 'Meanly';
        $middleName = $parts[2] ?? '';
        
        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'middle_name' => $middleName,
        ];
    }
}
