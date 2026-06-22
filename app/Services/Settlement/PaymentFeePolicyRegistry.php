<?php

namespace App\Services\Settlement;

use InvalidArgumentException;

class PaymentFeePolicyRegistry
{
    public function version(string $versionKey): array
    {
        $versions = (array) config('payment_fees.versions', []);
        if (! isset($versions[$versionKey])) {
            throw new InvalidArgumentException("Unknown payment fee policy version [{$versionKey}].");
        }

        return $versions[$versionKey];
    }

    public function activeVersionKey(): string
    {
        return (string) config('payment_fees.default', 'v1');
    }

    public function versionLabel(string $versionKey): string
    {
        return (string) data_get($this->version($versionKey), 'version');
    }

    /**
     * @return array{type: string, bps: int}|null
     */
    public function feeRuleFor(string $versionKey, string $railCategory, string $asset): ?array
    {
        $normalizedAsset = strtoupper(trim($asset));
        $rule = data_get($this->version($versionKey), "{$railCategory}.{$normalizedAsset}");

        if (! is_array($rule)) {
            return null;
        }

        $type = (string) ($rule['type'] ?? '');
        $bps = (int) ($rule['bps'] ?? 0);

        if ($type === '' || $bps <= 0) {
            return null;
        }

        return [
            'type' => $type,
            'bps' => $bps,
        ];
    }

    public function rulesetHash(string $versionKey): string
    {
        return 'sha256:'.hash('sha256', $this->canonicalRulesetPayload($versionKey));
    }

    public function canonicalRulesetPayload(string $versionKey): string
    {
        $payload = $this->sortRecursive($this->version($versionKey));

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
