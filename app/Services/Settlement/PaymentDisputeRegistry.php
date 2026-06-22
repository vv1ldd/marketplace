<?php

namespace App\Services\Settlement;

use App\Models\IdentityPaymentDispute;
use InvalidArgumentException;

class PaymentDisputeRegistry
{
    /**
     * @return list<string>
     */
    public function allowedTransitions(string $fromStatus): array
    {
        return match ($fromStatus) {
            IdentityPaymentDispute::STATUS_OPENED => [
                IdentityPaymentDispute::STATUS_EVIDENCE_REQUESTED,
                IdentityPaymentDispute::STATUS_REVIEWED,
            ],
            IdentityPaymentDispute::STATUS_EVIDENCE_REQUESTED => [
                IdentityPaymentDispute::STATUS_EVIDENCE_COLLECTED,
            ],
            IdentityPaymentDispute::STATUS_EVIDENCE_COLLECTED => [
                IdentityPaymentDispute::STATUS_REVIEWED,
            ],
            IdentityPaymentDispute::STATUS_REVIEWED => [
                IdentityPaymentDispute::STATUS_RESOLVED,
            ],
            default => [],
        };
    }

    public function assertTransition(string $fromStatus, string $toStatus): void
    {
        if (! in_array($toStatus, $this->allowedTransitions($fromStatus), true)) {
            throw new InvalidArgumentException(
                "Dispute cannot transition from [{$fromStatus}] to [{$toStatus}].",
            );
        }
    }

    public function eventForStatus(string $status): string
    {
        return match ($status) {
            IdentityPaymentDispute::STATUS_EVIDENCE_REQUESTED => IdentityPaymentDispute::EVENT_EVIDENCE_REQUESTED,
            IdentityPaymentDispute::STATUS_EVIDENCE_COLLECTED => IdentityPaymentDispute::EVENT_EVIDENCE_COLLECTED,
            IdentityPaymentDispute::STATUS_REVIEWED => IdentityPaymentDispute::EVENT_REVIEWED,
            IdentityPaymentDispute::STATUS_RESOLVED => IdentityPaymentDispute::EVENT_RESOLVED,
            default => $status,
        };
    }
}
