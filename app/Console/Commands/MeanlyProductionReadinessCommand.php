<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class MeanlyProductionReadinessCommand extends Command
{
    protected $signature = 'meanly:production-readiness
        {--domain= : Optional public HTTPS domain for DNS/deployment checks}
        {--deploy-gate : Run only deploy-critical gates (Providers, DGS Sidecar, DB, Queue, Cache)}
        {--json : Output machine-readable JSON}';

    protected $description = 'Aggregate production readiness across Providers, DNS, Queue, Scheduler, DB, Cache, LLM, SEO, Storefront, and Ops.';

    /** @var array<int, array{gate:string,status:string,detail:string}> */
    private array $checks = [];

    public function handle(): int
    {
        if (! $this->option('json')) {
            $this->info('MEANLY PRODUCTION READINESS');
            $this->line('---------------------------');
        }

        if ($this->option('deploy-gate')) {
            $this->checkProviders();
            $this->checkDgsSidecar();
            $this->checkDatabase();
            $this->checkQueue();
            $this->checkCache();
            $this->render();

            return collect($this->checks)->contains(fn (array $check): bool => $check['status'] === 'fail')
                ? self::FAILURE
                : self::SUCCESS;
        }

        $this->checkProviders();
        $this->checkDgsSidecar();
        $this->checkDnsAndDeployment();
        $this->checkQueue();
        $this->checkScheduler();
        $this->checkDatabase();
        $this->checkCache();
        $this->checkLlm();
        $this->checkSeo();
        $this->checkStorefront();
        $this->checkOps();

        $this->render();

        return collect($this->checks)->contains(fn (array $check): bool => $check['status'] === 'fail')
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function checkProviders(): void
    {
        try {
            if (! Schema::hasTable('providers')) {
                $this->addCheck('Providers', 'fail', 'providers table is missing.');

                return;
            }

            $activeProviders = DB::table('providers')->where('is_active', true)->count();
            $activeProducts = Schema::hasTable('provider_products')
                ? DB::table('provider_products')->where('is_active', true)->count()
                : 0;
            $baseUrl = (string) config('services.wildflow.base_url');
            $kernelMode = (string) config('services.wildflow.kernel_mode', 'http');
            $usesLegacyRuntimeUrl = str_contains($baseUrl, 'api.wildflow.dev') || str_contains($baseUrl, 'api.wildflow.test');
            $kernelReady = $kernelMode !== 'local' && filled($baseUrl) && ! $usesLegacyRuntimeUrl;

            $status = $activeProviders > 0 && $kernelReady ? 'pass' : 'warn';

            $this->addCheck(
                'Providers',
                $status,
                "active_providers={$activeProviders}, active_provider_products={$activeProducts}, kernel_mode={$kernelMode}, base_url={$baseUrl}",
            );
        } catch (Throwable $e) {
            $this->addCheck('Providers', 'fail', $e->getMessage());
        }
    }

    private function checkDgsSidecar(): void
    {
        try {
            $mode = (string) config('services.dgs.fulfillment_mode', 'http');
            if (! in_array($mode, ['split', 'node'], true)) {
                $this->addCheck('DGS Sidecar', 'pass', "fulfillment_mode={$mode}; Node sidecar checks skipped.");

                return;
            }

            $fulfillmentUrl = rtrim((string) config('services.dgs.fulfillment_url'), '/');
            $shadowUrl = rtrim((string) config('services.dgs_shadow.ingest_url', ''), '/');
            $issues = [];

            $fulfillmentHealth = Http::timeout(5)->get("{$fulfillmentUrl}/healthcheck");
            if (! $fulfillmentHealth->successful()) {
                $issues[] = ':8091 fulfillment healthcheck failed';
            }

            if ($shadowUrl !== '') {
                $shadowBase = preg_replace('#/shadow/ingest$#', '', $shadowUrl) ?: $shadowUrl;
                $shadowHealth = Http::timeout(5)->get("{$shadowBase}/healthcheck");
                if (! $shadowHealth->successful()) {
                    $issues[] = ':8092 shadow ingest healthcheck failed';
                }
            } else {
                $issues[] = 'DGS_SHADOW_INGEST_URL is not configured';
            }

            $this->addCheck(
                'DGS Sidecar',
                $issues === [] ? 'pass' : 'fail',
                $issues === []
                    ? "fulfillment_mode={$mode}; Node sidecar endpoints healthy."
                    : implode('; ', $issues),
            );
        } catch (Throwable $e) {
            $this->addCheck('DGS Sidecar', 'fail', $e->getMessage());
        }
    }

    private function checkDnsAndDeployment(): void
    {
        $domain = trim((string) $this->option('domain'));
        if ($domain === '') {
            $appUrl = (string) config('app.url');
            $host = (string) parse_url($appUrl, PHP_URL_HOST);
            $publicDomains = (array) config('app.public_domains', []);

            $this->addCheck(
                'DNS',
                in_array($host, $publicDomains, true) ? 'warn' : 'fail',
                in_array($host, $publicDomains, true)
                    ? "APP_URL host {$host} is allowlisted. Pass --domain=https://{$host} for live HTTPS/DNS checks."
                    : "APP_URL host {$host} is not in app.public_domains.",
            );

            return;
        }

        $this->proxyJsonCommand(
            gate: 'DNS',
            command: 'meanly:deployment-readiness',
            parameters: ['--domain' => $domain, '--json' => true],
            readyStatuses: ['READY'],
        );
    }

    private function checkQueue(): void
    {
        try {
            $connection = (string) config('queue.default');
            $driver = (string) config("queue.connections.{$connection}.driver", $connection);
            $requiredTables = $driver === 'database' ? ['jobs', 'failed_jobs'] : [];
            $missing = collect($requiredTables)->reject(fn (string $table): bool => Schema::hasTable($table))->values();

            $this->addCheck(
                'Queue',
                $missing->isEmpty() ? 'pass' : 'fail',
                $missing->isEmpty()
                    ? "queue_connection={$connection}, driver={$driver}"
                    : 'Missing queue tables: '.$missing->implode(', '),
            );
        } catch (Throwable $e) {
            $this->addCheck('Queue', 'fail', $e->getMessage());
        }
    }

    private function checkScheduler(): void
    {
        $consoleRoutes = base_path('routes/console.php');
        $content = is_file($consoleRoutes) ? (string) file_get_contents($consoleRoutes) : '';
        $scheduledCommands = substr_count($content, 'Schedule::command(');

        $this->addCheck(
            'Scheduler',
            $scheduledCommands > 0 ? 'pass' : 'warn',
            $scheduledCommands > 0
                ? "scheduled_commands={$scheduledCommands}; ensure production runs php artisan schedule:run every minute."
                : 'No scheduled commands found in routes/console.php.',
        );
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            $requiredTables = ['migrations', 'users', 'shops', 'orders', 'products', 'legal_entities'];
            $missing = collect($requiredTables)->reject(fn (string $table): bool => Schema::hasTable($table))->values();

            $this->addCheck(
                'DB',
                $missing->isEmpty() ? 'pass' : 'fail',
                $missing->isEmpty()
                    ? 'Database connection and core tables are available.'
                    : 'Missing core tables: '.$missing->implode(', '),
            );
        } catch (Throwable $e) {
            $this->addCheck('DB', 'fail', $e->getMessage());
        }
    }

    private function checkCache(): void
    {
        try {
            $key = 'meanly:production-readiness:'.Str::uuid();
            Cache::put($key, 'ok', now()->addMinute());
            $ok = Cache::get($key) === 'ok';
            Cache::forget($key);

            $this->addCheck(
                'Cache',
                $ok ? 'pass' : 'fail',
                $ok ? 'Cache read/write/delete smoke check passed for store '.config('cache.default').'.' : 'Cache smoke check failed.',
            );
        } catch (Throwable $e) {
            $this->addCheck('Cache', 'fail', $e->getMessage());
        }
    }

    private function checkLlm(): void
    {
        $this->proxyJsonCommand(
            gate: 'LLM',
            command: 'meanly:llm-health',
            parameters: ['--json' => true],
            readyStatuses: ['READY'],
        );
    }

    private function checkSeo(): void
    {
        $this->proxyJsonCommand(
            gate: 'SEO',
            command: 'meanly:seo-readiness',
            parameters: ['--json' => true],
            readyStatuses: ['READY'],
            conditionalStatuses: ['CONDITIONAL GO'],
        );
    }

    private function checkStorefront(): void
    {
        try {
            $routesReady = Route::has('home')
                && Route::has('meanly.storefront.index')
                && Route::has('meanly.canonical-products.show')
                && Route::has('llms.catalog.index');
            $activeProducts = Schema::hasTable('products')
                ? DB::table('products')->where('is_active', true)->count()
                : 0;
            $canonicalIdentities = Schema::hasTable('canonical_product_identities')
                ? DB::table('canonical_product_identities')->count()
                : 0;

            $this->addCheck(
                'Storefront',
                $routesReady && $activeProducts > 0 ? 'pass' : ($routesReady ? 'warn' : 'fail'),
                "routes_ready=".($routesReady ? 'yes' : 'no').", active_products={$activeProducts}, canonical_identities={$canonicalIdentities}",
            );
        } catch (Throwable $e) {
            $this->addCheck('Storefront', 'fail', $e->getMessage());
        }
    }

    private function checkOps(): void
    {
        try {
            $routesReady = Route::has('ops.dashboard')
                && Route::has('ops.dashboard.providers.data')
                && Route::has('ops.dashboard.inventory.data')
                && Route::has('ops.dashboard.search-integrations.data');
            $sovereignValidators = Schema::hasTable('model_has_roles') && Schema::hasTable('roles')
                ? DB::table('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('roles.name', User::ROLE_SOVEREIGN_VALIDATOR)
                    ->count()
                : 0;

            $this->addCheck(
                'Ops',
                $routesReady ? ($sovereignValidators > 0 ? 'pass' : 'warn') : 'fail',
                "routes_ready=".($routesReady ? 'yes' : 'no').", sovereign_validator_assignments={$sovereignValidators}",
            );
        } catch (Throwable $e) {
            $this->addCheck('Ops', 'fail', $e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<int, string>  $readyStatuses
     * @param  array<int, string>  $conditionalStatuses
     */
    private function proxyJsonCommand(
        string $gate,
        string $command,
        array $parameters,
        array $readyStatuses,
        array $conditionalStatuses = [],
    ): void {
        try {
            $code = Artisan::call($command, $parameters);
            $payload = json_decode(trim(Artisan::output()), true);

            if (! is_array($payload)) {
                $this->addCheck($gate, 'fail', "Unable to parse {$command} JSON output.");

                return;
            }

            $status = (string) ($payload['status'] ?? ($code === 0 ? 'READY' : 'NO GO'));
            $failed = collect($payload['checks'] ?? [])->where('status', 'fail')->pluck('name')->values();
            $warnings = collect($payload['checks'] ?? [])->where('status', 'warn')->pluck('name')->values();

            $this->addCheck(
                $gate,
                in_array($status, $readyStatuses, true) && $code === 0
                    ? 'pass'
                    : (in_array($status, $conditionalStatuses, true) && $code === 0 ? 'warn' : 'fail'),
                'status='.$status
                    .($failed->isNotEmpty() ? '; failed='.$failed->implode(', ') : '')
                    .($warnings->isNotEmpty() ? '; warnings='.$warnings->implode(', ') : ''),
            );
        } catch (Throwable $e) {
            $this->addCheck($gate, 'fail', $e->getMessage());
        }
    }

    private function addCheck(string $gate, string $status, string $detail): void
    {
        $this->checks[] = [
            'gate' => $gate,
            'status' => $status,
            'detail' => Str::limit($detail, 300),
        ];
    }

    private function render(): void
    {
        $status = $this->resultStatus();

        if ($this->option('json')) {
            $this->line(json_encode([
                'status' => $status,
                'checks' => $this->checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return;
        }

        $this->newLine();
        $this->table(['Gate', 'Status', 'Detail'], $this->checks);

        match ($status) {
            'READY' => $this->info('RESULT: READY'),
            'BLOCKED' => $this->error('RESULT: BLOCKED'),
            default => $this->warn('RESULT: CONDITIONAL'),
        };
    }

    private function resultStatus(): string
    {
        $statuses = collect($this->checks)->pluck('status');

        if ($statuses->contains('fail')) {
            return 'BLOCKED';
        }

        if ($statuses->contains('warn')) {
            return 'CONDITIONAL';
        }

        return 'READY';
    }
}
