<?php

namespace App\Services\Identity\Governance;

/**
 * Canonical identity governance event vocabulary (v1).
 *
 * Keep this list small. If it grows quickly, UI or implementation detail is
 * leaking into the log.
 */
final class GovernanceEventTypes
{
    public const IDENTITY_CREATED = 'identity.created';

    public const IDENTITY_USERNAME_ASSIGNED = 'identity.username_assigned';

    public const POLICY_DECLARED = 'policy.declared';

    public const CREDENTIAL_BOUND = 'credential.bound';

    public const CREDENTIAL_REVOKED = 'credential.revoked';

    public const AUTHORITY_MODE_CHANGED = 'authority.mode_changed';

    /** Session-scoped — not folded into registry or governance materialized views. */
    public const EVIDENCE_VERIFIED = 'evidence.verified';

    /** Session outcome — optional audit; not governance state. */
    public const CONTINUITY_REESTABLISHED = 'continuity.reestablished';

    /**
     * Legacy aliases retained for historical compatibility (read path only).
     *
     * @var array<string, string>
     */
    public const LEGACY_ALIASES = [
        'recovery_policy.declared' => self::POLICY_DECLARED,
        'root_authority.declared' => self::AUTHORITY_MODE_CHANGED,
        'recovery.factor.verified' => self::EVIDENCE_VERIFIED,
        'recovery.completed' => self::CONTINUITY_REESTABLISHED,
    ];

    /**
     * @return list<string>
     */
    public static function coreLogTypes(): array
    {
        return [
            self::IDENTITY_CREATED,
            self::IDENTITY_USERNAME_ASSIGNED,
            self::POLICY_DECLARED,
            self::CREDENTIAL_BOUND,
            self::CREDENTIAL_REVOKED,
            self::AUTHORITY_MODE_CHANGED,
        ];
    }

    public static function normalize(string $type): string
    {
        return self::LEGACY_ALIASES[$type] ?? $type;
    }
}
