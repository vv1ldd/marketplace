<?php

namespace App\Services\Identity\Governance;

final class GovernanceProjection
{
    public const ENGINE_VERSION = 'policy-v1';

    /**
     * @param  list<string>  $fulfilledClasses
     * @param  list<array<string, mixed>>  $activeFactors
     */
    public function __construct(
        public readonly string $entity,
        public readonly string $rootAuthorityMode,
        public readonly string $protectionTier,
        public readonly string $recoveryState,
        public readonly array $fulfilledClasses,
        public readonly int $independentDimensionsMet,
        public readonly int $activeFactorCount,
        public readonly array $activeFactors,
        public readonly ?GovernancePolicy $currentPolicy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entity' => $this->entity,
            'root_authority_mode' => $this->rootAuthorityMode,
            'protection_tier' => $this->protectionTier,
            'recovery_state' => $this->recoveryState,
            'fulfilled_classes' => $this->fulfilledClasses,
            'independent_dimensions_met' => $this->independentDimensionsMet,
            'active_factor_count' => $this->activeFactorCount,
            'active_factors' => $this->activeFactors,
            'current_policy' => $this->currentPolicy?->toArray(),
            'engine_version' => self::ENGINE_VERSION,
        ];
    }
}
