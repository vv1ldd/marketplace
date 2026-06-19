<?php

namespace App\Services\Identity\Governance;

/**
 * Invariant 12 — read-time normalization. Old log rows are never rewritten.
 */
final class GovernanceEventPayloadNormalizer
{
    public const CURRENT_SCHEMA_VERSION = 1;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function normalize(string $eventType, array $payload): array
    {
        $schemaVersion = (int) ($payload['schema_version'] ?? self::CURRENT_SCHEMA_VERSION);

        return match (GovernanceEventTypes::normalize($eventType)) {
            GovernanceEventTypes::CREDENTIAL_BOUND => self::credentialBound($payload, $schemaVersion),
            GovernanceEventTypes::POLICY_DECLARED => self::policyDeclared($payload, $schemaVersion),
            GovernanceEventTypes::IDENTITY_USERNAME_ASSIGNED => self::usernameAssigned($payload, $schemaVersion),
            default => $payload,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function forAppend(string $eventType, array $payload): array
    {
        if (! array_key_exists('schema_version', $payload)) {
            $payload['schema_version'] = self::CURRENT_SCHEMA_VERSION;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function credentialBound(array $payload, int $schemaVersion): array
    {
        if ($schemaVersion > self::CURRENT_SCHEMA_VERSION) {
            throw new \InvalidArgumentException("Unsupported credential.bound schema_version: {$schemaVersion}");
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function policyDeclared(array $payload, int $schemaVersion): array
    {
        if ($schemaVersion > self::CURRENT_SCHEMA_VERSION) {
            throw new \InvalidArgumentException("Unsupported policy.declared schema_version: {$schemaVersion}");
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function usernameAssigned(array $payload, int $schemaVersion): array
    {
        if ($schemaVersion > self::CURRENT_SCHEMA_VERSION) {
            throw new \InvalidArgumentException("Unsupported identity.username_assigned schema_version: {$schemaVersion}");
        }

        return $payload;
    }
}
