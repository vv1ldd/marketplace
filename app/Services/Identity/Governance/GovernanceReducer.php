<?php

namespace App\Services\Identity\Governance;

final class GovernanceReducer
{
    /** @deprecated Use {@see GovernanceEventTypes} */
    public const EVENT_POLICY_DECLARED = GovernanceEventTypes::POLICY_DECLARED;

    /** @deprecated Use {@see GovernanceEventTypes} */
    public const EVENT_ROOT_AUTHORITY_DECLARED = GovernanceEventTypes::AUTHORITY_MODE_CHANGED;

    public const EVENT_CREDENTIAL_BOUND = GovernanceEventTypes::CREDENTIAL_BOUND;

    public const EVENT_CREDENTIAL_REVOKED = GovernanceEventTypes::CREDENTIAL_REVOKED;

    /** @deprecated Use {@see GovernanceEventTypes::EVIDENCE_VERIFIED} */
    public const EVENT_FACTOR_VERIFIED = GovernanceEventTypes::EVIDENCE_VERIFIED;

    /** @deprecated Use {@see GovernanceEventTypes::CONTINUITY_REESTABLISHED} */
    public const EVENT_RECOVERY_COMPLETED = GovernanceEventTypes::CONTINUITY_REESTABLISHED;

    /**
     * @param  list<GovernanceEvent>  $events
     */
    public static function fold(array $events): GovernanceProjection
    {
        $sorted = self::sortEvents($events);

        if ($sorted === []) {
            throw new \InvalidArgumentException('At least one governance event is required.');
        }

        $state = self::emptyState($sorted[0]->entity);

        foreach ($sorted as $event) {
            self::applyEvent($state, $event);
        }

        return self::deriveProjection($state);
    }

    /**
     * @param  list<GovernanceEvent>  $events
     */
    public static function foldToSnapshot(array $events): GovernanceSnapshot
    {
        $sorted = self::sortEvents($events);

        if ($sorted === []) {
            throw new \InvalidArgumentException('At least one governance event is required.');
        }

        $state = self::emptyState($sorted[0]->entity);
        $throughSequence = 0;

        foreach ($sorted as $event) {
            self::applyEvent($state, $event);
            $throughSequence = $event->sequence;
        }

        return self::reSnapshot(new GovernanceSnapshot(
            entity: $state['entity'],
            throughSequence: $throughSequence,
            rootAuthorityMode: $state['root_authority_mode'],
            currentPolicy: $state['current_policy'],
            factors: [...$state['factors']],
        ));
    }

    /**
     * Canonical, idempotent snapshot representation (Invariant — snapshot stability).
     */
    public static function reSnapshot(GovernanceSnapshot $snapshot): GovernanceSnapshot
    {
        $factors = $snapshot->factors;
        ksort($factors);

        return new GovernanceSnapshot(
            entity: $snapshot->entity,
            throughSequence: $snapshot->throughSequence,
            rootAuthorityMode: $snapshot->rootAuthorityMode,
            currentPolicy: $snapshot->currentPolicy,
            factors: $factors,
        );
    }

    public static function snapshotsEqual(GovernanceSnapshot $left, GovernanceSnapshot $right): bool
    {
        return $left->toArray() === $right->toArray();
    }

    /**
     * @param  list<GovernanceEvent>  $tailEvents
     */
    public static function foldFromSnapshot(GovernanceSnapshot $snapshot, array $tailEvents): GovernanceProjection
    {
        $state = [
            'entity' => $snapshot->entity,
            'root_authority_mode' => $snapshot->rootAuthorityMode,
            'current_policy' => $snapshot->currentPolicy,
            'factors' => [...$snapshot->factors],
        ];

        foreach (self::sortEvents($tailEvents) as $event) {
            if ($event->sequence <= $snapshot->throughSequence) {
                continue;
            }

            self::applyEvent($state, $event);
        }

        return self::deriveProjection($state);
    }

    /**
     * @return array{
     *     entity: string,
     *     root_authority_mode: string,
     *     current_policy: ?GovernancePolicy,
     *     factors: array<string, GovernanceFactor>
     * }
     */
    private static function emptyState(string $entity): array
    {
        return [
            'entity' => $entity,
            'root_authority_mode' => 'provider',
            'current_policy' => null,
            'factors' => [],
        ];
    }

    /**
     * @param  array{
     *     entity: string,
     *     root_authority_mode: string,
     *     current_policy: ?GovernancePolicy,
     *     factors: array<string, GovernanceFactor>
     * }  $state
     */
    private static function applyEvent(array &$state, GovernanceEvent $event): void
    {
        if ($event->entity !== $state['entity']) {
            throw new \InvalidArgumentException('All events must belong to the same entity.');
        }

        match (GovernanceEventTypes::normalize($event->type)) {
            GovernanceEventTypes::POLICY_DECLARED => $state['current_policy'] = GovernancePolicy::fromPayload(
                GovernanceEventPayloadNormalizer::normalize($event->type, $event->payload),
            ),
            GovernanceEventTypes::AUTHORITY_MODE_CHANGED => $state['root_authority_mode'] = (string) (GovernanceEventPayloadNormalizer::normalize($event->type, $event->payload)['mode'] ?? 'provider'),
            GovernanceEventTypes::CREDENTIAL_BOUND => $state['factors'][(string) GovernanceEventPayloadNormalizer::normalize($event->type, $event->payload)['factor_id']] = GovernanceFactor::fromBoundPayload(
                GovernanceEventPayloadNormalizer::normalize($event->type, $event->payload),
            ),
            GovernanceEventTypes::CREDENTIAL_REVOKED => self::revokeFactor(
                $state,
                (string) GovernanceEventPayloadNormalizer::normalize($event->type, $event->payload)['factor_id'],
            ),
            GovernanceEventTypes::IDENTITY_CREATED,
            GovernanceEventTypes::IDENTITY_USERNAME_ASSIGNED,
            GovernanceEventTypes::EVIDENCE_VERIFIED,
            GovernanceEventTypes::CONTINUITY_REESTABLISHED => null,
            default => throw new \InvalidArgumentException("Unsupported governance event type: {$event->type}"),
        };
    }

