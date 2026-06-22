<?php

namespace App\Services\Settlement;

use App\Models\IdentityPaymentAccountingEvent;
use App\Models\IdentityPaymentIntent;
use Illuminate\Support\Carbon;

class PaymentLimitAccountingStateService
{
    public const MODE_GROSS_OUTBOUND = 'gross_outbound';

    public const MODE_NET_OUTBOUND = 'net_outbound';

    public function dailyConsumedBefore(
        string $identityId,
        string $asset,
        string $consumptionMode,
        ?Carbon $asOf = null,
    ): string {
        $identityId = strtolower(trim($identityId));
        $asset = strtoupper(trim($asset));
        $asOf ??= now();
        $startOfDay = $asOf->copy()->startOfDay();

        $grossOutbound = $this->sumOutboundAccountingAmounts($identityId, $asset, $startOfDay, $asOf);

        if ($consumptionMode === self::MODE_GROSS_OUTBOUND) {
            return $grossOutbound;
        }

        $reversalCredits = $this->sumReversalCreditAmounts($identityId, $asset, $startOfDay, $asOf);

        return $this->subtractAmount($grossOutbound, $reversalCredits);
    }

    private function sumOutboundAccountingAmounts(
        string $identityId,
        string $asset,
        Carbon $startOfDay,
        Carbon $asOf,
    ): string {
        $total = '0';

        IdentityPaymentAccountingEvent::query()
            ->where('sender_identity_id', $identityId)
            ->where('asset', $asset)
            ->where('recorded_at', '>=', $startOfDay)
            ->where('recorded_at', '<=', $asOf)
            ->whereHas('intent', fn ($query) => $query->where('status', IdentityPaymentIntent::STATUS_EXECUTED))
            ->cursor()
            ->each(function (IdentityPaymentAccountingEvent $event) use (&$total): void {
                $total = bcadd($total, $this->normalizeAmount((string) $event->amount), 6);
            });

        return $total;
    }

    private function sumReversalCreditAmounts(
        string $identityId,
        string $asset,
        Carbon $startOfDay,
        Carbon $asOf,
    ): string {
        $total = '0';

        IdentityPaymentAccountingEvent::query()
            ->where('receiver_identity_id', $identityId)
            ->where('asset', $asset)
            ->where('recorded_at', '>=', $startOfDay)
            ->where('recorded_at', '<=', $asOf)
            ->whereHas('intent', fn ($query) => $query
                ->where('status', IdentityPaymentIntent::STATUS_EXECUTED)
                ->whereNotNull('reversal_of_intent_id'))
            ->cursor()
            ->each(function (IdentityPaymentAccountingEvent $event) use (&$total): void {
                $total = bcadd($total, $this->normalizeAmount((string) $event->amount), 6);
            });

        return $total;
    }

    private function subtractAmount(string $left, string $right): string
    {
        $result = bcsub($this->normalizeAmount($left), $this->normalizeAmount($right), 6);

        return bccomp($result, '0', 6) < 0 ? '0' : $this->trimTrailingZeros($result);
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

        return $this->trimTrailingZeros($normalized);
    }

    private function trimTrailingZeros(string $amount): string
    {
        if (! str_contains($amount, '.')) {
            return $amount;
        }

        $trimmed = rtrim(rtrim($amount, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }
}
