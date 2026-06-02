<?php

namespace App\Services\Mutation;

use App\Exceptions\DuplicateMutationException;
use App\Models\MutationGuardEntry;
use App\Services\Continuity\WriterAuthorityGuard;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MutationDedupGuard
{
    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $metadata
     * @return array{allowed:bool,duplicate:bool,mode:string,guard_key:string,mutation_id:string}
     */
    public function check(
        array $identity,
        string $mutationPath,
        ?string $mode = null,
        ?string $guardKey = null,
        array $metadata = [],
    ): array {
        $mode = $this->normalizeMode($mode);
        $mutationId = (string) ($identity['mutation_id'] ?? '');
        $guardKey ??= 'mutation:'.$mutationPath.':'.$mutationId;
        $writerAuthority = app(WriterAuthorityGuard::class)->check(
            identity: $identity + ['mutation_path' => $mutationPath],
            mode: (string) config('mutation.writer_guard_mode', 'shadow'),
        );

        if ($mode === 'disabled' || $mutationId === '' || ! Schema::hasTable('mutation_guard_entries')) {
            return $this->decision(true, false, $mode, $guardKey, $mutationId);
        }

        try {
            MutationGuardEntry::query()->create([
                'guard_key' => $guardKey,
                'mutation_id' => $mutationId,
                'mutation_path' => $mutationPath,
                'actor' => $identity['actor'] ?? null,
                'action' => $identity['action'] ?? null,
                'entity_type' => $identity['entity_type'] ?? null,
                'entity_id' => $identity['entity_id'] ?? null,
                'idempotency_key' => $identity['idempotency_key'] ?? null,
                'context_fingerprint' => $identity['context_fingerprint'] ?? null,
                'mode' => $mode,
                'decision' => 'allowed',
                'status' => 'started',
                'metadata' => $metadata + ['writer_authority' => $writerAuthority],
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);

            Log::info('Mutation guard allowed mutation', [
                'mutation_id' => $mutationId,
                'mutation_path' => $mutationPath,
                'guard_key' => $guardKey,
                'mode' => $mode,
            ]);

            return $this->decision(true, false, $mode, $guardKey, $mutationId);
        } catch (QueryException $e) {
            $existing = MutationGuardEntry::query()->where('guard_key', $guardKey)->first();
            if (! $existing) {
                throw $e;
            }

            $existing?->forceFill([
                'decision' => $this->isHardMode($mode) ? 'blocked_duplicate' : 'shadow_duplicate',
                'last_seen_at' => now(),
                'metadata' => array_merge($existing->metadata ?? [], [
                    'duplicate_seen_at' => now()->toJSON(),
                    'duplicate_metadata' => $metadata,
                ]),
            ])->save();

            Log::warning('Mutation guard detected duplicate mutation', [
                'mutation_id' => $mutationId,
                'mutation_path' => $mutationPath,
                'guard_key' => $guardKey,
                'mode' => $mode,
            ]);

            if ($this->isHardMode($mode)) {
                throw new DuplicateMutationException(
                    mutationId: $mutationId,
                    mutationPath: $mutationPath,
                    guardKey: $guardKey,
                );
            }

            return $this->decision(true, true, $mode, $guardKey, $mutationId);
        }
    }

    public function complete(string $guardKey): void
    {
        if (! Schema::hasTable('mutation_guard_entries')) {
            return;
        }

        MutationGuardEntry::query()
            ->where('guard_key', $guardKey)
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function normalizeMode(?string $mode): string
    {
        return strtolower((string) ($mode ?: config('mutation.default_mode', 'shadow')));
    }

    private function isHardMode(string $mode): bool
    {
        return in_array($mode, ['enforce', 'hard', 'hard_enforce'], true);
    }

    /**
     * @return array{allowed:bool,duplicate:bool,mode:string,guard_key:string,mutation_id:string}
     */
    private function decision(bool $allowed, bool $duplicate, string $mode, string $guardKey, string $mutationId): array
    {
        return compact('allowed', 'duplicate', 'mode', 'guardKey', 'mutationId') + [
            'guard_key' => $guardKey,
            'mutation_id' => $mutationId,
        ];
    }
}
