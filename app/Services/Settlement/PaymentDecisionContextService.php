<?php

namespace App\Services\Settlement;

class PaymentDecisionContextService
{
    public function __construct(
        private readonly CapabilityPolicyRegistry $capabilityPolicies,
    ) {}

    /**
     * @return array{
     *     policy_keys: list<string>,
     *     evaluated_at: string,
     *     ruleset_hash: string
     * }
     */
    public function build(string $capabilityPolicyKey, ?string $evaluatedAt = null): array
    {
        return [
            'policy_keys' => [
                $this->capabilityPolicies->versionLabel($capabilityPolicyKey),
                IdentityPaymentRoutingService::POLICY_VERSION,
            ],
            'evaluated_at' => $evaluatedAt ?? now()->toJSON(),
            'ruleset_hash' => $this->rulesetHash($capabilityPolicyKey),
        ];
    }

    public function rulesetHash(string $capabilityPolicyKey): string
    {
        return 'sha256:'.hash('sha256', $this->canonicalRulesetPayload($capabilityPolicyKey));
    }

    public function canonicalRulesetPayload(string $capabilityPolicyKey): string
    {
        $payload = $this->sortRecursive([
            'capability_policy' => $this->capabilityPolicies->version($capabilityPolicyKey),
            'routing_policy' => [
                'name' => IdentityPaymentRoutingService::POLICY_SHARED_MANAGED_NETWORK,
                'version' => IdentityPaymentRoutingService::POLICY_VERSION,
            ],
        ]);

        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $array
     * @return array<string, mixed>
     */
    private function sortRecursive(array $array): array
    {
        ksort($array);

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->sortRecursive($value);
            }
        }

        return $array;
    }
}
