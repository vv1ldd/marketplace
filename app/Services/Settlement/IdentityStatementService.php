<?php

namespace App\Services\Settlement;

use App\Models\IdentityPaymentAccountingEvent;
use App\Models\VaultIdentity;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class IdentityStatementService
{
    public const CONTRACT_NAME = 'identity-statement';

    public const CONTRACT_VERSION = 'v1';

    public const STATEMENT_VERSION = 'identity-statement:v1';

    private const ORIGIN_COMPENSATION = 'compensation';

    /**
     * @return array<string, mixed>
     */
    public function build(string $identityId, Carbon $from, Carbon $to, string $asset = 'USDC'): array
    {
        $identityId = strtolower(trim($identityId));
        $asset = strtoupper(trim($asset));

        $lines = [];
        foreach ($this->accountingEventsForIdentity($identityId, $asset, $from, $to) as $event) {
            foreach ($this->linesFromAccountingEvent($event, $identityId) as $line) {
                $lines[] = $line;
            }
        }

        $openingBalance = $this->balanceBefore($identityId, $asset, $from);
        $netChange = $this->sumSignedAmounts(array_column($lines, 'signed_amount'));
        $totals = $this->buildTotals($lines);
        $totals['net_change'] = $this->formatSigned($netChange);

        return [
            'contract' => [
                'name' => self::CONTRACT_NAME,
                'version' => self::CONTRACT_VERSION,
            ],
            'statement_version' => self::STATEMENT_VERSION,
            'identity_id' => $identityId,
            'asset' => $asset,
            'period' => [
                'from' => $from->toJSON(),
                'to' => $to->toJSON(),
            ],
            'derivation' => 'accounting_history_only',
            'opening_balance' => $this->formatSigned($openingBalance),
            'closing_balance' => $this->formatSigned(bcadd($openingBalance, $netChange, 8)),
            'totals' => $totals,
            'lines' => $lines,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forVaultParticipant(
        VaultIdentity $vault,
        Carbon $from,
        Carbon $to,
        ?string $asset = null,
    ): array {
        if (! (bool) config('identity_payments.enabled', false)) {
            throw ValidationException::withMessages([
                'payment' => 'Identity payments are disabled.',
            ]);
        }

        return $this->build(
            strtolower(trim((string) $vault->anchor_address)),
            $from,
            $to,
            strtoupper(trim($asset ?: 'USDC')),
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, IdentityPaymentAccountingEvent>
     */
    private function accountingEventsForIdentity(
        string $identityId,
        string $asset,
        Carbon $from,
        Carbon $to,
    ) {
        return IdentityPaymentAccountingEvent::query()
            ->where('asset', $asset)
            ->where('recorded_at', '>=', $from)
            ->where('recorded_at', '<=', $to)
            ->where(function ($query) use ($identityId) {
                $query->where('sender_identity_id', $identityId)
                    ->orWhere('receiver_identity_id', $identityId);
            })
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function linesFromAccountingEvent(IdentityPaymentAccountingEvent $event, string $identityId): array
    {
        $entries = (array) data_get($event->metadata, 'entries', []);
        if ($entries === []) {
            return [];
        }

        $originKind = (string) data_get($event->metadata, 'origin.kind', 'payment');
        $provenance = [
            'source' => 'accounting_event',
            'accounting_event_id' => $event->id,
        ];

        $paymentIntentUuid = data_get($event->metadata, 'origin.payment_intent_uuid');
        if (is_string($paymentIntentUuid) && $paymentIntentUuid !== '') {
            $provenance['payment_intent_id'] = $paymentIntentUuid;
        }

        $feeAmount = $this->senderFeeAmount($event, $entries, $identityId);
        $lines = [];
        $lineIndex = 0;
        $senderDebitHandled = false;

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (($entry['account'] ?? null) === 'platform_fee') {
                if ($this->isSender($event, $identityId)) {
                    $fee = $this->positiveAmount((string) ($entry['delta'] ?? '0'));
                    if (bccomp($fee, '0', 8) > 0) {
                        $lines[] = $this->makeLine('fee', '-'.$this->trimZeros($fee), $event, $provenance, $lineIndex++);
                    }
                }

                continue;
            }

            $entryIdentityId = strtolower(trim((string) ($entry['identity_id'] ?? '')));
            if ($entryIdentityId !== $identityId) {
                continue;
            }

            $signed = $this->normalizeSigned((string) ($entry['delta'] ?? '0'));
            if (bccomp($this->stripSign($signed), '0', 8) === 0) {
                continue;
            }

            if (str_starts_with($signed, '-')) {
                $principal = bcsub($this->stripSign($signed), $feeAmount, 8);
                if (bccomp($principal, '0', 8) > 0) {
                    $lines[] = $this->makeLine(
                        'outbound_payment',
                        '-'.$this->trimZeros($principal),
                        $event,
                        $provenance,
                        $lineIndex++,
                    );
                    $senderDebitHandled = true;
                }
            } else {
                $type = $originKind === self::ORIGIN_COMPENSATION ? 'compensation' : 'inbound_payment';
                $lines[] = $this->makeLine(
                    $type,
                    '+'.$this->trimZeros($this->stripSign($signed)),
                    $event,
                    $provenance,
                    $lineIndex++,
                );
            }
        }

        if (! $senderDebitHandled && $this->isSender($event, $identityId) && bccomp($feeAmount, '0', 8) > 0) {
            $lines[] = $this->makeLine('fee', '-'.$this->trimZeros($feeAmount), $event, $provenance, $lineIndex++);
        }

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, string>
     */
    private function buildTotals(array $lines): array
    {
        $totals = [
            'inbound' => '0',
            'outbound' => '0',
            'fees' => '0',
            'compensations' => '0',
            'net_change' => '0',
        ];

        foreach ($lines as $line) {
            $signed = (string) ($line['signed_amount'] ?? '0');
            $amount = $this->stripSign($signed);

            match ($line['type'] ?? '') {
                'inbound_payment' => $totals['inbound'] = bcadd($totals['inbound'], $amount, 8),
                'outbound_payment' => $totals['outbound'] = bcadd($totals['outbound'], $amount, 8),
                'fee' => $totals['fees'] = bcadd($totals['fees'], $amount, 8),
                'compensation' => $totals['compensations'] = bcadd($totals['compensations'], $amount, 8),
                default => null,
            };
        }

        return [
            'inbound' => $this->formatSigned('+'.$this->trimZeros($totals['inbound'])),
            'outbound' => $this->formatSigned('-'.$this->trimZeros($totals['outbound'])),
            'fees' => $this->formatSigned('-'.$this->trimZeros($totals['fees'])),
            'compensations' => $this->formatSigned('+'.$this->trimZeros($totals['compensations'])),
            'net_change' => '0',
        ];
    }

    private function balanceBefore(string $identityId, string $asset, Carbon $before): string
    {
        $balance = '0';

        IdentityPaymentAccountingEvent::query()
            ->where('asset', $asset)
            ->where('recorded_at', '<', $before)
            ->where(function ($query) use ($identityId) {
                $query->where('sender_identity_id', $identityId)
                    ->orWhere('receiver_identity_id', $identityId);
            })
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->cursor()
            ->each(function (IdentityPaymentAccountingEvent $event) use (&$balance, $identityId): void {
                foreach ($this->linesFromAccountingEvent($event, $identityId) as $line) {
                    $balance = bcadd($balance, (string) ($line['signed_amount'] ?? '0'), 8);
                }
            });

        return $balance;
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    private function senderFeeAmount(
        IdentityPaymentAccountingEvent $event,
        array $entries,
        string $identityId,
    ): string {
        if (! $this->isSender($event, $identityId)) {
            return '0';
        }

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (($entry['account'] ?? null) === 'platform_fee') {
                return $this->positiveAmount((string) ($entry['delta'] ?? '0'));
            }
        }

        $feeAmount = (string) data_get($event->metadata, 'fee_decision.fee_amount', '0');

        return $this->positiveAmount($feeAmount);
    }

    /**
     * @param  array<string, mixed>  $provenance
     * @return array<string, mixed>
     */
    private function makeLine(
        string $type,
        string $signedAmount,
        IdentityPaymentAccountingEvent $event,
        array $provenance,
        int $lineIndex,
    ): array {
        $signedAmount = $this->formatSigned($signedAmount);
        $paymentIntentId = $provenance['payment_intent_id'] ?? null;
        $drilldownAvailable = is_string($paymentIntentId) && $paymentIntentId !== '';

        return [
            'line_id' => sprintf('%s:%d:%s:%d', self::STATEMENT_VERSION, $event->id, $type, $lineIndex),
            'type' => $type,
            'amount' => $this->stripSign($signedAmount),
            'signed_amount' => $signedAmount,
            'asset' => $event->asset,
            'occurred_at' => $event->recorded_at?->toJSON(),
            'narrative' => $event->narrative,
            'explainable' => true,
            'drilldown_available' => $drilldownAvailable,
            'provenance' => $provenance,
        ];
    }

    private function isSender(IdentityPaymentAccountingEvent $event, string $identityId): bool
    {
        return strtolower(trim((string) $event->sender_identity_id)) === $identityId;
    }

    /**
     * @param  list<string>  $signedAmounts
     */
    private function sumSignedAmounts(array $signedAmounts): string
    {
        $total = '0';

        foreach ($signedAmounts as $signedAmount) {
            $total = bcadd($total, $this->toBcAmount((string) $signedAmount), 8);
        }

        return $total;
    }

    private function toBcAmount(string $amount): string
    {
        $normalized = trim($amount);

        if ($normalized === '' || $normalized === '+' || $normalized === '-') {
            return '0';
        }

        if (str_starts_with($normalized, '+')) {
            return ltrim($normalized, '+');
        }

        return $normalized;
    }

    private function formatSigned(string $amount): string
    {
        $bcAmount = $this->toBcAmount($amount);

        if (bccomp($bcAmount, '0', 8) === 0) {
            return '0';
        }

        if (str_starts_with($bcAmount, '-')) {
            return '-'.$this->trimZeros(ltrim($bcAmount, '-'));
        }

        return '+'.$this->trimZeros($bcAmount);
    }

    private function normalizeSigned(string $amount): string
    {
        return $this->toBcAmount($amount);
    }

    private function stripSign(string $amount): string
    {
        return $this->trimZeros(ltrim($this->toBcAmount($amount), '-'));
    }

    private function positiveAmount(string $delta): string
    {
        return $this->stripSign($delta);
    }

    private function trimZeros(string $amount): string
    {
        if (! str_contains($amount, '.')) {
            return $amount === '' ? '0' : $amount;
        }

        $trimmed = rtrim(rtrim($amount, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }
}
