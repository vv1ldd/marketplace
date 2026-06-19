<?php

namespace App\Services\Identity\Governance;

/**
 * Append-time rules for identity governance streams (Invariant 9 + genesis).
 * Reducers may fold synthetic test histories; production append MUST pass here first.
 */
final class IdentityGovernanceStreamAppendRules
{
    public static function validate(?int $headVersion, GovernanceEvent $candidate): void
    {
        $version = $candidate->sequence;
        $type = GovernanceEventTypes::normalize($candidate->type);

        if ($headVersion === null) {
            if ($version !== 1) {
                throw new \InvalidArgumentException('Genesis stream append must use version 1.');
            }

            if ($type !== GovernanceEventTypes::IDENTITY_CREATED) {
                throw new \InvalidArgumentException('Genesis event must be identity.created.');
            }

            return;
        }

        if ($version !== $headVersion + 1) {
            throw new \InvalidArgumentException(
                "Stream version must be strictly monotonic with no gaps. Expected {$headVersion}+1, got {$version}.",
            );
        }
    }

    /**
     * @param  list<GovernanceEvent>  $events  Must already be persisted in version order
     */
    public static function validateMonotonicStream(array $events): void
    {
        $headVersion = null;

        foreach ($events as $event) {
            self::validate($headVersion, $event);
            $headVersion = $event->sequence;
        }
    }
}
