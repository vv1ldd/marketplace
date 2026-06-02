<?php

namespace App\Console\Commands;

use App\Models\LegalEntity;
use App\Models\ProjectionRebuildRegistry;
use App\Services\Continuity\ProjectionRebuildRegistryService;
use App\Services\L1StateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RebuildMarketplaceBalancesCommand extends Command
{
    protected $signature = 'marketplace:rebuild-balances
        {--legal-entity= : Rebuild a single legal entity projection}
        {--dry-run : Calculate without writing projection columns}
        {--json : Output machine-readable JSON}';

    protected $description = 'Rebuild partner balance projection columns from the ledger-derived L1 state.';

    public function handle(L1StateService $stateService, ProjectionRebuildRegistryService $registry): int
    {
        $registry->ensureDefaults();

        $query = LegalEntity::query();
        if ($this->option('legal-entity')) {
            $query->whereKey((int) $this->option('legal-entity'));
        }

        $rows = [];
        $updated = 0;

        $query->orderBy('id')->chunkById(100, function ($entities) use ($stateService, &$rows, &$updated) {
            foreach ($entities as $entity) {
                $projection = $stateService->reconstructBalance($entity);
                $row = [
                    'legal_entity_id' => $entity->id,
                    'available_balance' => $projection['available_balance'],
                    'reserved_balance' => $projection['reserved_balance'],
                    'native_token_balance' => $projection['native_available_balance'],
                    'native_token_reserved' => $projection['native_reserved_balance'],
                    'blocks_processed' => $projection['blocks_processed'],
                    'integrity_secured' => $projection['integrity_secured'],
                ];

                if (! $this->option('dry-run')) {
                    LegalEntity::withoutEvents(function () use ($entity, $projection): void {
                        $entity->forceFill([
                            'available_balance' => $projection['available_balance'],
                            'reserved_balance' => $projection['reserved_balance'],
                            'balance' => $projection['total_balance'],
                            'native_token_balance' => $projection['native_available_balance'],
                            'native_token_reserved' => $projection['native_reserved_balance'],
                        ])->save();
                    });
                    $updated++;
                }

                $rows[] = $row;
            }
        });

        if (! $this->option('dry-run') && Schema::hasTable('projection_rebuild_registry')) {
            ProjectionRebuildRegistry::query()
                ->where('projection_name', 'balances_projection')
                ->update([
                    'last_rebuilt_at' => now(),
                    'source_revision' => 'legal_entities:'.count($rows),
                    'updated_at' => now(),
                ]);
        }

        $payload = [
            'status' => 'OK',
            'dry_run' => (bool) $this->option('dry-run'),
            'entities_processed' => count($rows),
            'entities_updated' => $updated,
            'rows' => $rows,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->info('Balance projection rebuild complete.');
            $this->table(
                ['Legal Entity', 'Available', 'Reserved', 'SL1 Available', 'SL1 Reserved', 'Blocks', 'Integrity'],
                collect($rows)->map(fn (array $row): array => [
                    $row['legal_entity_id'],
                    $row['available_balance'],
                    $row['reserved_balance'],
                    $row['native_token_balance'],
                    $row['native_token_reserved'],
                    $row['blocks_processed'],
                    $row['integrity_secured'] ? 'yes' : 'no',
                ])->all(),
            );
        }

        return self::SUCCESS;
    }
}
