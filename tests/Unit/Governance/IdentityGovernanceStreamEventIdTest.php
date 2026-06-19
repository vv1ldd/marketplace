<?php

namespace Tests\Unit\Governance;

use App\Services\Identity\Governance\IdentityGovernanceStreamEventId;
use App\Services\Identity\Governance\IdentityGovernanceStreamEventIdException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IdentityGovernanceStreamEventIdTest extends TestCase
{
    #[Test]
    public function accepts_producer_deterministic_idempotency_keys(): void
    {
        foreach ([
            'vault-create:user:42:identity.created',
            'vault-create:user:42:credential.bound',
            'chaos-dedupe-bind',
        ] as $eventId) {
            IdentityGovernanceStreamEventId::assertValid($eventId);
            $this->addToAssertionCount(1);
        }
    }

    #[Test]
    public function accepts_uuid_producer_keys(): void
    {
        IdentityGovernanceStreamEventId::assertValid('550e8400-e29b-41d4-a716-446655440000');
        $this->assertSame(255, IdentityGovernanceStreamEventId::MAX_LENGTH);
    }

    #[Test]
    public function rejects_empty_event_id(): void
    {
        $this->expectException(IdentityGovernanceStreamEventIdException::class);

        IdentityGovernanceStreamEventId::assertValid('');
    }

    #[Test]
    public function rejects_event_id_longer_than_storage_contract(): void
    {
        $this->expectException(IdentityGovernanceStreamEventIdException::class);

        IdentityGovernanceStreamEventId::assertValid(str_repeat('a', IdentityGovernanceStreamEventId::MAX_LENGTH + 1));
    }

    #[Test]
    public function rejects_invalid_characters(): void
    {
        $this->expectException(IdentityGovernanceStreamEventIdException::class);

        IdentityGovernanceStreamEventId::assertValid('vault create:user:1:bad');
    }
}
