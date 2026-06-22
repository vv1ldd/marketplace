<?php

namespace App\Services\Settlement;

use App\Models\IdentityBinding;
use InvalidArgumentException;

class CapabilityPolicyRegistry
{
    /**
     * @return array<string, mixed>
     */
    public function version(string $versionKey): array
    {
        $versions = (array) config('capability_policies.versions', []);
        if (! isset($versions[$versionKey])) {
            throw new InvalidArgumentException("Unknown capability policy version [{$versionKey}].");
        }

        return $versions[$versionKey];
    }

    public function activeVersionKey(): string
    {
        return (string) config('capability_policies.default', 'v1');
    }

    public function versionLabel(string $versionKey): string
    {
        return (string) data_get($this->version($versionKey), 'version');
    }

    public function activeVersionLabel(): string
    {
        return $this->versionLabel($this->activeVersionKey());
    }

    /**
     * @return list<string>
     */
    public function networkPreference(string $versionKey): array
    {
        $preference = data_get($this->version($versionKey), 'network_preference');

        return is_array($preference) && $preference !== []
            ? array_values($preference)
            : (array) config('identity_payments.network_preference', []);
    }

    public function bindingRailType(IdentityBinding $binding): ?string
    {
        if (! $binding->isVerified()) {
            return null;
        }

        $network = (string) $binding->binding_key;
        $protocol = (string) data_get($binding->metadata, 'protocol', '');
        $source = (string) ($binding->binding_source ?? IdentityBinding::SOURCE_EXTERNAL);

        if ($source === IdentityBinding::SOURCE_MANAGED && $protocol === 'evm') {
            return match ($network) {
                'polygon' => 'polygon_managed',
                'base' => 'base_managed',
                'ethereum' => 'ethereum_managed',
                default => null,
            };
        }

        if ($source === IdentityBinding::SOURCE_EXTERNAL && $protocol === 'solana' && $network === 'solana') {
            return 'solana_verified';
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function paymentRoutingAssetsForBinding(IdentityBinding $binding, string $versionKey): array
    {
        if (! (bool) config('identity_payments.enabled', false)) {
            return [];
        }

        $railType = $this->bindingRailType($binding);
        if ($railType === null) {
            return [];
        }

        $assets = [];
        foreach ((array) data_get($this->version($versionKey), 'assets', []) as $asset => $rules) {
            $allowed = (array) data_get($rules, 'payment_routing', []);
            if (in_array($railType, $allowed, true)) {
                $assets[] = strtoupper((string) $asset);
            }
        }

        return $assets;
    }

    public function allowsPaymentRouting(IdentityBinding $binding, string $asset, string $versionKey): bool
    {
        $normalizedAsset = strtoupper(trim($asset));

        return in_array(
            $normalizedAsset,
            $this->paymentRoutingAssetsForBinding($binding, $versionKey),
            true,
        );
    }
}
