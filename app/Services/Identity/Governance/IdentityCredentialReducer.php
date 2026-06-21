<?php

namespace App\Services\Identity\Governance;

/**
 * Folds verify-ready WebAuthn material from credential.bound payloads.
 *
 * Target model (Variant A): credential store is a projection — destroy and replay.
 */
final class IdentityCredentialReducer
{
    /**
     * @param  list<GovernanceEvent>  $events
     */
    public static function fold(array $events): IdentityCredentialProjection
    {
        if ($events === []) {
            throw new \InvalidArgumentException('At least one event is required.');
        }

        $sorted = self::sortEvents($events);
        $entity = $sorted[0]->entity;
        /** @var array<string, IdentityCredentialMaterial> $credentials */
        $credentials = [];
        $throughVersion = 0;

        foreach ($sorted as $event) {
            if ($event->entity !== $entity) {
                throw new \InvalidArgumentException('All events must belong to the same entity.');
            }

            $throughVersion = $event->sequence;

            match (GovernanceEventTypes::normalize($event->type)) {
                GovernanceEventTypes::CREDENTIAL_BOUND => self::bindCredential($credentials, $event->payload),
                GovernanceEventTypes::CREDENTIAL_REVOKED => self::revokeCredential($credentials, (string) $event->payload['factor_id']),
                default => null,
            };
        }

        $active = array_values($credentials);
        usort(
            $active,
            static fn (IdentityCredentialMaterial $a, IdentityCredentialMaterial $b): int => strcmp($a->factorId, $b->factorId),
        );

        return new IdentityCredentialProjection(
            entity: $entity,
            activeCredentials: $active,
            throughVersion: $throughVersion,
        );
    }

    /**
     * @param  array<string, IdentityCredentialMaterial>  $credentials
     * @param  array<string, mixed>  $payload
     */
    private static function bindCredential(array &$credentials, array $payload): void
    {
        $material = IdentityCredentialMaterial::fromBoundPayload(
            GovernanceEventPayloadNormalizer::normalize(GovernanceEventTypes::CREDENTIAL_BOUND, $payload),
        );

        if ($material === null) {
            return;
        }

        $credentials[$material->factorId] = $material;
    }

    /**
     * @param  array<string, IdentityCredentialMaterial>  $credentials
     */
    private static function revokeCredential(array &$credentials, string $factorId): void
    {
        unset($credentials[$factorId]);
    }

    /**
     * @param  list<GovernanceEvent>  $events
     * @return list<GovernanceEvent>
     */
    private static function sortEvents(array $events): array
    {
        usort(
            $events,
            static fn (GovernanceEvent $a, GovernanceEvent $b): int => $a->sequence <=> $b->sequence,
        );

        return $events;
    }
}
