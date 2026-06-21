<?php

namespace App\Support;

final class SettlementAdapterConfig
{
    public const MODE_READ_ONLY = 'read_only';

    public const MODE_FULL = 'full';

    /**
     * @return array<string, mixed>|null
     */
    public static function for(string $adapterKey): ?array
    {
        $config = config('settlement_adapters.'.$adapterKey);

        return is_array($config) ? $config : null;
    }

    public static function isConfigured(string $adapterKey): bool
    {
        return self::for($adapterKey) !== null;
    }

    public static function isEnabled(string $adapterKey): bool
    {
        return (bool) (self::for($adapterKey)['enabled'] ?? false);
    }

    public static function mode(string $adapterKey): string
    {
        return (string) (self::for($adapterKey)['mode'] ?? self::MODE_READ_ONLY);
    }

    public static function allowsWrite(string $adapterKey): bool
    {
        return self::isEnabled($adapterKey) && self::mode($adapterKey) === self::MODE_FULL;
    }

    public static function adapterClass(string $adapterKey): ?string
    {
        $class = self::for($adapterKey)['adapter'] ?? null;

        return is_string($class) && $class !== '' ? $class : null;
    }

    public static function staleObservationHours(string $adapterKey): int
    {
        return max(1, (int) (self::for($adapterKey)['stale_observation_hours'] ?? 24));
    }
}
