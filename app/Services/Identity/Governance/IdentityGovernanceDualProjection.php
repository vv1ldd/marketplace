<?php

namespace App\Services\Identity\Governance;

final class IdentityGovernanceDualProjection
{
    public function __construct(
        public readonly IdentityRegistryProjection $registry,
        public readonly GovernanceProjection $governance,
        public readonly int $throughVersion,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'through_version' => $this->throughVersion,
            'registry' => $this->registry->toArray(),
            'governance' => $this->governance->toArray(),
        ];
    }

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }
}
