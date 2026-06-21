<?php

namespace App\Services\Identity\Governance;

use Illuminate\Support\Facades\Cache;

/**
 * Runtime-only sign counter telemetry — not part of identity continuity stream.
 */
final class IdentityGovernanceCredentialCounterStore
{
    public function read(string $streamId, string $factorId): ?int
    {
        $value = Cache::get($this->key($streamId, $factorId));

        return is_int($value) ? $value : null;
    }

    public function write(string $streamId, string $factorId, int $counter): void
    {
        Cache::put($this->key($streamId, $factorId), $counter, now()->addDays(30));
    }

    public function forgetAll(): void
    {
        // Used only in tests; production counters expire naturally.
    }

    public function effectiveSignCount(IdentityCredentialMaterial $material, string $streamId): int
    {
        return max(
            $material->signCount,
            $this->read($streamId, $material->factorId) ?? 0,
        );
    }

    private function key(string $streamId, string $factorId): string
    {
        return 'identity-governance:credential-counter:'.strtolower($streamId).':'.$factorId;
    }
}
