<?php

namespace App\Services\Identity\Governance;

use RuntimeException;

final class IdentityGovernanceStreamIdempotencyConflictException extends RuntimeException
{
    public function __construct(
        public readonly string $eventId,
    ) {
        parent::__construct("Event id {$eventId} was already used with a different payload.");
    }
}
