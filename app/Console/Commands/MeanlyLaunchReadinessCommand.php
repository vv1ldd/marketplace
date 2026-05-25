<?php

namespace App\Console\Commands;

use App\Models\DemandGap;
use App\Models\LegalEntityMigrationPill;
use App\Models\MeanlyOperationalAlert;
use App\Models\OpportunityCase;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Throwable;

class MeanlyLaunchReadinessCommand extends Command
{
    protected $signature = 'meanly:launch-readiness
        {--quick : Skip optional expensive checks}
        {--run-tests : Run targeted launch-readiness tests}
        {--run-demand : Recalculate demand gaps before checking counters}
        {--domain= : Run Gate 2 deployment readiness against this public domain}';

    protected $description = 'Run Meanly production readiness checks across migrations, discovery, demand, routes, queues, alerts, and smoke prerequisites.';

    /** @var array<int, array{name:string,status:string,detail:string}> */
    private array $checks = [];

    public function handle(): int
    {
        $this->info('Meanly Launch Readiness');
        $this->line('------------------------');

        $this->checkMigrationsPretend();
        $this->checkDiscoveryHealth();
        $this->checkStorefrontInventory();
        $this->checkAdminPasskeyReadiness();
        $this->checkSeoReadiness();
        $this->checkDeploymentReadiness();
        $this->checkDemandAndOpportunity();
        $this->checkRoutes();
        $this->checkSitemapsAndLlms();
        $this->checkQueues();
        $this->checkAlerts();

        if ($this->option('run-demand')) {
            $this->runDemand();
        }

        if ($this->option('run-tests')) {
            $this->runTargetedTests();
        }

        $this->renderSummary();

        return collect($this->checks)->contains(fn (array $check): bool => $check['status'] === 'fail')
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function checkMigrationsPretend(): void
    {
        try {
            $code = Artisan::call('migrate', [
                '--pretend' => true,
                '--no-interaction' => true,
            ]);
            $output = trim(Artisan::output());

            $this->addCheck(
                'migrate --pretend',
                $code === 0 ? 'pass' : 'fail',
                $output !== '' ? $this->oneLine($output) : 'No pending migration SQL emitted.',
            );
        } catch (Throwable $e) {
            $this->addCheck('migrate --pretend', 'fail', $e->getMessage());
        }
    }

    private function checkDiscoveryHealth(): void
    {
        try {
            $command = app(DiscoveryHealthCommand::class);
            $stats = $command->stats();

            $this->addCheck(
                'discovery health',
                ((int) $stats['broken'] === 0 && (int) $stats['canonical_identities'] > 0) ? 'pass' : 'fail',
                isset($stats['checks']['missing_tables'])
                    ? 'Missing tables: '.$stats['checks']['missing_tables']
                    : "Brands {$stats['brands']}, regions {$stats['regions']}, intersections {$stats['intersections']}, broken {$stats['broken']}",
            );
        } catch (Throwable $e) {
            $this->addCheck('discovery health', 'fail', $e->getMessage());
        }
    }

    private function checkDemandAndOpportunity(): void
    {
        try {
            $missingTables = collect(['demand_gaps', 'opportunity_cases'])
                ->reject(fn (string $table): bool => Schema::hasTable($table))
                ->values();

            if ($missingTables->isNotEmpty()) {
                $this->addCheck('demand/opportunity counters', 'fail', 'Missing tables: '.$missingTables->implode(', '));

                return;
            }

            $demandCount = DemandGap::query()->count();
            $caseCounts = OpportunityCase::query()
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');
            $overdue = OpportunityCase::query()
                ->whereIn('status', [OpportunityCase::STATUS_OPEN, OpportunityCase::STATUS_IN_PROGRESS])
                ->whereNotNull('sla_due_at')
                ->where('sla_due_at', '<', now())
                ->count();

            $this->addCheck(
                'demand/opportunity counters',
                $demandCount > 0 ? 'pass' : 'warn',
                'demand_gaps='.$demandCount
                    .', open='.(int) ($caseCounts[OpportunityCase::STATUS_OPEN] ?? 0)
                    .', in_progress='.(int) ($caseCounts[OpportunityCase::STATUS_IN_PROGRESS] ?? 0)
                    .', resolved='.(int) ($caseCounts[OpportunityCase::STATUS_RESOLVED] ?? 0)
                    .', overdue='.$overdue,
            );
        } catch (Throwable $e) {
            $this->addCheck('demand/opportunity counters', 'fail', $e->getMessage());
        }
    }

    private function checkStorefrontInventory(): void
    {
        try {
            $missingTables = collect(['products', 'product_sales_channels', 'shops', 'canonical_product_identities'])
                ->reject(fn (string $table): bool => Schema::hasTable($table))
                ->values();

            if ($missingTables->isNotEmpty()) {
                $this->addCheck('storefront inventory', 'fail', 'Missing tables: '.$missingTables->implode(', '));

                return;
            }

            $channel = (string) config('meanly_storefront.channels.storefront', 'meanly_storefront');
            $activeProducts = DB::table('products')
                ->join('shops', 'shops.id', '=', 'products.shop_id')
                ->where('products.is_active', true)
                ->where('shops.is_active', true)
                ->count();
            $enabledChannelProducts = DB::table('product_sales_channels')
                ->join('products', 'products.id', '=', 'product_sales_channels.product_id')
                ->join('shops', 'shops.id', '=', 'product_sales_channels.shop_id')
                ->where('product_sales_channels.channel', $channel)
                ->where('product_sales_channels.is_enabled', true)
                ->where('products.is_active', true)
                ->where('shops.is_active', true)
                ->count();
            $sellerOfferIdentities = DB::table('canonical_product_identities')
                ->where('seller_offers_count', '>', 0)
                ->whereNotNull('best_offer_product_id')
                ->count();

            $status = $activeProducts > 0 && $enabledChannelProducts > 0 && $sellerOfferIdentities > 0
                ? 'pass'
                : 'fail';

            $this->addCheck(
                'storefront inventory',
                $status,
                "active_products={$activeProducts}, {$channel}={$enabledChannelProducts}, seller_offer_identities={$sellerOfferIdentities}",
            );
        } catch (Throwable $e) {
            $this->addCheck('storefront inventory', 'fail', $e->getMessage());
        }
    }

    private function checkAdminPasskeyReadiness(): void
    {
        try {
            $adminCount = User::role('super_admin')->count();
            $adminPasskeys = User::role('super_admin')
                ->whereHas('passkeys')
                ->count();
            $activeAdminPills = LegalEntityMigrationPill::query()
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->get()
                ->filter(fn (LegalEntityMigrationPill $pill): bool => data_get($pill->metadata, 'purpose') === 'main_admin_passkey_enrollment')
                ->count();

            $this->addCheck(
                'admin passkey',
                $adminPasskeys > 0 || $activeAdminPills > 0 ? 'pass' : 'warn',
                $adminPasskeys > 0
                    ? "super_admins={$adminCount}, admin_passkeys={$adminPasskeys}"
                    : "super_admins={$adminCount}, admin_passkeys=0, active_admin_pills={$activeAdminPills}",
            );
        } catch (Throwable $e) {
            $this->addCheck('admin passkey', 'warn', $e->getMessage());
        }
    }

    private function checkSeoReadiness(): void
    {
        try {
            $code = Artisan::call('meanly:seo-readiness', [
                '--json' => true,
            ]);
            $payload = json_decode(trim(Artisan::output()), true);

            if (! is_array($payload)) {
                $this->addCheck('seo readiness', 'fail', 'Unable to parse meanly:seo-readiness output.');

                return;
            }

            $status = (string) ($payload['status'] ?? 'NO GO');
            $checks = collect($payload['checks'] ?? []);
            $failed = $checks->where('status', 'fail')->pluck('name')->values();
            $warnings = $checks->where('status', 'warn')->pluck('name')->values();

            $this->addCheck(
                'seo readiness',
                $status === 'NO GO' || $code !== 0 ? 'fail' : ($status === 'CONDITIONAL GO' ? 'warn' : 'pass'),
                $status === 'READY'
                    ? 'Gate 1 passed: URL health, metadata, JSON-LD, sitemap XML, LLM, discovery, and search health.'
                    : 'status='.$status
                        .($failed->isNotEmpty() ? '; failed='.$failed->implode(', ') : '')
                        .($warnings->isNotEmpty() ? '; warnings='.$warnings->implode(', ') : ''),
            );
        } catch (Throwable $e) {
            $this->addCheck('seo readiness', 'fail', $e->getMessage());
        }
    }

    private function checkDeploymentReadiness(): void
    {
        $domain = trim((string) $this->option('domain'));
        if ($domain === '') {
            return;
        }

        try {
            $code = Artisan::call('meanly:deployment-readiness', [
                '--domain' => $domain,
                '--json' => true,
            ]);
            $payload = json_decode(trim(Artisan::output()), true);

            if (! is_array($payload)) {
                $this->addCheck('deployment readiness', 'fail', 'Unable to parse meanly:deployment-readiness output.');

                return;
            }

            $status = (string) ($payload['status'] ?? 'NO GO');
            $checks = collect($payload['checks'] ?? []);
            $failed = $checks->where('status', 'fail')->pluck('name')->values();
            $warnings = $checks->where('status', 'warn')->pluck('name')->values();

            $this->addCheck(
                'deployment readiness',
                $status === 'NO GO' || $code !== 0 ? 'fail' : ($status === 'CONDITIONAL GO' ? 'warn' : 'pass'),
                $status === 'READY'
                    ? 'Gate 2 passed: HTTPS, robots, sitemap, canonical domain, headers, and LLM JSON.'
                    : 'status='.$status
                        .($failed->isNotEmpty() ? '; failed='.$failed->implode(', ') : '')
                        .($warnings->isNotEmpty() ? '; warnings='.$warnings->implode(', ') : ''),
            );
        } catch (Throwable $e) {
            $this->addCheck('deployment readiness', 'fail', $e->getMessage());
        }
    }

    private function checkRoutes(): void
    {
        $requiredRoutes = [
            'home',
            'meanly.catalog.index',
            'meanly.catalog.categories.show',
            'meanly.catalog.brands.show',
            'meanly.catalog.regions.show',
            'meanly.catalog.brand-regions.show',
            'meanly.catalog.collections.show',
            'meanly.canonical-products.show',
            'meanly.storefront.products.show',
            'meanly.storefront.checkout',
            'meanly.storefront.orders.safe.show',
            'storefront.ai-chat',
            'storefront.chat',
        ];

        $missing = collect($requiredRoutes)
            ->reject(fn (string $route): bool => Route::has($route))
            ->values()
            ->all();

        $this->addCheck(
            'route names',
            $missing === [] ? 'pass' : 'fail',
            $missing === [] ? 'Required routes are registered.' : 'Missing: '.implode(', ', $missing),
        );
    }

    private function checkSitemapsAndLlms(): void
    {
        $requiredRoutes = [
            'sitemap.index',
            'sitemap.products',
            'sitemap.brands',
            'sitemap.regions',
            'sitemap.brand-regions',
            'llms.catalog.index',
            'llms.commerce.opportunities',
        ];

        $missing = collect($requiredRoutes)
            ->reject(fn (string $route): bool => Route::has($route))
            ->values()
            ->all();

        $this->addCheck(
            'sitemap/llms routes',
            $missing === [] ? 'pass' : 'fail',
            $missing === [] ? 'Sitemap and LLM route names are registered.' : 'Missing: '.implode(', ', $missing),
        );
    }

    private function checkQueues(): void
    {
        try {
            if (! Schema::hasTable('failed_jobs')) {
                $this->addCheck('failed_jobs', 'warn', 'failed_jobs table is missing.');

                return;
            }

            $failedJobs = DB::table('failed_jobs')->count();

            $this->addCheck(
                'failed_jobs',
                $failedJobs === 0 ? 'pass' : 'warn',
                "failed_jobs={$failedJobs}",
            );
        } catch (Throwable $e) {
            $this->addCheck('failed_jobs', 'warn', 'failed_jobs table unavailable: '.$e->getMessage());
        }
    }

    private function checkAlerts(): void
    {
        try {
            $missingTables = collect(['meanly_operational_alerts', 'orders'])
                ->reject(fn (string $table): bool => Schema::hasTable($table))
                ->values();

            if ($missingTables->isNotEmpty()) {
                $this->addCheck('operational alerts', 'fail', 'Missing tables: '.$missingTables->implode(', '));

                return;
            }

            if (! Schema::hasColumn('orders', 'sales_channel')) {
                $this->addCheck('operational alerts', 'fail', 'Missing column: orders.sales_channel');

                return;
            }

            Artisan::call('meanly:check-alerts');
            $critical = MeanlyOperationalAlert::query()
                ->where('status', 'open')
                ->whereIn('severity', ['critical', 'error'])
                ->count();
            $warnings = MeanlyOperationalAlert::query()
                ->where('status', 'open')
                ->where('severity', 'warning')
                ->count();

            $this->addCheck(
                'operational alerts',
                $critical > 0 ? 'fail' : ($warnings > 0 ? 'warn' : 'pass'),
                "critical={$critical}, warnings={$warnings}",
            );
        } catch (Throwable $e) {
            $this->addCheck('operational alerts', 'fail', $e->getMessage());
        }
    }

    private function runDemand(): void
    {
        try {
            $code = Artisan::call('demand:calculate-gaps');
            $this->addCheck(
                'demand:calculate-gaps',
                $code === 0 ? 'pass' : 'fail',
                $this->oneLine(Artisan::output()),
            );
        } catch (Throwable $e) {
            $this->addCheck('demand:calculate-gaps', 'fail', $e->getMessage());
        }
    }

    private function runTargetedTests(): void
    {
        $tests = [
            'tests/Feature/MeanlyFirstPartyStorefrontTest.php',
            'tests/Feature/PasskeyStorefrontCheckoutTest.php',
            'tests/Feature/ProviderProductSellerCatalogTest.php',
            'tests/Feature/QueryNormalizationTest.php',
            'tests/Feature/DiscoveryEntityGraphTest.php',
            'tests/Feature/DemandGapTest.php',
            'tests/Feature/OpportunityGraphApiTest.php',
            'tests/Feature/MarketplaceAiDiscoveryTest.php',
            'tests/Feature/SearchIntentLoggingTest.php',
            'tests/Feature/StorefrontCatalogGroupTest.php',
            'tests/Feature/SandboxWildflowRedeemE2eTest.php',
            'tests/Feature/WildflowServiceContractTest.php',
        ];

        try {
            $process = new Process(array_merge(['./vendor/bin/phpunit'], $tests), base_path(), [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => ':memory:',
                'CACHE_STORE' => 'array',
                'QUEUE_CONNECTION' => 'sync',
                'SESSION_DRIVER' => 'array',
            ]);
            $process->setTimeout(120);
            $process->run();

            $this->addCheck(
                'targeted tests',
                $process->isSuccessful() ? 'pass' : 'fail',
                $process->isSuccessful()
                    ? $this->oneLine($process->getOutput())
                    : $this->oneLine($process->getErrorOutput() ?: $process->getOutput()),
            );
        } catch (Throwable $e) {
            $this->addCheck('targeted tests', 'fail', $e->getMessage());
        }
    }

    private function renderSummary(): void
    {
        $this->newLine();
        $this->table(['Check', 'Status', 'Detail'], $this->checks);

        $failed = collect($this->checks)->where('status', 'fail')->count();
        $warnings = collect($this->checks)->where('status', 'warn')->count();

        if ($failed > 0) {
            $this->error("NO-GO: {$failed} failed check(s), {$warnings} warning(s).");

            return;
        }

        if ($warnings > 0) {
            $this->warn("CONDITIONAL GO: {$warnings} warning(s) need review.");

            return;
        }

        $this->info('GO: launch readiness checks passed.');
    }

    private function addCheck(string $name, string $status, string $detail): void
    {
        $this->checks[] = [
            'name' => $name,
            'status' => $status,
            'detail' => $detail,
        ];
    }

    private function oneLine(string $value): string
    {
        return str($value)->replaceMatches('/\s+/', ' ')->limit(220)->toString();
    }
}
