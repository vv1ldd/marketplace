<?php

namespace App\Console\Commands;

use App\Models\CanonicalProductIdentitySource;
use App\Services\CanonicalProductIdentityCurationService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ReviewCanonicalProductIdentities extends Command
{
    protected $signature = 'catalog:review-identities
                            {--limit=20 : Maximum review queue rows to show}
                            {--source-type= : Restrict to a source type, e.g. provider_product}
                            {--source-id= : Restrict to a provider or seller product source id}
                            {--json : Emit queue rows as JSON}';

    protected $description = 'List canonical product identities that need curation review';

    public function handle(CanonicalProductIdentityCurationService $curation): int
    {
        $limit = max(1, (int) ($this->option('limit') ?: 20));
        $sourceId = $this->option('source-id');
        $sourceId = $sourceId !== null && $sourceId !== '' ? (int) $sourceId : null;
        $sourceType = $this->option('source-type');
        $sourceType = $sourceType !== null && $sourceType !== ''
            ? (string) $sourceType
            : ($sourceId !== null ? CanonicalProductIdentitySource::SOURCE_PROVIDER_PRODUCT : null);

        $rows = $curation->reviewQueue($limit, $sourceType, $sourceId);

        if ((bool) $this->option('json')) {
            $this->output->write(json_encode($rows->values()->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);

            return self::SUCCESS;
        }

        $this->info('Canonical product identity review queue');
        if ($sourceId !== null || $sourceType !== null) {
            $this->line('Filter: '.trim((string) $sourceType.':'.(string) $sourceId, ':'));
        }

        if ($rows->isEmpty()) {
            $this->line('No identities currently match the review queue filters.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'fingerprint', 'slug', 'confidence', 'brand', 'family', 'robots', 'surface', 'override', 'reasons', 'sources'],
            $rows
                ->map(fn (array $row) => [
                    'id' => $row['id'],
                    'fingerprint' => $row['fingerprint'],
                    'slug' => $this->clip($row['slug'] ?? null, 40),
                    'confidence' => $row['confidence'],
                    'brand' => $this->clip($row['brand'] ?? null, 24),
                    'family' => $this->clip($row['family'] ?? null, 32),
                    'robots' => $row['robots'],
                    'surface' => $row['surface'],
                    'override' => $row['override_status'] ?? '-',
                    'reasons' => $this->clip(implode('; ', (array) $row['review_reasons']), 88),
                    'sources' => $this->clip(implode(', ', (array) $row['sources']), 80),
                ])
                ->all(),
        );

        return self::SUCCESS;
    }

    private function clip(mixed $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        return Str::limit((string) $value, $limit, '...');
    }
}
