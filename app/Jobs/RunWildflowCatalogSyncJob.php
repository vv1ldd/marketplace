<?php

namespace App\Jobs;

use App\Models\Provider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Долгий импорт каталога Wildflow (app:wildflow-parser) — не вызывать из HTTP-синхронно.
 */
class RunWildflowCatalogSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $providerId) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('wildflow-catalog-parser'))->releaseAfter(600)];
    }

    public function handle(): void
    {
        $provider = Provider::query()->find($this->providerId);
        if (! $provider || $provider->type !== 'wildflow') {
            Log::warning('RunWildflowCatalogSyncJob: provider missing or not wildflow', ['id' => $this->providerId]);

            return;
        }

        // 🚀 CALLING THE REAL UNIFIED MACHINE!
        $exit = Artisan::call('app:sync-catalogs', [
            'provider' => 'wildflow',
            '--force' => true
        ]);

        if ($exit !== 0) {
            throw new \RuntimeException('app:sync-catalogs exited with code '.$exit);
        }

        $provider->update(['last_sync_at' => now()]);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('RunWildflowCatalogSyncJob failed', [
            'provider_id' => $this->providerId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
