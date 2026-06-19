<?php

namespace App\Services\Identity\Governance;

use RuntimeException;

final class IdentityGovernanceStreamConcurrencyException extends RuntimeException
{
    public function __construct(
        public readonly string $streamId,
        public readonly int $expectedVersion,
        public readonly int $actualVersion,
    ) {
        parent::__construct(sprintf(
            'Stream concurrency conflict for %s: expected version %d, actual head is %d.',
            $streamId,
            $expectedVersion,
            $actualVersion,
        ));
    }
}
