<?php

namespace App\Services\Identity\Governance;

final class GovernancePolicy
{
    /**
     * @param  list<string>  $requiredFactorClasses
     * @param  list<string>  $independenceDimensions
     */
    public function __construct(
        public readonly int $version = 1,
        public readonly string $rule = 'all',
        public readonly array $requiredFactorClasses = [],
        public readonly int $minimumIndependentDimensions = 2,
        public readonly array $independenceDimensions = ['device', 'ecosystem', 'custody'],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            version: (int) ($payload['version'] ?? 1),
            rule: (string) ($payload['rule'] ?? 'all'),
            requiredFactorClasses: array_values($payload['required_factor_classes'] ?? []),
            minimumIndependentDimensions: (int) ($payload['minimum_independent_dimensions'] ?? 2),
            independenceDimensions: array_values($payload['independence_dimensions'] ?? ['device', 'ecosystem', 'custody']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'rule' => $this->rule,
            'required_factor_classes' => $this->requiredFactorClasses,
            'minimum_independent_dimensions' => $this->minimumIndependentDimensions,
            'independence_dimensions' => $this->independenceDimensions,
        ];
    }
}
