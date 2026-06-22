<?php

namespace App\Services\Settlement;

use App\Models\IdentityBinding;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class IdentityPaymentRoutingService
{
    public const POLICY_SHARED_MANAGED_NETWORK = 'shared_managed_network';

    public const POLICY_VERSION = 'shared_managed_network:v1';

    public function __construct(
        private readonly SettlementInstrumentCapabilityService $instrumentCapabilities,
        private readonly CapabilityPolicyRegistry $capabilityPolicies,
        private readonly PaymentDecisionContextService $decisionContext,
    ) {}

    /**
     * @param  Collection<int, IdentityBinding>  $senderBindings
     * @param  array<string, mixed>  $recipientResolution
     * @return array{
     *     network: string,
     *     sender_binding_id: int,
     *     receiver_binding_id: int,
     *     policy: string,
     *     amount_wei: string,
     *     metadata: array<string, mixed>
     * }
     */
    public function decide(
        Collection $senderBindings,
        array $recipientResolution,
        string $asset,
        string $amount,
    ): array {
        $normalizedAsset = strtoupper(trim($asset));
        $amountWei = $this->amountToMinorUnits($amount, $normalizedAsset);
        $policyVersionKey = $this->capabilityPolicies->activeVersionKey();
        $policyVersionLabel = $this->capabilityPolicies->versionLabel($policyVersionKey);

        $senderRouting = $senderBindings
            ->mapWithKeys(function (IdentityBinding $binding) use ($normalizedAsset, $policyVersionKey): array {
                $capability = $this->instrumentCapabilities->formatSenderPaymentRoutingCapability(
                    $binding,
                    $normalizedAsset,
                    $policyVersionKey,
                );

                return $capability === null
                    ? []
                    : [(string) $binding->binding_key => [
                        'binding' => $binding,
                        'capability' => $capability,
                    ]];
            });

        $recipientRouting = collect($recipientResolution['payment_routing_capabilities'] ?? [])
            ->filter(fn (array $capability) => ($capability['status'] ?? null) === RecipientResolverService::STATUS_ROUTING_ENABLED)
            ->filter(fn (array $capability) => $this->routingCapabilitySupportsAsset($capability, $normalizedAsset))
            ->keyBy(fn (array $capability) => (string) ($capability['network'] ?? ''));

        $sharedNetworks = $senderRouting->keys()
            ->intersect($recipientRouting->keys())
            ->values()
            ->all();

        if ($sharedNetworks === []) {
            throw ValidationException::withMessages([
                'to_alias' => 'No shared payment routing capability exists between sender and recipient for this asset.',
            ]);
        }

        $sharedRails = collect($sharedNetworks)
            ->map(function (string $network) use ($senderRouting, $recipientRouting): array {
                /** @var array{binding: IdentityBinding, capability: array<string, mixed>} $senderEntry */
                $senderEntry = $senderRouting->get($network);
                $recipientCapability = $recipientRouting->get($network);

                return [
                    'network' => $network,
                    'sender_binding_id' => (int) $senderEntry['binding']->id,
                    'receiver_binding_id' => (int) ($recipientCapability['binding_id'] ?? 0),
                ];
            })
            ->sortBy(fn (array $rail) => (string) $rail['network'])
            ->values()
            ->all();

        [$network, $selectionReason] = $this->selectNetworkWithReason($sharedNetworks, $policyVersionKey);

        /** @var array{binding: IdentityBinding, capability: array<string, mixed>} $senderEntry */
        $senderEntry = $senderRouting->get($network);
        $recipientCapability = $recipientRouting->get($network);
        $receiverBindingId = (int) ($recipientCapability['binding_id'] ?? 0);

        if ($receiverBindingId <= 0) {
            throw ValidationException::withMessages([
                'to_alias' => 'Recipient payment routing candidate is incomplete for the selected network.',
            ]);
        }

        $selected = [
            'network' => $network,
            'sender_binding_id' => (int) $senderEntry['binding']->id,
            'receiver_binding_id' => $receiverBindingId,
        ];

        return [
            'network' => $network,
            'sender_binding_id' => (int) $senderEntry['binding']->id,
            'receiver_binding_id' => $receiverBindingId,
            'policy' => self::POLICY_SHARED_MANAGED_NETWORK,
            'amount_wei' => $amountWei,
            'metadata' => [
                'asset' => $normalizedAsset,
                'candidates' => $sharedRails,
                'selected' => $selected,
                'reason' => $selectionReason,
                'policy_version' => self::POLICY_VERSION,
                'capability_policy_version' => $policyVersionLabel,
                'capability_policy_key' => $policyVersionKey,
                'sender_payment_routing_capabilities' => $senderRouting
                    ->map(fn (array $entry) => $entry['capability'])
                    ->values()
                    ->all(),
                'recipient_payment_routing_capabilities' => $recipientRouting->values()->all(),
                'preference' => $this->capabilityPolicies->networkPreference($policyVersionKey),
                'decision_context' => $this->decisionContext->build($policyVersionKey),
            ],
        ];
    }

    public const REASON_HIGHEST_PRIORITY_SHARED_RAIL = 'highest_priority_shared_managed_rail';

    public const REASON_LEXICOGRAPHIC_SHARED_RAIL = 'lexicographic_shared_managed_rail';

    /**
     * @param  array<string, mixed>  $capability
     */
    private function routingCapabilitySupportsAsset(array $capability, string $normalizedAsset): bool
    {
        $assets = collect((array) ($capability['assets'] ?? []))
            ->map(fn (string $value) => strtoupper(trim($value)))
            ->values()
            ->all();

        if (isset($capability['asset']) && (string) $capability['asset'] !== '') {
            $assets[] = strtoupper(trim((string) $capability['asset']));
        }

        return in_array($normalizedAsset, array_unique($assets), true);
    }

    /**
     * @param  list<string>  $sharedNetworks
     * @return array{0: string, 1: string}
     */
    private function selectNetworkWithReason(array $sharedNetworks, string $policyVersionKey): array
    {
        $preference = $this->capabilityPolicies->networkPreference($policyVersionKey);

        foreach ($preference as $candidate) {
            if (in_array($candidate, $sharedNetworks, true)) {
                return [(string) $candidate, self::REASON_HIGHEST_PRIORITY_SHARED_RAIL];
            }
        }

        sort($sharedNetworks);

        return [(string) $sharedNetworks[0], self::REASON_LEXICOGRAPHIC_SHARED_RAIL];
    }

    private function amountToMinorUnits(string $amount, string $asset): string
    {
        $decimals = (int) config('identity_payments.assets.'.$asset.'.decimals', 6);
        $normalized = trim($amount);

        if (! preg_match('/^\d+(\.\d+)?$/', $normalized)) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be a positive decimal string.',
            ]);
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, $decimals), $decimals, '0', STR_PAD_RIGHT);

        $minor = ltrim($whole.$fraction, '0');

        if ($minor === '') {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be greater than zero.',
            ]);
        }

        return $minor;
    }
}
