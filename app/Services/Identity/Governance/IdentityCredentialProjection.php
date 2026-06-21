<?php

namespace App\Services\Identity\Governance;

/**
 * Verify-ready credential registry projected from the governance stream.
 */
final class IdentityCredentialProjection
{
    /**
     * @param  list<IdentityCredentialMaterial>  $activeCredentials
     */
    public function __construct(
        public readonly string $entity,
        public readonly array $activeCredentials,
        public readonly int $throughVersion,
    ) {}

    /**
     * @return list<array{type: string, id: string, transports?: list<string>}>
     */
    public function toAllowCredentials(): array
    {
        return array_map(
            static fn (IdentityCredentialMaterial $material): array => $material->toAllowCredentialDescriptor(),
            $this->activeCredentials,
        );
    }

    public function findByFactorId(string $factorId): ?IdentityCredentialMaterial
    {
        foreach ($this->activeCredentials as $credential) {
            if ($credential->factorId === $factorId) {
                return $credential;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entity' => $this->entity,
            'through_version' => $this->throughVersion,
            'active_credentials' => array_map(
                static fn (IdentityCredentialMaterial $material): array => $material->toArray(),
                $this->activeCredentials,
            ),
        ];
    }

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }
}
