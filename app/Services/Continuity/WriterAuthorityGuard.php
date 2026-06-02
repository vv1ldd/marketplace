<?php

namespace App\Services\Continuity;

use App\Exceptions\WriterAuthorityException;
use App\Models\WriterAuthorityReadiness;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class WriterAuthorityGuard
{
    /**
     * @param  array<string, mixed>  $identity
     * @return array{allowed:bool,mode:string,reason:string|null,current_region:string,writer_region:string|null,writer_epoch:string|null}
     */
    public function check(array $identity = [], ?string $mode = null, ?string $scope = null): array
    {
        $mode = strtolower((string) ($mode ?: config('mutation.writer_guard_mode', 'shadow')));
        $scope ??= (string) config('mutation.writer_scope', 'marketplace:global');
        $currentRegion = (string) config('mutation.region', 'local');
        $authority = $this->authority($scope);
        $writerRegion = $authority['writer_region'];
        $writerEpoch = $authority['writer_epoch'];
        $reason = null;

        if ($writerRegion === null || $writerEpoch === null) {
            $reason = 'writer_authority_unknown';
        } elseif ($writerRegion !== $currentRegion) {
            $reason = 'writer_region_mismatch';
        }

        $allowed = $reason === null;
        $payload = [
            'allowed' => $allowed,
            'mode' => $mode,
            'reason' => $reason,
            'scope' => $scope,
            'current_region' => $currentRegion,
            'writer_region' => $writerRegion,
            'writer_epoch' => $writerEpoch,
            'mutation_id' => $identity['mutation_id'] ?? null,
            'mutation_path' => $identity['mutation_path'] ?? null,
        ];

        if (! $allowed) {
            Log::warning('Writer authority guard detected unsafe mutation authority', $payload);

            if ($this->isHardMode($mode)) {
                throw new WriterAuthorityException(
                    reason: (string) $reason,
                    scope: $scope,
                    message: "Writer authority rejected mutation: {$reason}",
                );
            }
        }

        return [
            'allowed' => $allowed,
            'mode' => $mode,
            'reason' => $reason,
            'current_region' => $currentRegion,
            'writer_region' => $writerRegion,
            'writer_epoch' => $writerEpoch,
        ];
    }

    /**
     * @return array{writer_region:?string,writer_epoch:?string,source:string}
     */
    public function authority(?string $scope = null): array
    {
        $scope ??= (string) config('mutation.writer_scope', 'marketplace:global');

        if (Schema::hasTable('writer_authority_readiness')) {
            $row = WriterAuthorityReadiness::query()->where('scope', $scope)->first();
            if ($row && filled($row->authority_holder) && filled($row->authority_epoch)) {
                return [
                    'writer_region' => (string) $row->authority_holder,
                    'writer_epoch' => (string) $row->authority_epoch,
                    'source' => 'writer_authority_readiness',
                ];
            }
        }

        return [
            'writer_region' => filled(config('mutation.writer_region')) ? (string) config('mutation.writer_region') : null,
            'writer_epoch' => filled(config('mutation.writer_epoch')) ? (string) config('mutation.writer_epoch') : null,
            'source' => 'config',
        ];
    }

    private function isHardMode(string $mode): bool
    {
        return in_array($mode, ['enforce', 'hard', 'hard_enforce'], true);
    }
}
