<?php

namespace App\Services\Settlement;

use App\Models\IdentityBinding;
use Illuminate\Support\Carbon;

class PaymentLimitPolicyEvaluator
{
    public const RAIL_MANAGED_EVM = 'managed_evm';

    public function __construct(
        private readonly PaymentLimitPolicyRegistry $limits,
        private readonly PaymentLimitAccountingStateService $accountingState,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(
        string $identityId,
        string $asset,
        string $amount,
        string $network,
        IdentityBinding $senderBinding,
        ?string $policyVersionKey = null,
        ?Carbon $evaluatedAt = null,
    ): array {
        $policyVersionKey ??= $this->limits->activeVersionKey();
        $evaluatedAt ??= now();
        $normalizedAsset = strtoupper(trim($asset));
        $normalizedAmount = $this->normalizeAmount($amount);
        $railCategory = $this->railCategory($senderBinding);

        $base = [
            'policy_key' => $this->limits->versionLabel($policyVersionKey),
            'policy_version_key' => $policyVersionKey,
            'asset' => $normalizedAsset,
            'amount' => $normalizedAmount,
            'rail' => $network,
            'rail_category' => $railCategory,
            'evaluated_at' => $evaluatedAt->toJSON(),
            'ruleset_hash' => $this->limits->rulesetHash($policyVersionKey),
            'daily_consumption_mode' => $this->limits->dailyConsumptionMode($policyVersionKey),
        ];

        if ($railCategory === null) {
            return array_merge($base, [
                'approved' => true,
                'applicable' => false,
                'reason' => 'limit_policy_not_applicable_for_rail',
            ]);
        }

        $limitValues = $this->limits->limitsFor($policyVersionKey, $railCategory, $normalizedAsset);
        if ($limitValues === null) {
            return array_merge($base, [
                'approved' => true,
                'applicable' => false,
                'reason' => 'limit_policy_not_defined_for_asset',
            ]);
        }

        $dailyConsumedBefore = $this->accountingState->dailyConsumedBefore(
            $identityId,
            $normalizedAsset,
            $base['daily_consumption_mode'],
            $evaluatedAt,
        );
        $dailyRemainingBefore = $this->subtractAmount($limitValues['daily'], $dailyConsumedBefore);
        $dailyRemainingAfter = $this->subtractAmount($dailyRemainingBefore, $normalizedAmount);

        $approved = $this->compareAmount($normalizedAmount, $limitValues['per_transaction']) <= 0
            && $this->compareAmount($normalizedAmount, $dailyRemainingBefore) <= 0;

        $reason = match (true) {
            $this->compareAmount($normalizedAmount, $limitValues['per_transaction']) > 0 => 'per_transaction_limit_exceeded',
            $this->compareAmount($normalizedAmount, $dailyRemainingBefore) > 0 => 'daily_limit_exceeded',
            default => 'within_limits',
        };

        return array_merge($base, [
            'approved' => $approved,
            'applicable' => true,
            'reason' => $reason,
            'per_transaction_limit' => $limitValues['per_transaction'],
            'daily_limit' => $limitValues['daily'],
            'daily_consumed_before' => $dailyConsumedBefore,
            'daily_remaining_before' => $dailyRemainingBefore,
            'daily_remaining_after' => $approved ? $dailyRemainingAfter : $dailyRemainingBefore,
        ]);
    }

    public function railCategory(IdentityBinding $binding): ?string
    {
        if (! $binding->isVerified()) {
            return null;
        }

        $protocol = (string) data_get($binding->metadata, 'protocol', '');
        $source = (string) ($binding->binding_source ?? IdentityBinding::SOURCE_EXTERNAL);

        if ($source === IdentityBinding::SOURCE_MANAGED && $protocol === 'evm') {
            return self::RAIL_MANAGED_EVM;
        }

        return null;
    }

    private function compareAmount(string $left, string $right): int
    {
        return bccomp($this->normalizeAmount($left), $this->normalizeAmount($right), 6);
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