    /**
     * @param  array{
     *     entity: string,
     *     root_authority_mode: string,
     *     current_policy: ?GovernancePolicy,
     *     factors: array<string, GovernanceFactor>
     * }  $state
     */
    private static function revokeFactor(array &$state, string $factorId): void
    {
        if (! isset($state['factors'][$factorId])) {
            return;
        }

        $state['factors'][$factorId] = $state['factors'][$factorId]->withStatus(GovernanceFactor::STATUS_REVOKED);
    }

    /**
     * @param  array{
     *     entity: string,
     *     root_authority_mode: string,
     *     current_policy: ?GovernancePolicy,
     *     factors: array<string, GovernanceFactor>
     * }  $state
     */
    public static function deriveProjection(array $state): GovernanceProjection
    {
        $activeRecoveryFactors = self::activeRecoveryFactors($state['factors']);
        $fulfilledClasses = self::fulfilledClasses($activeRecoveryFactors);
        $independentDimensionsMet = self::independentDimensionsMet($activeRecoveryFactors);
        $policy = $state['current_policy'];
        $policySatisfied = self::policySatisfied($policy, $fulfilledClasses, $independentDimensionsMet);

        $recoveryState = self::deriveRecoveryState($activeRecoveryFactors, $policy, $policySatisfied);
        $protectionTier = self::deriveProtectionTier($activeRecoveryFactors, $policy, $policySatisfied);

        $activeFactors = array_values(array_map(
            static fn (GovernanceFactor $factor): array => $factor->toArray(),
            array_filter(
                $state['factors'],
                static fn (GovernanceFactor $factor): bool => $factor->status === GovernanceFactor::STATUS_ACTIVE,
            ),
        ));

        usort(
            $activeFactors,
            static fn (array $a, array $b): int => strcmp((string) $a['id'], (string) $b['id']),
        );

        return new GovernanceProjection(
            entity: $state['entity'],
            rootAuthorityMode: $state['root_authority_mode'],
            protectionTier: $protectionTier,
            recoveryState: $recoveryState,
            fulfilledClasses: $fulfilledClasses,
            independentDimensionsMet: $independentDimensionsMet,
            activeFactorCount: count($activeFactors),
            activeFactors: $activeFactors,
            currentPolicy: $policy,
        );
    }

    /**
     * @param  array<string, GovernanceFactor>  $factors
     * @return list<GovernanceFactor>
     */
    private static function activeRecoveryFactors(array $factors): array
    {
        return array_values(array_filter(
            $factors,
            static fn (GovernanceFactor $factor): bool => $factor->status === GovernanceFactor::STATUS_ACTIVE
                && ! $factor->isDailyLogin(),
        ));
    }

    /**
     * @param  list<GovernanceFactor>  $factors
     * @return list<string>
     */
    private static function fulfilledClasses(array $factors): array
    {
        $classes = [];

        foreach ($factors as $factor) {
            $classes[$factor->class] = true;
        }

        $classList = array_keys($classes);
        sort($classList);

        return $classList;
    }

    /**
     * @param  list<GovernanceFactor>  $factors
     */
    public static function independentDimensionsMetForFactors(array $factors): int
    {
        return self::independentDimensionsMet($factors);
    }

    /**
     * @param  list<GovernanceFactor>  $factors
     */
    private static function independentDimensionsMet(array $factors): int
    {
        if (count($factors) < 2) {
            return count($factors) === 1 ? 1 : 0;
        }

        $dimensions = ['device' => 'device_id', 'ecosystem' => 'ecosystem', 'custody' => 'custody'];
        $met = 0;

        foreach ($dimensions as $key => $field) {
            $values = [];

            foreach ($factors as $factor) {
                $value = $factor->metadata[$field] ?? null;

                if ($value === null || $value === '') {
                    continue;
                }

                $values[(string) $value] = true;
            }

            if (count($values) >= 2) {
                $met++;
            }
        }

        return $met;
    }

    /**
     * @param  list<string>  $fulfilledClasses
     */
    private static function policySatisfied(
        ?GovernancePolicy $policy,
        array $fulfilledClasses,
        int $independentDimensionsMet,
    ): bool {
        if ($policy === null) {
            return false;
        }

        if ($policy->rule === 'all') {
            foreach ($policy->requiredFactorClasses as $requiredClass) {
                if (! in_array($requiredClass, $fulfilledClasses, true)) {
                    return false;
                }
            }
        }

        return $independentDimensionsMet >= $policy->minimumIndependentDimensions;
    }

    /**
     * @param  list<GovernanceFactor>  $activeRecoveryFactors
     */
    private static function deriveRecoveryState(
        array $activeRecoveryFactors,
        ?GovernancePolicy $policy,
        bool $policySatisfied,
    ): string {
        if ($activeRecoveryFactors === []) {
            return 'unavailable';
        }

        if ($policy === null || ! $policySatisfied) {
            return 'incomplete';
        }

        return 'ready';
    }

    /**
     * @param  list<GovernanceFactor>  $activeRecoveryFactors
     */
    private static function deriveProtectionTier(
        array $activeRecoveryFactors,
        ?GovernancePolicy $policy,
        bool $policySatisfied,
    ): string {
        if ($activeRecoveryFactors === []) {
            return 'bronze';
        }

        if ($policySatisfied) {
            return 'gold';
        }

        return 'silver';
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
