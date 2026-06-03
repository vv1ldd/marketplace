<?php

namespace App\Console\Commands;

use App\Models\ProjectionRebuildRegistry;
use App\Services\Continuity\ProjectionRebuildRegistryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class VerifyCatalogSearchProjectionCommand extends Command
{
    protected $signature = 'marketplace:verify-catalog-search {--json : Output machine-readable JSON}';

    protected $description = 'Verify the catalog search aggregate projection through concrete canonical projections.';

    public function handle(ProjectionRebuildRegistryService $registry): int
    {
        $registry->ensureDefaults();

        $identityExit = Artisan::call('catalog:verify-identities', ['--json' => true]);
        $identityPayload = json_decode(trim(Artisan::output()), true) ?: ['status' => 'FAILED'];

        $profileExit = Artisan::call('search-profile:verify', ['--json' => true]);
        $profilePayload = json_decode(trim(Artisan::output()), true) ?: ['status' => 'FAILED'];

        $ok = $identityExit === self::SUCCESS && $profileExit === self::SUCCESS;
        $result = [
            'status' => $ok ? 'OK' : 'FAILED',
            'components' => [
                'canonical_product_identity_projection' => $identityPayload,
                'canonical_product_search_profile_projection' => $profilePayload,
            ],
        ];

        $registry->markVerified(
            projectionName: 'catalog_search_projection',
            verificationResult: $ok
                ? ProjectionRebuildRegistry::RESULT_HEALTHY
                : ProjectionRebuildRegistry::RESULT_FAILED,
            sourceRevision: 'split:canonical_product_identity_projection+canonical_product_search_profile_projection',
            metadata: ['split_components' => array_keys($result['components'])],
        );

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->line('Catalog search aggregate projection verification: '.$result['status']);
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
