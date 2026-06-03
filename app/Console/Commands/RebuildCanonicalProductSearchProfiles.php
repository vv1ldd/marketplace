<?php

namespace App\Console\Commands;

use App\Models\CanonicalProductIdentity;
use App\Models\CanonicalProductSearchProfile;
use App\Services\Continuity\ProjectionRebuildRegistryService;
use App\Services\CanonicalProductSearchProfileBuilder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class RebuildCanonicalProductSearchProfiles extends Command
{
    protected $signature = 'search-profile:rebuild
                            {--identity= : Rebuild one canonical product identity by ID}
                            {--stale : Rebuild missing, failed, outdated, or old-version profiles only}';

    protected $description = 'Rebuild materialized canonical product search profiles';

    public function handle(CanonicalProductSearchProfileBuilder $builder, ProjectionRebuildRegistryService $registry): int
    {
        $registry->ensureDefaults();

        if (! $this->tablesExist()) {
            $this->warn('Canonical product identity/search profile tables do not exist yet. Run migrations first.');

            return self::FAILURE;
        }

        if ($this->option('identity') && $this->option('stale')) {
            $this->error('Use either --identity or --stale, not both.');

            return self::FAILURE;
        }

        $started = microtime(true);
        $stats = [
            'profiles_rebuilt' => 0,
            'profiles_failed' => 0,
        ];

        $query = $this->identityQuery();
        $totalToProcess = (clone $query)->count();

        $query->chunkById(200, function ($identities) use ($builder, &$stats): void {
                foreach ($identities as $identity) {
                    try {
                        $builder->rebuild($identity);
                        $stats['profiles_rebuilt']++;
                    } catch (\Throwable $exception) {
                        $this->recordFailure($identity, $exception);
                        $stats['profiles_failed']++;
                    }
                }
            }, column: 'canonical_product_identities.id', alias: 'id');

        $durationMs = (int) round((microtime(true) - $started) * 1000);
        $health = $this->health();

        $this->info('Search profile rebuild complete.');
        $this->line('profiles_targeted: '.$totalToProcess);
        $this->line('profiles_rebuilt: '.$stats['profiles_rebuilt']);
        $this->line('profiles_failed_this_run: '.$stats['profiles_failed']);
        $this->line('rebuild_duration_ms: '.$durationMs);
        $this->line('profiles_rebuilt_per_minute: '.$this->profilesPerMinute($stats['profiles_rebuilt'], $durationMs));
        $this->line('profiles_total: '.$health['profiles_total']);
        $this->line('profiles_missing: '.$health['profiles_missing']);
        $this->line('profiles_stale: '.$health['profiles_stale']);
        $this->line('profiles_failed: '.$health['profiles_failed']);
        $this->line('last_rebuild_at: '.($health['last_rebuild_at'] ?? 'null'));
        $this->line('profile_version_distribution: '.json_encode($health['profile_version_distribution'], JSON_UNESCAPED_SLASHES));

        $registry->markRebuilt(
            projectionName: 'canonical_product_search_profile_projection',
            sourceRevision: 'canonical_product_search_profiles:'.$health['profiles_total'],
            metadata: $health + [
                'profiles_targeted' => $totalToProcess,
                'profiles_rebuilt' => $stats['profiles_rebuilt'],
                'profiles_failed_this_run' => $stats['profiles_failed'],
                'rebuild_duration_ms' => $durationMs,
            ],
        );

        return $stats['profiles_failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function identityQuery(): Builder
    {
        $identityId = $this->option('identity');

        if ($identityId !== null && $identityId !== '') {
            return CanonicalProductIdentity::query()
                ->whereKey((int) $identityId);
        }

        if (! $this->option('stale')) {
            return CanonicalProductIdentity::query();
        }

        return CanonicalProductIdentity::query()
            ->select('canonical_product_identities.*')
            ->leftJoin('canonical_product_search_profiles as search_profiles', 'search_profiles.canonical_product_identity_id', '=', 'canonical_product_identities.id')
            ->where(function ($query): void {
                $query
                    ->whereNull('search_profiles.id')
                    ->orWhereNotNull('search_profiles.last_error')
                    ->orWhere('search_profiles.profile_version', '<>', CanonicalProductSearchProfileBuilder::PROFILE_VERSION)
                    ->orWhereColumn('search_profiles.updated_at', '<', 'canonical_product_identities.updated_at');
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function health(): array
    {
        $identityCount = CanonicalProductIdentity::query()->count();
        $profileCount = CanonicalProductSearchProfile::query()->count();
        $failedCount = CanonicalProductSearchProfile::query()
            ->whereNotNull('last_error')
            ->count();
        $staleCount = $this->staleCount();
        $distribution = CanonicalProductSearchProfile::query()
            ->selectRaw('profile_version, count(*) as total')
            ->groupBy('profile_version')
            ->orderBy('profile_version')
            ->pluck('total', 'profile_version')
            ->map(fn ($count) => (int) $count)
            ->all();
        $lastRebuildAt = CanonicalProductSearchProfile::query()->max('last_rebuild_at');

        return [
            'profiles_total' => $profileCount,
            'profiles_missing' => max(0, $identityCount - $profileCount),
            'profiles_stale' => $staleCount,
            'profiles_failed' => $failedCount,
            'last_rebuild_at' => $lastRebuildAt,
            'profile_version_distribution' => $distribution,
        ];
    }

    private function staleCount(): int
    {
        return CanonicalProductIdentity::query()
            ->leftJoin('canonical_product_search_profiles as search_profiles', 'search_profiles.canonical_product_identity_id', '=', 'canonical_product_identities.id')
            ->where(function ($query): void {
                $query
                    ->whereNull('search_profiles.id')
                    ->orWhereNotNull('search_profiles.last_error')
                    ->orWhere('search_profiles.profile_version', '<>', CanonicalProductSearchProfileBuilder::PROFILE_VERSION)
                    ->orWhereColumn('search_profiles.updated_at', '<', 'canonical_product_identities.updated_at');
            })
            ->count();
    }

    private function recordFailure(CanonicalProductIdentity $identity, \Throwable $exception): void
    {
        CanonicalProductSearchProfile::query()->updateOrCreate(
            ['canonical_product_identity_id' => $identity->id],
            [
                'search_text' => '',
                'search_tokens' => [],
                'search_aliases' => [],
                'search_metadata' => [],
                'profile_version' => CanonicalProductSearchProfileBuilder::PROFILE_VERSION,
                'last_rebuild_at' => now(),
                'last_error' => $exception->getMessage(),
            ],
        );
    }

    private function profilesPerMinute(int $count, int $durationMs): string
    {
        if ($count === 0 || $durationMs <= 0) {
            return '0';
        }

        return number_format($count / ($durationMs / 60000), 2, '.', '');
    }

    private function tablesExist(): bool
    {
        return Schema::hasTable('canonical_product_identities')
            && Schema::hasTable('canonical_product_search_profiles');
    }
}
