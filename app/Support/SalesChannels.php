<?php

namespace App\Support;

class SalesChannels
{
    /**
     * @return array<string, array{label:string, icon:string, enabled:bool, implemented:bool}>
     */
    public static function all(): array
    {
        $channels = config('sales_channels.channels', []);

        return is_array($channels) ? $channels : [];
    }

    /**
     * @return array<string, string>
     */
    public static function optionsForUi(?\App\Models\Shop $shop = null): array
    {
        $options = [];

        foreach (self::all() as $key => $meta) {
            if (! ($meta['enabled'] ?? false)) {
                continue;
            }

            if ($shop && ! self::isChannelConfigured($key, $shop)) {
                continue;
            }

            $options[$key] = trim(($meta['icon'] ?? '').' '.($meta['label'] ?? $key));
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function descriptionsForUi(?\App\Models\Shop $shop = null): array
    {
        $descriptions = [];

        foreach (self::all() as $key => $meta) {
            if (! ($meta['enabled'] ?? false)) {
                continue;
            }

            if ($shop && ! self::isChannelConfigured($key, $shop)) {
                continue;
            }

            $descriptions[$key] = ($meta['implemented'] ?? false)
                ? 'Интеграция активна'
                : 'Скоро (интеграция в разработке)';
        }

        return $descriptions;
    }

    public static function isChannelConfigured(string $key, \App\Models\Shop $shop): bool
    {
        return match ($key) {
            'meanly_storefront' => true,
            'yandex_market' => $shop->isYandexMarketActive(),
            'offline_store' => true,
            'woocommerce' => filled($shop->woo_api_url) && filled($shop->woo_consumer_key),
            // Other channels can be added here as they become configurable
            default => false,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function implementedKeys(): array
    {
        $keys = [];

        foreach (self::all() as $key => $meta) {
            if (($meta['enabled'] ?? false) && ($meta['implemented'] ?? false)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param  array<int, string>|null  $selection
     * @return array<int, string>
     */
    public static function normalizeSelection(?array $selection): array
    {
        $allKeys = array_keys(config('sales_channels.channels', []));
        $selection = array_values(array_unique(array_filter($selection ?? [])));

        return array_values(array_intersect($selection, $allKeys));
    }
}
