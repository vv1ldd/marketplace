<?php

namespace App\Services\Identity\Governance;

final class IdentityGovernanceStreamAppendResult
{
    public function __construct(
        public readonly string $streamId,
        public readonly int $version,
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly bool $idempotentReplay,
    ) {}
}
