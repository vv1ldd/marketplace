<?php

namespace App\Services\Settlement;

use App\Models\IdentityPaymentAccountingEvent;

class PaymentFeeAccountingDerivationService
{
    /**
     * @return array{
     *     payment_amount: string,
     *     fee_amount: string,
     *     sender_total_debit: string
     * }
     */
    public function deriveSenderEconomics(IdentityPaymentAccountingEvent $event): array
    {
        $entries = (array) data_get($event->metadata, 'entries', []);
        $senderIdentityId = strtolower(trim((string) $event->sender_identity_id));
        $asset = strtoupper(trim((string) $event->asset));

        $senderDebit = '0';
        $receiverCredit = '0';
        $platformFee = '0';

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (strtoupper((string) ($entry['asset'] ?? $asset)) !== $asset) {
                continue;
            }

            $delta = (string) ($entry['delta'] ?? '0');

            if (isset($entry['account']) && (string) $entry['account'] === 'platform_fee') {
                $platformFee = $this->positiveAmount($delta);

                continue;
            }

            $entryIdentityId = strtolower(trim((string) ($entry['identity_id'] ?? '')));

            if ($entryIdentityId === $senderIdentityId) {
                $senderDebit = $this->positiveAmount($delta);
            }

            if ($entryIdentityId === strtolower(trim((string) $event->receiver_identity_id))) {
                $receiverCredit = $this->positiveAmount($delta);
            }
        }

        if ($entries === []) {
            return [
                'payment_amount' => $this->normalizeAmount((string) $event->amount),
                'fee_amount' => '0',
                'sender_total_debit' => $this->normalizeAmount((string) $event->amount),
            ];
        }

        return [
            'payment_amount' => $receiverCredit,
            'fee_amount' => $platformFee,
            'sender_total_debit' => $senderDebit,
        ];
    }

    private function positiveAmount(string $delta): string
    {
        $normalized = ltrim($this->normalizeAmount($delta), '+-');

        return $normalized === '' ? '0' : $normalized;
    }

    private function normalizeAmount(string $amount): string
    {
        $normalized = trim($amount);

        if ($normalized === '') {
            return '0';
        }

        if (! str_contains($normalized, '.')) {
            return $normalized;
        }

        return rtrim(rtrim($normalized, '0'), '.') ?: '0';
    }
}
