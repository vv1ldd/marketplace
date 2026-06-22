<?php

namespace App\Services\Settlement;

use App\Models\IdentityBinding;
use Illuminate\Support\Carbon;

class PaymentFeeQuoteService
{
    public function __construct(
        private readonly PaymentFeePolicyRegistry $fees,
        private readonly PaymentLimitPolicyEvaluator $railClassifier,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function quote(
        string $identityId,
        ?string $payerAlias,
        string $asset,
        string $amount,
        string $network,
        IdentityBinding $senderBinding,
        ?string $policyVersionKey = null,
        ?Carbon $evaluatedAt = null,
    ): array {
        $policyVersionKey ??= $this->fees->activeVersionKey();
        $evaluatedAt ??= now();
        $normalizedAsset = strtoupper(trim($asset));
        $normalizedAmount = $this->normalizeAmount($amount);
        $railCategory = $this->railClassifier->railCategory($senderBinding);
        $payer = $this->payerLabel($payerAlias, $identityId);

        $base = [
            'policy_key' => $this->fees->versionLabel($policyVersionKey),
            'policy_version_key' => $policyVersionKey,
            'asset' => $normalizedAsset,
            'amount' => $normalizedAmount,
            'rail' => $network,
            'rail_category' => $railCategory,
            'payer' => $payer,
            'payer_identity_id' => strtolower(trim($identityId)),
            'evaluated_at' => $evaluatedAt->toJSON(),
            'ruleset_hash' => $this->fees->rulesetHash($policyVersionKey),
        ];

        if ($railCategory === null) {
            return array_merge($base, [
                'applicable' => false,
                'fee_type' => null,
                'fee_bps' => 0,
                'fee_amount' => '0',
                'reason' => 'fee_policy_not_applicable_for_rail',
            ]);
        }

        $feeRule = $this->fees->feeRuleFor($policyVersionKey, $railCategory, $normalizedAsset);
        if ($feeRule === null) {
            return array_merge($base, [
                'applicable' => false,
                'fee_type' => null,
                'fee_bps' => 0,
                'fee_amount' => '0',
                'reason' => 'fee_policy_not_defined_for_asset',
            ]);
        }

        $feeAmount = $this->calculatePercentageFee($normalizedAmount, $feeRule['bps']);

        return array_merge($base, [
            'applicable' => true,
            'fee_type' => $feeRule['type'],
            'fee_bps' => $feeRule['bps'],
            'fee_amount' => $feeAmount,
            'reason' => 'quoted',
        ]);
    }

    private function calculatePercentageFee(string $amount, int $bps): string
    {
        $fee = bcdiv(bcmul($this->normalizeAmount($amount), (string) $bps, 8), '10000', 8);

        return $this->trimTrailingZeros($fee);
    }

    private function payerLabel(?string $payerAlias, string $identityId): string
    {
        $alias = ltrim(trim((string) $payerAlias), '@');

        return $alias !== '' ? $alias : strtolower(trim($identityId));
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
