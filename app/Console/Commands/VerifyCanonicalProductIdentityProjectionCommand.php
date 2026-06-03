<?php

namespace App\Console\Commands;

use App\Models\ProjectionRebuildRegistry;
use App\Services\Continuity\ProjectionRebuildRegistryService;
use App\Services\Projections\CatalogProjectionVerificationService;
use Illuminate\Console\Command;

class VerifyCanonicalProductIdentityProjectionCommand extends Command
{
    protected $signature = 'catalog:verify-identities {--json : Output machine-readable JSON}';

    protected $description = 'Verify canonical product identity projection integrity.';

    public function handle(CatalogProjectionVerificationService $verification, ProjectionRebuildRegistryService $registry): int
    {
        $registry->ensureDefaults();

        $result = $verification->verifyIdentities();
        $registry->markVerified(
            projectionName: 'canonical_product_identity_projection',
            verificationResult: $this->registryResult($result['status']),
            sourceRevision: $result['source_revision'],
            metadata: [
                'identities_total' => $result['identities_total'],
                'sources_total' => $result['sources_total'],
                'orphan_sources' => $result['orphan_sources'],
            ],
        );

        $this->writeResult($result);

        return $result['status'] === 'OK' ? self::SUCCESS : self::FAILURE;
    }

    private function registryResult(string $status): string
    {
        return match ($status) {
            'OK' => ProjectionRebuildRegistry::RESULT_HEALTHY,
            'SOURCE_GAP' => ProjectionRebuildRegistry::RESULT_SOURCE_GAP,
            default => ProjectionRebuildRegistry::RESULT_FAILED,
        };
    }

    /**
     * @param array<string, mixed> $result
     */
    private function writeResult(array $result): void
    {
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return;
        }

        $this->line('Canonical product identity projection verification: '.$result['status']);
        $this->line('identities_total: '.$result['identities_total']);
        $this->line('sources_total: '.$result['sources_total']);
        $this->line('orphan_sources: '.$result['orphan_sources']);
    }
}
