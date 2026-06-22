<?php

namespace App\Services\Settlement;

use InvalidArgumentException;

class PaymentLimitPolicyRegistry
{
    public function version(string $versionKey): array
    {
        $versions = (array) config('payment_limits.versions', []);
        if (! isset($versions[$versionKey])) {
            throw new InvalidArgumentException("Unknown payment limit policy version [{$versionKey}].");
        }

        return $versions[$versionKey];
    }

    public function activeVersionKey(): string
    {
        return (string) config('payment_limits.default', 'v1');
    }

    public function versionLabel(string $versionKey): string
    {
        return (string) data_get($this->version($versionKey), 'version');
    }

    public function dailyConsumptionMode(string $versionKey): string
    {
        return (string) data_get($this->version($versionKey), 'daily_consumption_mode', 'net_outbound');
    }

    /**
     * @return array{per_transaction: string, daily: string}|null
     */
    public function limitsFor(string $versionKey, string $railCategory, string $asset): ?array
    {
        $normalizedAsset = strtoupper(trim($asset));
        $limits = data_get($this->version($versionKey), "{$railCategory}.{$normalizedAsset}");

        if (! is_array($limits)) {
            return null;
        }

        $perTransaction = trim((string) ($limits['per_transaction'] ?? ''));
        $daily = trim((string) ($limits['daily'] ?? ''));

        if ($perTransaction === '' || $daily === '') {
            return null;
        }

        return [
            'per_transaction' => $perTransaction,
            'daily' => $daily,
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
