<?php

namespace App\Console\Commands;

use Database\Seeders\AgreementSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DevResetAccountsCommand extends Command
{
    protected $signature = 'dev:reset-accounts
                            {--force : Allow running outside local/testing}
                            {--keep-agreements : Do not re-seed B2B/B2C agreements}';

    protected $description = 'Wipe dev accounts, shops, orders and seller products; keep provider catalog and reference data';

    /**
     * Commerce / identity tables cleared on reset (FK checks disabled).
     *
     * @var list<string>
     */
    private array $truncateTables = [
        'ym_notifications',
        'wildflow_kernel_orders',
        'wildflow_credit_reservations',
        'woo_synced_orders',
        'order_search_attributions',
        'order_comments',
        'order_progress',
        'order_items',
        'orders',
        'ticket_messages',
        'tickets',
        'api_applications',
        'procurements',
        'product_sales_channels',
        'product_inventory',
        'direct_channel_product',
        'direct_channels',
        'products',
        'warehouse_stocks',
        'warehouses',
        'shop_user',
        'shops',
        'seller_terminals',
        'settlement_proofs',
        'merchant_deposit_intents',
        'authority_verdicts',
        'validator_attestations',
        'sovereign_balance_requests',
        'sovereign_ledger',
        'entry_signatures',
        'tokenized_vouchers',
        'wallet_ledger_entries',
        'wallet_accounts',
        'storefront_favorites',
        'marketplace_favorites',
        'customers',
        'catalog_search_logs',
        'meanly_analytics_events',
        'meanly_operational_alerts',
        'token_metering_events',
        'mutation_guard_entries',
        'notifications',
        'exports',
        'failed_import_rows',
        'imports',
        'zero_layer_signals',
        'zero_layer_integrations',
        'legal_entity_managers',
        'legal_entities',
        'sellers',
        'passkeys',
        'simple_l1_identity_keys',
        'sessions',
        'model_has_permissions',
        'model_has_roles',
        'users',
        'failed_jobs',
        'jobs',
        'job_batches',
    ];

    public function handle(): int
    {
        if (! $this->option('force') && ! app()->environment(['local', 'testing'])) {
            $this->error('Refusing to reset accounts outside local/testing. Pass --force if you are sure.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Delete ALL users, legal entities, shops, orders and seller products?', false)) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        $this->info('Resetting dev accounts and commerce data…');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($this->truncateTables as $table) {
            if (! $this->tableExists($table)) {
                $this->line("  skip missing table: {$table}");

                continue;
            }

            DB::table($table)->truncate();
            $this->line("  truncated {$table}");
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->clearGeneratedMedia();

        if (! $this->option('keep-agreements')) {
            $this->call('db:seed', ['--class' => AgreementSeeder::class, '--force' => true]);
        }

        $this->newLine();
        $this->info('Dev reset complete.');
        $this->table(
            ['Kept', 'Count'],
            [
                ['wildflow_catalogs', (string) DB::table('wildflow_catalogs')->count()],
                ['brands', (string) DB::table('brands')->count()],
                ['provider_products', (string) DB::table('provider_products')->count()],
                ['users', (string) DB::table('users')->count()],
                ['legal_entities', (string) DB::table('legal_entities')->count()],
                ['shops', (string) DB::table('shops')->count()],
                ['products', (string) DB::table('products')->count()],
            ]
        );
        $this->line('Next: log in with a fresh SL1E wallet → /business/register → partner onboarding.');

        return self::SUCCESS;
    }

    private function tableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }

    private function clearGeneratedMedia(): void
    {
        $cardRoot = public_path('img/card');
        if (is_dir($cardRoot)) {
            File::deleteDirectory($cardRoot);
            File::ensureDirectoryExists($cardRoot);
            $this->line('  cleared public/img/card/');
        }
    }
}
