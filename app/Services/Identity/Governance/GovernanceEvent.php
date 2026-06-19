<?php

namespace App\Services\Identity\Governance;

final class GovernanceEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $type,
        public readonly string $entity,
        public readonly int $sequence,
        public readonly array $payload = [],
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            type: (string) $row['type'],
            entity: (string) $row['entity'],
            sequence: (int) $row['sequence'],
            payload: (array) ($row['payload'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'entity' => $this->entity,
            'sequence' => $this->sequence,
            'payload' => $this->payload,
        ];
    }
}
