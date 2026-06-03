<?php

namespace App\Services\Projections;

use App\Models\CanonicalProductIdentity;
use App\Models\CanonicalProductSearchProfile;
use App\Services\CanonicalProductSearchProfileBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CatalogProjectionVerificationService
{
    /**
     * @return array{status: string, identities_total: int, sources_total: int, orphan_sources: int, source_revision: string}
     */
    public function verifyIdentities(): array
    {
        if (! Schema::hasTable('canonical_product_identities') || ! Schema::hasTable('canonical_product_identity_sources')) {
            return [
                'status' => 'SOURCE_GAP',
                'identities_total' => 0,
                'sources_total' => 0,
                'orphan_sources' => 0,
                'source_revision' => 'canonical_product_identities:missing',
            ];
        }

        $orphanSources = DB::table('canonical_product_identity_sources as sources')
            ->leftJoin('canonical_product_identities as identities', 'identities.id', '=', 'sources.canonical_product_identity_id')
            ->whereNull('identities.id')
            ->count();

        $identityCount = CanonicalProductIdentity::query()->count();
        $sourceCount = DB::table('canonical_product_identity_sources')->count();

        return [
            'status' => $orphanSources === 0 ? 'OK' : 'FAILED',
            'identities_total' => $identityCount,
            'sources_total' => $sourceCount,
            'orphan_sources' => $orphanSources,
            'source_revision' => sprintf(
                'canonical_product_identities:%d:%s;canonical_product_identity_sources:%d:%s',
                $identityCount,
                CanonicalProductIdentity::query()->max('id') ?? 'none',
                $sourceCount,
                DB::table('canonical_product_identity_sources')->max('id') ?? 'none',
            ),
        ];
    }

    /**
     * @return array{status: string, identities_total: int, profiles_total: int, profiles_missing: int, profiles_stale: int, profiles_failed: int, source_revision: string}
     */
    public function verifySearchProfiles(): array
    {
        if (! Schema::hasTable('canonical_product_identities') || ! Schema::hasTable('canonical_product_search_profiles')) {
            return [
                'status' => 'SOURCE_GAP',
                'identities_total' => 0,
                'profiles_total' => 0,
                'profiles_missing' => 0,
                'profiles_stale' => 0,
                'profiles_failed' => 0,
                'source_revision' => 'canonical_product_search_profiles:missing',
            ];
        }

        $identityCount = CanonicalProductIdentity::query()->count();
        $profileCount = CanonicalProductSearchProfile::query()->count();
        $failedCount = CanonicalProductSearchProfile::query()->whereNotNull('last_error')->count();
        $staleCount = $this->staleSearchProfileCount();
        $missingCount = max(0, $identityCount - $profileCount);

        return [
            'status' => ($missingCount === 0 && $staleCount === 0 && $failedCount === 0) ? 'OK' : 'FAILED',
            'identities_total' => $identityCount,
            'profiles_total' => $profileCount,
            'profiles_missing' => $missingCount,
            'profiles_stale' => $staleCount,
            'profiles_failed' => $failedCount,
            'source_revision' => sprintf(
                'canonical_product_identities:%d:%s;canonical_product_search_profiles:%d:%s',
                $identityCount,
                CanonicalProductIdentity::query()->max('id') ?? 'none',
                $profileCount,
                CanonicalProductSearchProfile::query()->max('id') ?? 'none',
            ),
        ];
    }

    private function staleSearchProfileCount(): int
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
}
