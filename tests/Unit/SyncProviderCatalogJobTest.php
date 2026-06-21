<?php

namespace Tests\Unit;

use App\Jobs\SyncProviderCatalogJob;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncProviderCatalogJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_supply_authority_sync_publishes_to_global_catalog_before_search_rebuild(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('app:sync-catalogs', \Mockery::on(function (array $args) use (&$providerId): bool {
                return ($args['provider'] ?? null) === $providerId
                    && ($args['--force'] ?? false) === true
                    && ($args['--pull-upstream'] ?? false) === true;
            }))
            ->andReturn(0);

        Artisan::shouldReceive('call')
            ->once()
            ->with('meanly:publish-provider-catalog', [
                '--provider' => 'wildflow',
                '--rebuild-identities' => true,
            ])
            ->andReturn(0);

        Artisan::shouldReceive('call')
            ->once()
            ->with('marketplace:rebuild-catalog-search')
            ->andReturn(0);

        $provider = Provider::query()->updateOrCreate(
            ['type' => 'wildflow'],
            [
                'name' => 'EZPin',
                'is_active' => true,
                'sync_status' => 'idle',
            ],
        );
        $providerId = $provider->id;

        (new SyncProviderCatalogJob(
            providerId: $provider->id,
            embedded: false,
            pullUpstream: true,
        ))->handle();

        $this->assertSame('idle', $provider->fresh()->sync_status);
        $this->assertNotNull($provider->fresh()->last_sync_at);
    }
}
