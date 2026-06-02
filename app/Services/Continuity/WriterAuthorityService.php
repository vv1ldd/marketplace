<?php

namespace App\Services\Continuity;

use App\Models\WriterAuthorityReadiness;
use Illuminate\Support\Facades\Schema;

class WriterAuthorityService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function heartbeat(
        string $scope,
        string $authorityHolder,
        string $authorityEpoch,
        string $fencingStatus = WriterAuthorityReadiness::FENCING_ACTIVE,
        array $metadata = [],
    ): ?WriterAuthorityReadiness {
        if (! Schema::hasTable('writer_authority_readiness')) {
            return null;
        }

        return WriterAuthorityReadiness::updateOrCreate(
            ['scope' => $scope],
            [
                'authority_holder' => $authorityHolder,
                'authority_epoch' => $authorityEpoch,
                'fencing_status' => $fencingStatus,
                'conflict_status' => WriterAuthorityReadiness::CONFLICT_NONE,
                'last_heartbeat_at' => now(),
                'metadata' => $metadata,
            ],
        );
    }

    public function markConflict(string $scope, string $reason): ?WriterAuthorityReadiness
    {
        if (! Schema::hasTable('writer_authority_readiness')) {
            return null;
        }

        return WriterAuthorityReadiness::updateOrCreate(
            ['scope' => $scope],
            [
                'conflict_status' => WriterAuthorityReadiness::CONFLICT_CONFLICT,
                'fencing_status' => WriterAuthorityReadiness::FENCING_PENDING,
                'metadata' => ['reason' => $reason],
            ],
        );
    }

    /**
     * @return array{status:string,total:int,healthy:int,conflicts:int,stale:int,no_holder:int,detail:string}
     */
    public function readiness(int $staleAfterMinutes = 2): array
    {
        if (! Schema::hasTable('writer_authority_readiness')) {
            return [
                'status' => 'fail',
                'total' => 0,
                'healthy' => 0,
                'conflicts' => 0,
                'stale' => 0,
                'no_holder' => 0,
                'detail' => 'writer_authority_readiness table is missing.',
            ];
        }

        $rows = WriterAuthorityReadiness::query()->get();
        $now = now();
        $conflicts = $rows->where('conflict_status', WriterAuthorityReadiness::CONFLICT_CONFLICT)->count();
        $noHolder = $rows->filter(fn (WriterAuthorityReadiness $row): bool => blank($row->authority_holder))->count();
        $stale = $rows->filter(function (WriterAuthorityReadiness $row) use ($now, $staleAfterMinutes): bool {
            return $row->last_heartbeat_at !== null
                && $row->last_heartbeat_at->diffInMinutes($now) > $staleAfterMinutes;
        })->count();
        $healthy = $rows->filter(fn (WriterAuthorityReadiness $row): bool => $row->isHealthy())->count();

        $status = 'pass';
        if ($conflicts > 0) {
            $status = 'fail';
        } elseif ($rows->isEmpty() || $noHolder > 0 || $stale > 0) {
            $status = 'warn';
        }

        return [
            'status' => $status,
            'total' => $rows->count(),
            'healthy' => $healthy,
            'conflicts' => $conflicts,
            'stale' => $stale,
            'no_holder' => $noHolder,
            'detail' => "scopes={$rows->count()}, healthy={$healthy}, conflicts={$conflicts}, stale={$stale}, no_holder={$noHolder}",
        ];
    }
}
