<?php

namespace App\Services\Settlement;

use App\Models\IdentityBinding;

class SettlementInstrumentCapabilityService
{
    public const MATRIX_VERSION = 'instrument-capability:v1';

    /** @deprecated use CapabilityPolicyRegistry::versionLabel('v1') */
    public const CAPABILITY_POLICY_VERSION = 'instrument-capability-policy:v1';

    public function __construct(
        private readonly CapabilityPolicyRegistry $capabilityPolicies,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function matrixForBinding(IdentityBinding $binding, ?string $policyVersionKey = null): array
    {
        $policyVersionKey = $policyVersionKey ?? $this->capabilityPolicies->activeVersionKey();
        $policyVersionLabel = $this->capabilityPolicies->versionLabel($policyVersionKey);
        $protocol = (string) data_get($binding->metadata, 'protocol', '');
        $network = (string) $binding->binding_key;
        $verified = $binding->isVerified();
        $railType = $this->capabilityPolicies->bindingRailType($binding);
        $paymentRoutingAssets = $verified
            ? $this->capabilityPolicies->paymentRoutingAssetsForBinding($binding, $policyVersionKey)
            : [];

        $paymentRoutingEnabled = $verified && $paymentRoutingAssets !== [];

        return [
            'matrix_version' => self::MATRIX_VERSION,
            'instrument' => $network,
            'instrument_label' => (string) data_get($binding->metadata, 'network_label', $network),
            'binding_source' => (string) ($binding->binding_source ?? IdentityBinding::SOURCE_EXTERNAL),
            'rail_type' => $railType,
            'receive' => [
                'enabled' => $verified,
                'asset' => $this->defaultReceiveAsset($protocol),
                'status' => $verified
                    ? RecipientResolverService::STATUS_RECEIVE_ENABLED
                    : RecipientResolverService::STATUS_RECEIVE_PENDING,
            ],
            'send' => [
                'enabled' => $paymentRoutingEnabled && $this->identityPaymentsExecuteEnabled(),
                'assets' => $paymentRoutingEnabled && $this->identityPaymentsExecuteEnabled()
                    ? $paymentRoutingAssets
                    : [],
            ],
            'payment_routing' => [
                'enabled' => $paymentRoutingEnabled,
                'assets' => $paymentRoutingEnabled ? $paymentRoutingAssets : [],
                'policy' => $paymentRoutingEnabled
                    ? IdentityPaymentRoutingService::POLICY_SHARED_MANAGED_NETWORK
                    : null,
                'capability_policy_version' => $paymentRoutingEnabled ? $policyVersionLabel : null,
                'evaluated_by' => $paymentRoutingEnabled ? $policyVersionLabel : null,
            ],
        ];
    }

    /**
     * Payment routing capability is a subset of receive capability.
     *
     * @param  array<string, mixed>  $matrix
     */
    public function paymentRoutingEnabled(array $matrix): bool
    {
        return (bool) data_get($matrix, 'receive.enabled')
            && (bool) data_get($matrix, 'payment_routing.enabled')
            && data_get($matrix, 'payment_routing.assets', []) !== [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function formatSenderPaymentRoutingCapability(
        IdentityBinding $binding,
        string $asset,
        ?string $policyVersionKey = null,
    ): ?array {
        $policyVersionKey = $policyVersionKey ?? $this->capabilityPolicies->activeVersionKey();
        $matrix = $this->matrixForBinding($binding, $policyVersionKey);
        if (! $this->paymentRoutingEnabled($matrix)) {
            return null;
        }

        $normalizedAsset = strtoupper(trim($asset));
        $assets = collect((array) data_get($matrix, 'payment_routing.assets', []))
            ->map(fn (string $value) => strtoupper(trim($value)))
            ->values()
            ->all();

        if (! in_array($normalizedAsset, $assets, true)) {
            return null;
        }

        $policyVersionLabel = $this->capabilityPolicies->versionLabel($policyVersionKey);

        return [
            'binding_id' => $binding->id,
            'network' => $binding->binding_key,
            'asset' => $normalizedAsset,
            'assets' => $assets,
            'rail_type' => data_get($matrix, 'rail_type'),
            'capability' => 'payment_routing',
            'status' => RecipientResolverService::STATUS_ROUTING_ENABLED,
            'policy' => data_get($matrix, 'payment_routing.policy'),
            'capability_policy_version' => $policyVersionLabel,
            'evaluated_by' => $policyVersionLabel,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function formatPaymentRoutingCapability(
        IdentityBinding $binding,
        ?string $policyVersionKey = null,
    ): ?array {
        $policyVersionKey = $policyVersionKey ?? $this->capabilityPolicies->activeVersionKey();
        $matrix = $this->matrixForBinding($binding, $policyVersionKey);
        if (! $this->paymentRoutingEnabled($matrix)) {
            return null;
        }

        $policyVersionLabel = $this->capabilityPolicies->versionLabel($policyVersionKey);

        return [
            'binding_id' => $binding->id,
            'network' => $binding->binding_key,
            'assets' => data_get($matrix, 'payment_routing.assets', []),
            'rail_type' => data_get($matrix, 'rail_type'),
            'capability' => 'payment_routing',
            'status' => RecipientResolverService::STATUS_ROUTING_ENABLED,
            'policy' => data_get($matrix, 'payment_routing.policy'),
            'capability_policy_version' => $policyVersionLabel,
            'evaluated_by' => $policyVersionLabel,
        ];
    }

    private function defaultReceiveAsset(string $protocol): string
    {
        return match ($protocol) {
            'utxo' => 'BTC',
            'solana' => 'USDC',
            'ton' => 'USDC',
            default => 'USDC',
        };
    }

    private function identityPaymentsExecuteEnabled(): bool
    {
        return (bool) config('identity_payments.enabled', false)
            && (bool) config('identity_payments.execute_enabled', false);
    }
}
