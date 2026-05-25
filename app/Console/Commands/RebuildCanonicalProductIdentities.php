<?php

namespace App\Console\Commands;

use App\Services\CanonicalProductIdentityIndexService;
use Illuminate\Console\Command;

class RebuildCanonicalProductIdentities extends Command
{
    protected $signature = 'catalog:rebuild-identities
                            {--limit= : Limit provider and seller products processed}
                            {--dry-run : Compute identities without writing database rows}';

    protected $description = 'Rebuild the persisted canonical product identity index';

    public function handle(CanonicalProductIdentityIndexService $index): int
    {
        $limit = $this->option('limit');
        $limit = $limit !== null && $limit !== '' ? max(1, (int) $limit) : null;
        $dryRun = (bool) $this->option('dry-run');

        $stats = $index->rebuild($limit, $dryRun);

        if (! empty($stats['missing_tables'])) {
            $this->warn('Canonical product identity tables do not exist yet. Run migrations before rebuilding the persisted index.');

            return self::FAILURE;
        }

        $this->info(($dryRun ? 'Dry run complete.' : 'Canonical product identity index rebuilt.'));
        $this->line('Identities touched: '.$stats['identities_touched']);
        $this->line('Identity groups discovered: '.$stats['identity_groups']);
        $this->line('Provider sources: '.$stats['provider_sources']);
        $this->line('Seller sources: '.$stats['seller_sources']);
        $this->line('Low confidence sources: '.$stats['low_confidence_sources']);
        $this->line('Skipped without fingerprint: '.$stats['skipped_no_fingerprint']);

        return self::SUCCESS;
    }
}
