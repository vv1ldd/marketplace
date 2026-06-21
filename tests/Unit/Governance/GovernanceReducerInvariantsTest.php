<?php

namespace Tests\Unit\Governance;

use App\Services\Identity\Governance\GovernanceEvent;
use App\Services\Identity\Governance\GovernanceEventTypes;
use App\Services\Identity\Governance\GovernanceFactor;
use App\Services\Identity\Governance\GovernancePolicy;
use App\Services\Identity\Governance\GovernanceReducer;
use App\Services\Identity\Governance\GovernanceSessionEvidence;
use App\Services\Identity\Governance\IdentityRegistryReducer;
use App\Services\Identity\Governance\SessionPolicyEngine;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GovernanceReducerInvariantsTest extends TestCase
{
    private const ENTITY = 'sl1e_test_invariants';

    #[Test]
    public function replay_determinism_same_log_same_projection(): void
    {
        $events = $this->sampleGoldPathEvents();

        $first = GovernanceReducer::fold($events)->toArray();
        $second = GovernanceReducer::fold($events)->toArray();

        $this->assertSame($first, $second);
    }

    #[Test]
    public function revocation_wins_after_bind_and_revoke(): void
    {
        $factorId = '11111111-1111-1111-1111-111111111111';

        $events = [
            $this->policyDeclared(1),
            $this->credentialBound(2, $factorId, 'knowledge', 'recovery_code'),
            $this->credentialRevoked(3, $factorId),
        ];

        $projection = GovernanceReducer::fold($events);

        $this->assertSame([], $projection->fulfilledClasses);
        $this->assertSame('bronze', $projection->protectionTier);
        $this->assertSame('unavailable', $projection->recoveryState);
    }

    #[Test]
    public function tier_is_always_derived_not_injected(): void
    {
        $projection = GovernanceReducer::fold($this->sampleGoldPathEvents());

        $this->assertSame('gold', $projection->protectionTier);
        $this->assertSame('ready', $projection->recoveryState);
        $this->assertContains('knowledge', $projection->fulfilledClasses);
        $this->assertContains('possession', $projection->fulfilledClasses);
    }

    #[Test]
    public function verified_events_do_not_affect_materialized_projection(): void
    {
        $withVerified = array_merge($this->sampleGoldPathEvents(), [
            new GovernanceEvent(
                type: GovernanceReducer::EVENT_FACTOR_VERIFIED,
                entity: self::ENTITY,
                sequence: 10,
                payload: [
                    'session_id' => 'session-a',
                    'factor_id' => '11111111-1111-1111-1111-111111111111',
                ],
            ),
        ]);

        $baseline = GovernanceReducer::fold($this->sampleGoldPathEvents())->toArray();
        $withSessionEvidence = GovernanceReducer::fold($withVerified)->toArray();

        $this->assertSame($baseline, $withSessionEvidence);
    }

    #[Test]
    public function policy_changes_are_forward_only(): void
    {
        $prefix = [
            $this->policyDeclared(1, ['knowledge', 'possession']),
            $this->credentialBound(2, '11111111-1111-1111-1111-111111111111', 'knowledge', 'recovery_code'),
            $this->credentialBound(3, '22222222-2222-2222-2222-222222222222', 'possession', 'passkey', 'recovery', [
                'device_id' => 'iphone',
                'ecosystem' => 'apple',
                'custody' => 'self',
            ]),
            $this->credentialBound(4, '33333333-3333-3333-3333-333333333333', 'possession', 'passkey', 'recovery', [
                'device_id' => 'pixel',
                'ecosystem' => 'android',
                'custody' => 'self',
            ]),
        ];

        $beforeStricterPolicy = GovernanceReducer::fold($prefix);
        $this->assertSame('gold', $beforeStricterPolicy->protectionTier);

        $full = array_merge($prefix, [
            $this->policyDeclared(5, ['knowledge', 'possession', 'social']),
        ]);

        $afterStricterPolicy = GovernanceReducer::fold($full);
        $this->assertSame('silver', $afterStricterPolicy->protectionTier);
        $this->assertSame('incomplete', $afterStricterPolicy->recoveryState);

        $rebuiltPrefix = GovernanceReducer::fold($prefix);
        $this->assertSame($beforeStricterPolicy->toArray(), $rebuiltPrefix->toArray());
    }

    #[Test]
    public function snapshot_replay_equivalence_with_random_histories(): void
    {
        for ($iteration = 0; $iteration < 1000; $iteration++) {
            $events = $this->generateRandomHistory(random_int(3, 40));

            $full = GovernanceReducer::fold($events);

            $splitAt = random_int(1, count($events) - 1);
            $prefix = array_slice($events, 0, $splitAt);
            $suffix = array_slice($events, $splitAt);

            $snapshot = GovernanceReducer::foldToSnapshot($prefix);
            $fromSnapshot = GovernanceReducer::foldFromSnapshot($snapshot, $suffix);

            $this->assertSame(
                $full->toArray(),
                $fromSnapshot->toArray(),
                "Snapshot replay diverged on iteration {$iteration} at split {$splitAt}",
            );
        }
    }

    #[Test]
    public function snapshot_is_idempotent(): void
    {
        for ($iteration = 0; $iteration < 500; $iteration++) {
            $events = $this->generateRandomHistory(random_int(3, 30));
            $snapshot = GovernanceReducer::foldToSnapshot($events);
            $once = GovernanceReducer::reSnapshot($snapshot);
            $twice = GovernanceReducer::reSnapshot(GovernanceReducer::reSnapshot($snapshot));

            $this->assertTrue(
                GovernanceReducer::snapshotsEqual($snapshot, $once),
                "reSnapshot changed canonical form on iteration {$iteration}",
            );
            $this->assertTrue(
                GovernanceReducer::snapshotsEqual($once, $twice),
                "reSnapshot is not idempotent on iteration {$iteration}",
            );
        }
    }

    #[Test]
    public function verifier_independence_reducer_ignores_verification_method(): void
    {
        $baseline = GovernanceReducer::fold($this->sampleGoldPathEvents())->toArray();

        foreach (['webauthn', 'code_hash', 'guardian_attestation', 'signature'] as $method) {
            $withMethod = array_merge($this->sampleGoldPathEvents(), [
                $this->factorVerified(10, 'session-a', '11111111-1111-1111-1111-111111111111', $method),
                $this->factorVerified(11, 'session-a', '22222222-2222-2222-2222-222222222222', $method),
            ]);

            $this->assertSame($baseline, GovernanceReducer::fold($withMethod)->toArray(), $method);
        }
    }

    #[Test]
    public function verifier_independence_session_engine_is_method_blind(): void
    {
        $policy = GovernancePolicy::fromPayload([
            'version' => 1,
            'rule' => 'all',
            'required_factor_classes' => ['knowledge', 'possession'],
            'minimum_independent_dimensions' => 2,
        ]);

        $knowledgeId = '11111111-1111-1111-1111-111111111111';
        $possessionA = '22222222-2222-2222-2222-222222222222';
        $possessionB = '33333333-3333-3333-3333-333333333333';

        $enrolled = [
            GovernanceFactor::fromBoundPayload([
                'factor_id' => $knowledgeId,
                'class' => 'knowledge',
                'type' => 'recovery_code',
            ]),
            GovernanceFactor::fromBoundPayload([
                'factor_id' => $possessionA,
                'class' => 'possession',
                'type' => 'passkey',
                'purpose' => 'recovery',
                'metadata' => ['device_id' => 'iphone', 'ecosystem' => 'apple', 'custody' => 'self'],
            ]),
            GovernanceFactor::fromBoundPayload([
                'factor_id' => $possessionB,
                'class' => 'possession',
                'type' => 'passkey',
                'purpose' => 'recovery',
                'metadata' => ['device_id' => 'pixel', 'ecosystem' => 'android', 'custody' => 'self'],
            ]),
        ];

        $decisions = [];

        foreach (['webauthn', 'code_hash', 'guardian_attestation'] as $method) {
            $evidence = [
                GovernanceSessionEvidence::fromVerifiedEvent(
                    $this->factorVerified(1, 'session-x', $knowledgeId, $method),
                ),
                GovernanceSessionEvidence::fromVerifiedEvent(
                    $this->factorVerified(2, 'session-x', $possessionA, $method),
                ),
                GovernanceSessionEvidence::fromVerifiedEvent(
                    $this->factorVerified(3, 'session-x', $possessionB, $method),
                ),
            ];

            $decisions[$method] = SessionPolicyEngine::evaluate($policy, $enrolled, $evidence);
        }

        $this->assertSame(
            [
                'webauthn' => SessionPolicyEngine::DECISION_ALLOW,
                'code_hash' => SessionPolicyEngine::DECISION_ALLOW,
                'guardian_attestation' => SessionPolicyEngine::DECISION_ALLOW,
            ],
            $decisions,
        );
    }

    #[Test]
    public function registry_is_projection_from_shared_log(): void
    {
        $factorId = '11111111-1111-1111-1111-111111111111';

        $events = [
            new GovernanceEvent(GovernanceEventTypes::IDENTITY_CREATED, self::ENTITY, 1),
            new GovernanceEvent(GovernanceEventTypes::IDENTITY_USERNAME_ASSIGNED, self::ENTITY, 2, ['username' => 'alice']),
            $this->policyDeclared(3),
            $this->credentialBound(4, $factorId, 'knowledge', 'recovery_code'),
            $this->credentialRevoked(5, $factorId),
        ];

        $registry = IdentityRegistryReducer::fold($events);

        $this->assertTrue($registry->exists);
        $this->assertSame('alice', $registry->username);
        $this->assertSame([], $registry->bindings);

        $governance = GovernanceReducer::fold($events);
        $this->assertSame('bronze', $governance->protectionTier);
        $this->assertSame([], $governance->fulfilledClasses);
    }

    #[Test]
    public function legacy_event_types_preserve_governance_semantics(): void
    {
        $factorId = '22222222-2222-2222-2222-222222222222';

        $canonical = [
            new GovernanceEvent(GovernanceEventTypes::POLICY_DECLARED, self::ENTITY, 1, [
                'version' => 1,
                'rule' => 'all',
                'required_factor_classes' => ['knowledge'],
                'minimum_independent_dimensions' => 1,
            ]),
            new GovernanceEvent(GovernanceEventTypes::AUTHORITY_MODE_CHANGED, self::ENTITY, 2, ['mode' => 'provider']),
            $this->credentialBound(3, $factorId, 'knowledge', 'recovery_code'),
        ];

        $legacy = [
            new GovernanceEvent('recovery_policy.declared', self::ENTITY, 1, $canonical[0]->payload),
            new GovernanceEvent('root_authority.declared', self::ENTITY, 2, $canonical[1]->payload),
            $this->credentialBound(3, $factorId, 'knowledge', 'recovery_code'),
        ];

        $this->assertSame(
            GovernanceReducer::fold($canonical)->toArray(),
            GovernanceReducer::fold($legacy)->toArray(),
        );
    }

    #[Test]
    public function historical_compatibility_across_random_legacy_histories(): void
    {
        for ($iteration = 0; $iteration < 200; $iteration++) {
            $events = $this->generateRandomHistory(random_int(3, 25));

            $legacy = array_map(function (GovernanceEvent $event): GovernanceEvent {
                return match ($event->type) {
                    GovernanceEventTypes::POLICY_DECLARED => new GovernanceEvent(
                        'recovery_policy.declared',
                        $event->entity,
                        $event->sequence,
                        $event->payload,
                    ),
                    GovernanceEventTypes::AUTHORITY_MODE_CHANGED => new GovernanceEvent(
                        'root_authority.declared',
                        $event->entity,
                        $event->sequence,
                        $event->payload,
                    ),
                    default => $event,
                };
            }, $events);

            $this->assertSame(
                GovernanceReducer::fold($events)->toArray(),
                GovernanceReducer::fold($legacy)->toArray(),
                "Legacy compatibility failed on iteration {$iteration}",
            );
        }
    }

    /**
     * @return list<GovernanceEvent>
     */
    private function sampleGoldPathEvents(): array
    {
        return [
            $this->policyDeclared(1),
            $this->credentialBound(2, '11111111-1111-1111-1111-111111111111', 'knowledge', 'recovery_code'),
            $this->credentialBound(3, '22222222-2222-2222-2222-222222222222', 'possession', 'passkey', 'recovery', [
                'device_id' => 'iphone',
                'ecosystem' => 'apple',
                'custody' => 'self',
            ]),
            $this->credentialBound(4, '33333333-3333-3333-3333-333333333333', 'possession', 'passkey', 'recovery', [
                'device_id' => 'pixel',
                'ecosystem' => 'android',
                'custody' => 'self',
            ]),
        ];
    }

    /**
     * @param  list<string>  $classes
     */
    private function policyDeclared(int $sequence, array $classes = ['knowledge', 'possession']): GovernanceEvent
    {
        return new GovernanceEvent(
            type: GovernanceReducer::EVENT_POLICY_DECLARED,
            entity: self::ENTITY,
            sequence: $sequence,
            payload: [
                'version' => 1,
                'rule' => 'all',
                'required_factor_classes' => $classes,
                'minimum_independent_dimensions' => 2,
            ],
        );
    }

    /**
     * @param  array<string, string>  $metadata
     */
    private function credentialBound(
        int $sequence,
        string $factorId,
        string $class,
        string $type,
        ?string $purpose = null,
        array $metadata = [],
    ): GovernanceEvent {
        return new GovernanceEvent(
            type: GovernanceReducer::EVENT_CREDENTIAL_BOUND,
            entity: self::ENTITY,
            sequence: $sequence,
            payload: [
                'factor_id' => $factorId,
                'class' => $class,
                'type' => $type,
                'purpose' => $purpose,
                'metadata' => $metadata,
            ],
        );
    }

    private function credentialRevoked(int $sequence, string $factorId): GovernanceEvent
    {
        return new GovernanceEvent(
            type: GovernanceReducer::EVENT_CREDENTIAL_REVOKED,
            entity: self::ENTITY,
            sequence: $sequence,
            payload: ['factor_id' => $factorId],
        );
    }

    private function factorVerified(int $sequence, string $sessionId, string $factorId, string $method): GovernanceEvent
    {
        return new GovernanceEvent(
            type: GovernanceReducer::EVENT_FACTOR_VERIFIED,
            entity: self::ENTITY,
            sequence: $sequence,
            payload: [
                'session_id' => $sessionId,
                'factor_id' => $factorId,
                'verification_method' => $method,
            ],
        );
    }

    /**
     * @return list<GovernanceEvent>
     */
    private function generateRandomHistory(int $length): array
    {
        $events = [$this->policyDeclared(1)];
        $sequence = 2;
        $knownFactors = [];

        for ($i = 0; $i < $length - 1; $i++) {
            $roll = random_int(0, 99);

            if ($roll < 15) {
                $events[] = $this->policyDeclared(
                    $sequence,
                    (random_int(0, 1) === 0)
                        ? ['knowledge', 'possession']
                        : ['knowledge', 'possession', 'social'],
                );
                $sequence++;

                continue;
            }

            if ($roll < 25 && $knownFactors !== []) {
                $factorId = $knownFactors[array_rand($knownFactors)];
                $events[] = $this->credentialRevoked($sequence, $factorId);
                $sequence++;

                continue;
            }

            $factorId = sprintf('%08x-%04x-%04x-%04x-%012x', random_int(0, 0xffffffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffffffffffff));
            $knownFactors[] = $factorId;

            $class = ['knowledge', 'possession', 'social'][random_int(0, 2)];
            $type = match ($class) {
                'knowledge' => 'recovery_code',
                'social' => 'guardian',
                default => 'passkey',
            };

            $events[] = $this->credentialBound(
                $sequence,
                $factorId,
                $class,
                $type,
                $class === 'possession' ? 'recovery' : null,
                [
                    'device_id' => 'device-'.random_int(1, 5),
                    'ecosystem' => ['apple', 'google', 'android'][random_int(0, 2)],
                    'custody' => random_int(0, 1) === 0 ? 'self' : 'delegated',
                ],
            );
            $sequence++;
        }

        if ($knownFactors !== [] && random_int(0, 1) === 1) {
            $events[] = new GovernanceEvent(
                type: GovernanceReducer::EVENT_FACTOR_VERIFIED,
                entity: self::ENTITY,
                sequence: $sequence,
                payload: [
                    'session_id' => 'session-'.random_int(1000, 9999),
                    'factor_id' => $knownFactors[array_rand($knownFactors)],
                ],
            );
        }

        return $events;
    }
}
