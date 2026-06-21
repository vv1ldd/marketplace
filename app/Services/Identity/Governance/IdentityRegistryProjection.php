<?php

namespace App\Services\Identity\Governance;

/**
 * Registry read model — projected from the same append-only log as governance.
 * Not a second source of truth (Invariant 7).
 */
final class IdentityRegistryProjection
{
    /**
     * @param  list<array<string, mixed>>  $bindings
     */
    public function __construct(
        public readonly string $entity,
        public readonly bool $exists,
        public readonly ?string $username,
        public readonly array $bindings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $bindings = $this->bindings;
        usort(
            $bindings,
            static fn (array $a, array $b): int => strcmp((string) $a['factor_id'], (string) $b['factor_id']),
        );

        return [
            'entity' => $this->entity,
            'exists' => $this->exists,
            'username' => $this->username,
            'bindings' => $bindings,
        ];
    }
}
