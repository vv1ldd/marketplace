<?php

namespace App\Services\Continuity;

use App\Models\MarketplaceTransitionOutbox;
use App\Models\ProjectionRebuildRegistry;
use App\Models\SovereignLedger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Meanly\Mdk\Kernel\Identity\CanonicalJsonEncoder;
use Throwable;

class ContinuityReadinessService
{
    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        app(ProjectionRebuildRegistryService::class)->ensureDefaults();

        $checks = [
            $this->writerAuthorityReadiness(),
            $this->projectionRebuildReadiness(),
            $this->ledgerContinuityReadiness(),
            $this->authorityLedgerReadiness(),
            $this->anchorVerificationReadiness(),
            $this->operationalProjectionReadiness(),
        ];
        $dbWritable = $this->dbWritableReadiness();
        $checks[] = $dbWritable;
        $balancesProjection = $this->balancesProjection();
        $writerGuard = app(WriterAuthorityGuard::class);
        $authority = $writerGuard->authority();
        $status = $this->statusFor($checks);

        return [
            'status' => $status,
            'continuity_status' => $this->continuityStatusFor($checks),
            'recovery_confidence' => $this->recoveryConfidence($checks),
            'current_region' => (string) config('mutation.region', 'local'),
            'writer_region' => $authority['writer_region'],
            'writer_epoch' => $authority['writer_epoch'],
            'writer_authority_source' => $authority['source'],
            'db_writable' => $dbWritable['status'] === 'pass',
            'failover_allowed' => $status !== 'UNHEALTHY' && $dbWritable['status'] === 'pass' && $authority['writer_region'] !== null && $authority['writer_epoch'] !== null,
            'writer_authority' => $this->operationalStatus($checks, 'WriterAuthorityReadiness'),
            'projection_rebuild' => $this->operationalStatus($checks, 'ProjectionRebuildReadiness'),
            'ledger_continuity' => $this->operationalStatus($checks, 'LedgerContinuityReadiness'),
            'authority_ledger' => $this->operationalStatus($checks, 'AuthorityLedgerReadiness'),
            'anchor_verification' => $this->operationalStatus($checks, 'AnchorVerificationReadiness'),
            'operational_projection' => $this->operationalStatus($checks, 'OperationalProjectionReadiness'),
            'last_balance_rebuild' => $balancesProjection?->last_rebuilt_at?->toJSON(),
            'last_balance_verify' => $balancesProjection?->last_verified_at?->toJSON(),
            'checks' => $checks,
        ];
    }

    /**
     * @return array{name:string,status:string,detail:string,meta:array<string, mixed>}
     */
    private function writerAuthorityReadiness(): array
    {
        $readiness = app(WriterAuthorityService::class)->readiness();

        return [
            'name' => 'WriterAuthorityReadiness',
            'status' => $readiness['status'],
            'detail' => $readiness['detail'],
            'meta' => $readiness,
        ];
    }

    /**
     * @return array{name:string,status:string,detail:string,meta:array<string, mixed>}
     */
    private function projectionRebuildReadiness(): array
    {
        if (! Schema::hasTable('projection_rebuild_registry')) {
            return $this->check('ProjectionRebuildReadiness', 'fail', 'projection_rebuild_registry table is missing.');
        }

        $rows = ProjectionRebuildRegistry::query()->get();
        $failed = $rows->whereIn('verification_result', [
            ProjectionRebuildRegistry::RESULT_FAILED,
            ProjectionRebuildRegistry::RESULT_SOURCE_GAP,
            ProjectionRebuildRegistry::RESULT_AUTHORITY_GAP,
            ProjectionRebuildRegistry::RESULT_ANCHOR_GAP,
        ])->count();
        $unknown = $rows->where('verification_result', ProjectionRebuildRegistry::RESULT_UNKNOWN)->count();
        $stale = $rows->filter(function (ProjectionRebuildRegistry $row): bool {
            return $row->last_verified_at !== null
                && $row->last_verified_at->lt(now()->subDay());
        })->count();
        $healthy = $rows->where('verification_result', ProjectionRebuildRegistry::RESULT_HEALTHY)->count();

        $status = $failed > 0 ? 'fail' : (($unknown > 0 || $stale > 0 || $healthy === 0) ? 'warn' : 'pass');

        return $this->check(
            'ProjectionRebuildReadiness',
            $status,
            "projections={$rows->count()}, healthy={$healthy}, unknown={$unknown}, stale={$stale}, failed={$failed}",
            compact('healthy', 'unknown', 'stale', 'failed'),
        );
    }

    /**
     * @return array{name:string,status:string,detail:string,meta:array<string, mixed>}
     */
    private function ledgerContinuityReadiness(): array
    {
        if (! Schema::hasTable('sovereign_ledger')) {
            return $this->check('LedgerContinuityReadiness', 'fail', 'sovereign_ledger table is missing.');
        }

        try {
            $entries = SovereignLedger::query()->orderBy('id')->get();
            if ($entries->isEmpty()) {
                return $this->check('LedgerContinuityReadiness', 'warn', 'sovereign_ledger has no entries yet.');
            }

            $errors = $this->verifyLedgerEntries($entries);
            $status = empty($errors) ? 'pass' : 'fail';

            return $this->check(
                'LedgerContinuityReadiness',
                $status,
                empty($errors) ? "ledger_entries={$entries->count()}, hash_chain=verified" : 'ledger errors: '.implode('; ', array_slice($errors, 0, 3)),
                ['errors' => $errors, 'entries' => $entries->count()],
            );
        } catch (Throwable $e) {
            return $this->check('LedgerContinuityReadiness', 'fail', $e->getMessage());
        }
    }

    /**
     * @return array{name:string,status:string,detail:string,meta:array<string, mixed>}
     */
    private function authorityLedgerReadiness(): array
    {
        $hasResourceDecisions = Schema::hasTable('resource_arbitration_decisions');
        $hasOutboxAuthority = Schema::hasTable('marketplace_transition_outbox')
            && MarketplaceTransitionOutbox::query()->whereNotNull('authority_decision_hash')->exists();

        if ($hasResourceDecisions || $hasOutboxAuthority) {
            return $this->check('AuthorityLedgerReadiness', 'pass', 'Authority decision surface is available.');
        }

        return $this->check(
            'AuthorityLedgerReadiness',
            'warn',
            'No authority decision entries detected yet; simple transitions may be valid, but disputed actions need explicit authority decisions.',
        );
    }

    /**
     * @return array{name:string,status:string,detail:string,meta:array<string, mixed>}
     */
    private function anchorVerificationReadiness(): array
    {
        if (! Schema::hasTable('marketplace_transition_outbox')) {
            return $this->check('AnchorVerificationReadiness', 'fail', 'marketplace_transition_outbox table is missing.');
        }

        $total = MarketplaceTransitionOutbox::query()->count();
        $verified = MarketplaceTransitionOutbox::query()
            ->where('anchor_status', MarketplaceTransitionOutbox::ANCHOR_VERIFIED)
            ->count();
        $failed = MarketplaceTransitionOutbox::query()
            ->where('anchor_status', MarketplaceTransitionOutbox::ANCHOR_FAILED)
            ->count();
        $pending = MarketplaceTransitionOutbox::query()
            ->where('anchor_status', MarketplaceTransitionOutbox::ANCHOR_PENDING)
            ->count();

        $status = $failed > 0 ? 'fail' : (($total === 0 || $pending > 0) ? 'warn' : 'pass');

        return $this->check(
            'AnchorVerificationReadiness',
            $status,
            "outbox_events={$total}, anchors_verified={$verified}, anchors_pending={$pending}, anchors_failed={$failed}",
            compact('total', 'verified', 'pending', 'failed'),
        );
    }

    /**
     * @return array{name:string,status:string,detail:string,meta:array<string, mixed>}
     */
    private function operationalProjectionReadiness(): array
    {
        $required = ['legal_entities', 'orders', 'wallet_accounts', 'products'];
        $missing = collect($required)->reject(fn (string $table): bool => Schema::hasTable($table))->values();

        return $this->check(
            'OperationalProjectionReadiness',
            $missing->isEmpty() ? 'pass' : 'fail',
            $missing->isEmpty()
                ? 'Core operational projection tables are available.'
                : 'Missing projection tables: '.$missing->implode(', '),
            ['missing' => $missing->all()],
        );
    }

    /**
     * @return array{name:string,status:string,detail:string,meta:array<string, mixed>}
     */
    private function dbWritableReadiness(): array
    {
        try {
            DB::transaction(function (): void {
                DB::statement('CREATE TEMPORARY TABLE IF NOT EXISTS continuity_write_probe (id INTEGER)');
                DB::statement('INSERT INTO continuity_write_probe (id) VALUES (1)');
                DB::statement('DELETE FROM continuity_write_probe');
            });

            return $this->check('DatabaseWritabilityReadiness', 'pass', 'Database accepted temporary write probe.');
        } catch (Throwable $e) {
            return $this->check('DatabaseWritabilityReadiness', 'fail', 'Database write probe failed: '.$e->getMessage());
        }
    }

    /**
     * @param  Collection<int, SovereignLedger>  $entries
     * @return array<int, string>
     */
    private function verifyLedgerEntries(Collection $entries): array
    {
        $encoder = new CanonicalJsonEncoder();
        $expectedPreviousByScope = [];
        $errors = [];

        foreach ($entries as $entry) {
            $scope = $this->ledgerScope($entry);
            $expectedPrevious = $expectedPreviousByScope[$scope] ?? null;

            if ($entry->previous_fingerprint !== $expectedPrevious) {
                $errors[] = "scope {$scope} broken at ledger {$entry->id}";
            }

            $data = [
                'prev' => $entry->previous_fingerprint,
                'type' => $entry->event_type,
                'entity_id' => (string) $entry->entity_id,
                'entity_type' => $entry->entity_type,
                'payload' => $entry->payload,
                'ts' => $entry->created_at->toDateTimeString(),
                'source' => $entry->trigger_source,
                'in' => $this->emptyArrayAsNull($entry->input_data),
                'out' => $this->emptyArrayAsNull($entry->output_state),
            ];

            $calculated = hash('sha256', $encoder->encode($data));
            if ($entry->fingerprint !== $calculated) {
                $errors[] = "fingerprint mismatch at ledger {$entry->id}";
            }

            $expectedPreviousByScope[$scope] = $entry->fingerprint;
        }

        return $errors;
    }

    private function ledgerScope(SovereignLedger $entry): string
    {
        if ($entry->legal_entity_id) {
            return 'legal_entity:'.$entry->legal_entity_id;
        }

        if ($entry->shop_id) {
            return 'shop:'.$entry->shop_id;
        }

        return 'marketplace:global';
    }

    private function emptyArrayAsNull(mixed $value): mixed
    {
        return $value === [] ? null : $value;
    }

    /**
     * @param  array<int, array{name:string,status:string,detail:string}>  $checks
     */
    private function statusFor(array $checks): string
    {
        $statuses = collect($checks)->pluck('status');

        if ($statuses->contains('fail')) {
            return 'UNHEALTHY';
        }

        if ($statuses->contains('warn')) {
            return 'DEGRADED';
        }

        return 'HEALTHY';
    }

    /**
     * @param  array<int, array{name:string,status:string,detail:string}>  $checks
     */
    private function continuityStatusFor(array $checks): string
    {
        return strtolower($this->statusFor($checks));
    }

    /**
     * @param  array<int, array{name:string,status:string,detail:string}>  $checks
     */
    private function operationalStatus(array $checks, string $name): string
    {
        $status = collect($checks)->firstWhere('name', $name)['status'] ?? null;

        return match ($status) {
            'pass' => 'healthy',
            'warn' => 'degraded',
            'fail' => 'unhealthy',
            default => 'unknown',
        };
    }

    /**
     * @param  array<int, array{name:string,status:string,detail:string}>  $checks
     */
    private function recoveryConfidence(array $checks): int
    {
        if ($checks === []) {
            return 0;
        }

        $score = collect($checks)->sum(fn (array $check): int => match ($check['status']) {
            'pass' => 100,
            'warn' => 60,
            default => 0,
        });

        return (int) round($score / count($checks));
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{name:string,status:string,detail:string,meta:array<string, mixed>}
     */
    private function check(string $name, string $status, string $detail, array $meta = []): array
    {
        return [
            'name' => $name,
            'status' => $status,
            'detail' => $detail,
            'meta' => $meta,
        ];
    }

    private function balancesProjection(): ?ProjectionRebuildRegistry
    {
        if (! Schema::hasTable('projection_rebuild_registry')) {
            return null;
        }

        return ProjectionRebuildRegistry::query()
            ->where('projection_name', 'balances_projection')
            ->first();
    }
}
