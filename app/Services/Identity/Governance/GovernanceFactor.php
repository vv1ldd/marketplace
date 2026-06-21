<?php

namespace App\Services\Identity\Governance;

final class GovernanceFactor
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $class,
        public readonly string $type,
        public readonly string $status,
        public readonly ?string $purpose = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromBoundPayload(array $payload): self
    {
        return new self(
            id: (string) $payload['factor_id'],
            class: (string) $payload['class'],
            type: (string) $payload['type'],
            status: self::STATUS_ACTIVE,
            purpose: isset($payload['purpose']) ? (string) $payload['purpose'] : null,
            metadata: (array) ($payload['metadata'] ?? []),
        );
    }

    public function isDailyLogin(): bool
    {
        return $this->purpose === 'daily';
    }

    public function withStatus(string $status): self
    {
        return new self(
            id: $this->id,
            class: $this->class,
            type: $this->type,
            status: $status,
            purpose: $this->purpose,
            metadata: $this->metadata,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'class' => $this->class,
            'type' => $this->type,
            'status' => $this->status,
            'purpose' => $this->purpose,
            'metadata' => $this->metadata,
        ];
    }
}
