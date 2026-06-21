<?php

namespace App\Services\Identity\Governance;

final class GovernanceSnapshot
{
    /**
     * @param  array<string, GovernanceFactor>  $factors
     */
    public function __construct(
        public readonly string $entity,
        public readonly int $throughSequence,
        public readonly string $rootAuthorityMode,
        public readonly ?GovernancePolicy $currentPolicy,
        public readonly array $factors,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $factors = $this->factors;
        ksort($factors);

        return [
            'entity' => $this->entity,
            'through_sequence' => $this->throughSequence,
            'root_authority_mode' => $this->rootAuthorityMode,
            'current_policy' => $this->currentPolicy?->toArray(),
            'factors' => array_map(
                static fn (GovernanceFactor $factor): array => $factor->toArray(),
                $factors,
            ),
        ];
    }
}
