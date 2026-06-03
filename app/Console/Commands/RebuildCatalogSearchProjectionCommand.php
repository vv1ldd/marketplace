<?php

namespace App\Console\Commands;

use App\Services\Continuity\ProjectionRebuildRegistryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RebuildCatalogSearchProjectionCommand extends Command
{
    protected $signature = 'marketplace:rebuild-catalog-search
        {--stale : Rebuild stale search profiles only after identity rebuild}
        {--json : Output machine-readable JSON}';

    protected $description = 'Rebuild the catalog search aggregate projection via concrete canonical projections.';

    public function handle(ProjectionRebuildRegistryService $registry): int
    {
        $registry->ensureDefaults();

        $identityExit = Artisan::call('catalog:rebuild-identities');
        $identityOutput = Artisan::output();

        $profileArgs = $this->option('stale') ? ['--stale' => true] : [];
        $profileExit = Artisan::call('search-profile:rebuild', $profileArgs);
        $profileOutput = Artisan::output();

        $result = [
            'status' => ($identityExit === self::SUCCESS && $profileExit === self::SUCCESS) ? 'OK' : 'FAILED',
            'components' => [
                'canonical_product_identity_projection' => [
                    'exit_code' => $identityExit,
                    'output' => trim($identityOutput),
                ],
                'canonical_product_search_profile_projection' => [
                    'exit_code' => $profileExit,
                    'output' => trim($profileOutput),
                ],
            ],
        ];

        if ($result['status'] === 'OK') {
            $registry->markRebuilt(
                projectionName: 'catalog_search_projection',
                sourceRevision: 'split:canonical_product_identity_projection+canonical_product_search_profile_projection',
                metadata: ['split_components' => array_keys($result['components'])],
            );
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->line('Catalog search aggregate projection rebuild: '.$result['status']);
        }

        return $result['status'] === 'OK' ? self::SUCCESS : self::FAILURE;
    }
}
