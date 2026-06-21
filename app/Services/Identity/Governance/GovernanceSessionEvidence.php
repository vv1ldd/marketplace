<?php

namespace App\Services\Identity\Governance;

/**
 * Session-scoped proof that a factor was satisfied. Verifier method is intentionally
 * excluded (Invariant 6 — Verifier Independence).
 */
final class GovernanceSessionEvidence
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $factorId,
    ) {}

    public static function fromVerifiedEvent(GovernanceEvent $event): self
    {
        if (GovernanceEventTypes::normalize($event->type) !== GovernanceEventTypes::EVIDENCE_VERIFIED) {
            throw new \InvalidArgumentException('Expected an evidence.verified event.');
        }

        return new self(
            sessionId: (string) ($event->payload['session_id'] ?? ''),
            factorId: (string) ($event->payload['factor_id'] ?? ''),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'factor_id' => $this->factorId,
        ];
    }
}
