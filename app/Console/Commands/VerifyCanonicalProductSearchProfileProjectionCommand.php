<?php

namespace App\Console\Commands;

use App\Models\ProjectionRebuildRegistry;
use App\Services\Continuity\ProjectionRebuildRegistryService;
use App\Services\Projections\CatalogProjectionVerificationService;
use Illuminate\Console\Command;

class VerifyCanonicalProductSearchProfileProjectionCommand extends Command
{
    protected $signature = 'search-profile:verify {--json : Output machine-readable JSON}';

    protected $description = 'Verify canonical product search profile projection freshness.';

    public function handle(CatalogProjectionVerificationService $verification, ProjectionRebuildRegistryService $registry): int
    {
        $registry->ensureDefaults();

        $result = $verification->verifySearchProfiles();
        $registry->markVerified(
            projectionName: 'canonical_product_search_profile_projection',
            verificationResult: $this->registryResult($result['status']),
            sourceRevision: $result['source_revision'],
            metadata: [
                'identities_total' => $result['identities_total'],
                'profiles_total' => $result['profiles_total'],
                'profiles_missing' => $result['profiles_missing'],
                'profiles_stale' => $result['profiles_stale'],
                'profiles_failed' => $result['profiles_failed'],
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

        $this->line('Canonical product search profile projection verification: '.$result['status']);
        $this->line('identities_total: '.$result['identities_total']);
        $this->line('profiles_total: '.$result['profiles_total']);
        $this->line('profiles_missing: '.$result['profiles_missing']);
        $this->line('profiles_stale: '.$result['profiles_stale']);
        $this->line('profiles_failed: '.$result['profiles_failed']);
    }
}
