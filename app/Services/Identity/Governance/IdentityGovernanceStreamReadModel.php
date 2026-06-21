<?php

namespace App\Services\Identity\Governance;

/**
 * Read-side API for authorize and storefront flows.
 * Strong consistency: prefers projection cache updated synchronously on append.
 * Restart-safe: rebuilds from stream when cache is cold.
 */
final class IdentityGovernanceStreamReadModel
{
    public function __construct(
        private readonly IdentityGovernanceStreamWriter $writer,
        private readonly IdentityGovernanceProjectionRebuilder $rebuilder,
        private readonly IdentityGovernanceStreamAppender $appender,
    ) {}

    public function read(string $streamId): IdentityGovernanceDualProjection
    {
        return $this->writer->read(strtolower($streamId));
    }

    public function replayFromStreamOnly(string $streamId): IdentityGovernanceDualProjection
    {
        return $this->rebuilder->replayFull(strtolower($streamId));
    }

    public function streamExists(string $streamId): bool
    {
        return $this->appender->headVersion(strtolower($streamId)) > 0;
    }

    public function canAuthorize(string $streamId, ?string $factorId = null): bool
    {
        if (! $this->streamExists($streamId)) {
            return false;
        }

        $projection = $this->read($streamId);

        if (! $projection->registry->exists) {
            return false;
        }

        if ($factorId === null || $factorId === '') {
            return true;
        }

        foreach ($projection->registry->bindings as $binding) {
            if (($binding['factor_id'] ?? null) === $factorId) {
                return ($binding['status'] ?? null) === GovernanceFactor::STATUS_ACTIVE;
            }
        }

        return false;
    }
}
