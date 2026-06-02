<?php

namespace App\Console\Commands;

use App\Models\LegalEntity;
use App\Models\ProjectionRebuildRegistry;
use App\Services\Continuity\ProjectionRebuildRegistryService;
use App\Services\L1StateService;
use Illuminate\Console\Command;

class VerifyMarketplaceBalancesCommand extends Command
{
    protected $signature = 'marketplace:verify-balances
        {--legal-entity= : Verify a single legal entity projection}
        {--json : Output machine-readable JSON}';

    protected $description = 'Verify partner balance projection columns against ledger-derived L1 state.';

    public function handle(L1StateService $stateService, ProjectionRebuildRegistryService $registry): int
    {
        $registry->ensureDefaults();

        $query = LegalEntity::query();
        if ($this->option('legal-entity')) {
            $query->whereKey((int) $this->option('legal-entity'));
        }

        $rows = [];
        $mismatches = 0;

        $query->orderBy('id')->chunkById(100, function ($entities) use ($stateService, &$rows, &$mismatches) {
            foreach ($entities as $entity) {
                $projection = $stateService->reconstructBalance($entity);
                $expected = [
                    'available_balance' => $this->money($projection['available_balance'], 2),
                    'reserved_balance' => $this->money($projection['reserved_balance'], 2),
                    'native_token_balance' => $this->money($projection['native_available_balance'], 4),
                    'native_token_reserved' => $this->money($projection['native_reserved_balance'], 4),
                ];
                $actual = [
                    'available_balance' => $this->money($entity->available_balance, 2),
                    'reserved_balance' => $this->money($entity->reserved_balance, 2),
                    'native_token_balance' => $this->money($entity->native_token_balance, 4),
                    'native_token_reserved' => $this->money($entity->native_token_reserved, 4),
                ];
                $matches = $expected === $actual;

                if (! $matches) {
                    $mismatches++;
                }

                $rows[] = [
                    'legal_entity_id' => $entity->id,
                    'matches' => $matches,
                    'expected' => $expected,
                    'actual' => $actual,
                    'blocks_processed' => $projection['blocks_processed'],
                    'integrity_secured' => $projection['integrity_secured'],
                ];
            }
        });

        $status = $mismatches === 0 ? ProjectionRebuildRegistry::RESULT_HEALTHY : ProjectionRebuildRegistry::RESULT_FAILED;
        $registry->markVerified(
            projectionName: 'balances_projection',
            verificationResult: $status,
            sourceRevision: 'legal_entities:'.count($rows),
            metadata: ['mismatches' => $mismatches],
        );

        $payload = [
            'status' => $mismatches === 0 ? 'OK' : 'FAILED',
            'entities_checked' => count($rows),
            'mismatches' => $mismatches,
            'rows' => $rows,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->line('Balance projection verification: '.$payload['status']);
            $this->table(
                ['Legal Entity', 'Matches', 'Blocks', 'Integrity'],
                collect($rows)->map(fn (array $row): array => [
                    $row['legal_entity_id'],
                    $row['matches'] ? 'yes' : 'no',
                    $row['blocks_processed'],
                    $row['integrity_secured'] ? 'yes' : 'no',
                ])->all(),
            );
        }

        return $mismatches === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function money(mixed $value, int $scale): string
    {
        return number_format((float) $value, $scale, '.', '');
    }
}
