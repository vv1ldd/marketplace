<?php

namespace App\Services\Identity\Governance;

/**
 * Producer-supplied idempotency identifier — not a database UUID.
 *
 * Accepts deterministic producer ids (vault-create:…), RFC UUIDs, and external producer keys.
 */
final class IdentityGovernanceStreamEventId
{
    public const MAX_LENGTH = 255;

    /** @var string Letters, digits, and common producer separators. */
    private const PATTERN = '/^[A-Za-z0-9._:-]+$/';

    public static function assertValid(string $eventId): void
    {
        if ($eventId === '') {
            throw new IdentityGovernanceStreamEventIdException('event_id must not be empty.');
        }

        if (strlen($eventId) > self::MAX_LENGTH) {
            throw new IdentityGovernanceStreamEventIdException(
                'event_id exceeds storage contract of '.self::MAX_LENGTH.' bytes.',
            );
        }

        if (preg_match(self::PATTERN, $eventId) !== 1) {
            throw new IdentityGovernanceStreamEventIdException(
                'event_id must match producer idempotency key format [A-Za-z0-9._:-].',
            );
        }
    }
}
