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

class SyncProviderCatalogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200; // Increased for large source catalogs
    public int $tries = 1;

    public function __construct(public int $providerId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping("sync-provider-{$this->providerId}"))->releaseAfter(1200)];
    }

    public function handle(): void
    {
        $provider = Provider::find($this->providerId);
        
        if (!$provider || !$provider->is_active) {
            Log::warning("SyncProviderCatalogJob: Provider not found or inactive", ['id' => $this->providerId]);
            return;
        }

        $provider->update(['sync_status' => 'syncing']);

        try {
            $this->runSync($provider);
            $provider->update([
                'sync_status' => 'idle',
                'last_sync_at' => now(),
            ]);
        } catch (\Throwable $th) {
            $provider->update(['sync_status' => 'idle']);
            throw $th;
        }
    }

    protected function runSync(Provider $provider): void
    {
        if ($provider->type === 'playstation') {
            Artisan::call('ps:sync-to-products');
        } elseif ($provider->type === 'playstation_us') {
            Artisan::call('ps:sync-region');
        } else {
            // Digital Goods Source and embedded projections are handled by the unified aggregator sync.
            Artisan::call('app:sync-catalogs', [
                'provider' => $provider->id,
                '--force' => true
            ]);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('SyncProviderCatalogJob failed', [
            'provider_id' => $this->providerId,
            'message' => $exception?->getMessage(),
        ]);
        
        $provider = Provider::find($this->providerId);
        if ($provider) {
            $provider->update(['sync_status' => 'idle']);
        }
    }
}
