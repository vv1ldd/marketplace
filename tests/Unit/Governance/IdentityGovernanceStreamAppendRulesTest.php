<?php

namespace Tests\Unit\Governance;

use App\Services\Identity\Governance\GovernanceEvent;
use App\Services\Identity\Governance\GovernanceEventTypes;
use App\Services\Identity\Governance\IdentityGovernanceStreamAppendRules;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IdentityGovernanceStreamAppendRulesTest extends TestCase
{
    private const ENTITY = 'sl1e_stream_rules';

    #[Test]
    public function genesis_must_be_identity_created_at_version_one(): void
    {
        IdentityGovernanceStreamAppendRules::validate(null, new GovernanceEvent(
            GovernanceEventTypes::IDENTITY_CREATED,
            self::ENTITY,
            1,
        ));

        $this->expectException(\InvalidArgumentException::class);
        IdentityGovernanceStreamAppendRules::validate(null, new GovernanceEvent(
            GovernanceEventTypes::CREDENTIAL_BOUND,
            self::ENTITY,
            1,
            ['factor_id' => '11111111-1111-1111-1111-111111111111', 'class' => 'knowledge', 'type' => 'recovery_code'],
        ));
    }

    #[Test]
    public function credential_bound_rejected_before_stream_exists(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Genesis event must be identity.created');

        IdentityGovernanceStreamAppendRules::validate(null, new GovernanceEvent(
            GovernanceEventTypes::CREDENTIAL_BOUND,
            self::ENTITY,
            1,
            ['factor_id' => '11111111-1111-1111-1111-111111111111'],
        ));
    }

    #[Test]
    public function stream_version_is_strictly_monotonic_with_no_gaps(): void
    {
        $events = [
            new GovernanceEvent(GovernanceEventTypes::IDENTITY_CREATED, self::ENTITY, 1),
            new GovernanceEvent(GovernanceEventTypes::IDENTITY_USERNAME_ASSIGNED, self::ENTITY, 2, ['username' => 'alice']),
            new GovernanceEvent(GovernanceEventTypes::CREDENTIAL_BOUND, self::ENTITY, 3, [
                'factor_id' => '11111111-1111-1111-1111-111111111111',
                'class' => 'knowledge',
                'type' => 'recovery_code',
            ]),
        ];

        IdentityGovernanceStreamAppendRules::validateMonotonicStream($events);

        $this->expectException(\InvalidArgumentException::class);
        IdentityGovernanceStreamAppendRules::validate(3, new GovernanceEvent(
            GovernanceEventTypes::CREDENTIAL_REVOKED,
            self::ENTITY,
            5,
            ['factor_id' => '11111111-1111-1111-1111-111111111111'],
        ));
    }

    #[Test]
    public function backdated_append_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        IdentityGovernanceStreamAppendRules::validate(3, new GovernanceEvent(
            GovernanceEventTypes::POLICY_DECLARED,
            self::ENTITY,
            3,
            ['version' => 1, 'rule' => 'all', 'required_factor_classes' => ['knowledge']],
        ));
    }
}
