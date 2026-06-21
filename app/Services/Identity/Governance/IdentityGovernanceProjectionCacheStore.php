<?php

namespace App\Services\Identity\Governance;

use App\Models\IdentityGovernanceProjectionCache;
use Illuminate\Support\Facades\DB;

/**
 * Strong read consistency: append persists, projections rebuild synchronously, cache updates
 * in the same transaction before the caller receives success.
 */
final class IdentityGovernanceProjectionCacheStore
{
    public function read(string $streamId): ?IdentityGovernanceDualProjection
    {
        $row = IdentityGovernanceProjectionCache::query()->find($streamId);

        if ($row === null) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function write(IdentityGovernanceDualProjection $projection): void
    {
        IdentityGovernanceProjectionCache::query()->updateOrInsert(
            ['stream_id' => $projection->registry->entity],
            [
                'through_version' => $projection->throughVersion,
                'registry_projection' => json_encode($projection->registry->toArray(), JSON_THROW_ON_ERROR),
                'governance_projection' => json_encode($projection->governance->toArray(), JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ],
        );
    }

    public function forget(string $streamId): void
    {
        IdentityGovernanceProjectionCache::query()->where('stream_id', $streamId)->delete();
    }

    private function hydrate(IdentityGovernanceProjectionCache $row): IdentityGovernanceDualProjection
    {
        $registryData = (array) $row->registry_projection;
        $governanceData = (array) $row->governance_projection;

        return new IdentityGovernanceDualProjection(
            registry: new IdentityRegistryProjection(
                entity: (string) $registryData['entity'],
                exists: (bool) $registryData['exists'],
                username: $registryData['username'] ?? null,
                bindings: (array) ($registryData['bindings'] ?? []),
            ),
            governance: $this->hydrateGovernanceProjection($governanceData),
            throughVersion: (int) $row->through_version,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hydrateGovernanceProjection(array $data): GovernanceProjection
    {
        $policy = null;

        if (isset($data['current_policy']) && is_array($data['current_policy'])) {
            $policy = GovernancePolicy::fromPayload($data['current_policy']);
        }

        return new GovernanceProjection(
            entity: (string) $data['entity'],
            rootAuthorityMode: (string) $data['root_authority_mode'],
            protectionTier: (string) $data['protection_tier'],
            recoveryState: (string) $data['recovery_state'],
            fulfilledClasses: (array) ($data['fulfilled_classes'] ?? []),
            independentDimensionsMet: (int) ($data['independent_dimensions_met'] ?? 0),
            activeFactorCount: (int) ($data['active_factor_count'] ?? 0),
            activeFactors: (array) ($data['active_factors'] ?? []),
            currentPolicy: $policy,
        );
    }
}
