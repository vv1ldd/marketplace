<?php

namespace App\Services\Identity\Governance;

/**
 * Folds identity registry fields from the shared append-only log.
 */
final class IdentityRegistryReducer
{
    /**
     * @param  list<GovernanceEvent>  $events
     */
    public static function fold(array $events): IdentityRegistryProjection
    {
        if ($events === []) {
            throw new \InvalidArgumentException('At least one event is required.');
        }

        $sorted = self::sortEvents($events);
        $entity = $sorted[0]->entity;
        $exists = false;
        $username = null;
        /** @var array<string, array<string, mixed>> $bindings */
        $bindings = [];

        foreach ($sorted as $event) {
            if ($event->entity !== $entity) {
                throw new \InvalidArgumentException('All events must belong to the same entity.');
            }

            $payload = GovernanceEventPayloadNormalizer::normalize($event->type, $event->payload);

            match (GovernanceEventTypes::normalize($event->type)) {
                GovernanceEventTypes::IDENTITY_CREATED => $exists = true,
                GovernanceEventTypes::IDENTITY_USERNAME_ASSIGNED => $username = (string) ($payload['username'] ?? ''),
                GovernanceEventTypes::CREDENTIAL_BOUND => $bindings[(string) $payload['factor_id']] = [
                    'factor_id' => (string) $payload['factor_id'],
                    'class' => (string) ($payload['class'] ?? ''),
                    'type' => (string) ($payload['type'] ?? ''),
                    'purpose' => $payload['purpose'] ?? null,
                    'status' => GovernanceFactor::STATUS_ACTIVE,
                    'metadata' => (array) ($payload['metadata'] ?? []),
                ],
                GovernanceEventTypes::CREDENTIAL_REVOKED => self::revokeBinding($bindings, (string) $payload['factor_id']),
                default => null,
            };
        }

        return new IdentityRegistryProjection(
            entity: $entity,
            exists: $exists,
            username: $username !== '' ? $username : null,
            bindings: array_values($bindings),
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $bindings
     */
    private static function revokeBinding(array &$bindings, string $factorId): void
    {
        unset($bindings[$factorId]);
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
