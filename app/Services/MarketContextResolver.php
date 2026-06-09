<?php

namespace App\Services;

use App\Models\Shop;
use App\Support\MarketContext;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class MarketContextResolver
{
    public function resolve(Request $request): MarketContext
    {
        $host = $this->normalizeHost($request->getHost());
        $markets = (array) config('markets.markets', []);
        $defaultKey = (string) config('markets.default', 'global');
        $hostMarketKey = $this->marketKeyForHost($host, $markets, $defaultKey);
        $matchedKey = $hostMarketKey ?? $defaultKey;

        return $this->contextForMarket($matchedKey, $host, $hostMarketKey !== null, $markets, $defaultKey);
    }

    public function resolveForShop(Shop $shop): MarketContext
    {
        $markets = (array) config('markets.markets', []);
        $defaultKey = (string) config('markets.default', 'global');
        $host = $this->normalizeHost((string) $shop->domain);

        if ($host !== '') {
            $hostMarketKey = $this->marketKeyForHost($host, $markets, $defaultKey);
            if ($hostMarketKey !== null) {
                return $this->contextForMarket($hostMarketKey, $host, true, $markets, $defaultKey);
            }

            return $this->contextForMarket($defaultKey, $host, false, $markets, $defaultKey);
        }

        $region = $this->shopRegion($shop);
        $regionMarketKey = $region ? $this->marketKeyForDemandRegion($region, $markets) : null;

        return $this->contextForMarket($regionMarketKey ?? $defaultKey, $host, false, $markets, $defaultKey);
    }

    /**
     * @param array<string, array<string, mixed>> $markets
     */
    private function marketKeyForHost(string $host, array $markets, string $defaultKey): ?string
    {
        // 1. Exact match pass
        foreach ($markets as $key => $config) {
            $domains = array_map(
                static fn (mixed $domain): string => Str::lower(trim((string) $domain)),
                Arr::wrap($config['domains'] ?? []),
            );

            if (in_array($host, $domains, true)) {
                return (string) $key;
            }
        }

        // 2. Suffix (subdomain) match pass
        foreach ($markets as $key => $config) {
            if ((string) $key === $defaultKey) {
                continue;
            }

            $domains = array_map(
                static fn (mixed $domain): string => Str::lower(trim((string) $domain)),
                Arr::wrap($config['domains'] ?? []),
            );

            foreach ($domains as $domain) {
                if (str_ends_with($host, '.' . $domain)) {
                    return (string) $key;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, array<string, mixed>> $markets
     */
    private function marketKeyForDemandRegion(string $region, array $markets): ?string
    {
        $region = Str::upper(trim($region));
        if ($region === '') {
            return null;
        }

        foreach ($markets as $key => $config) {
            $demandRegion = filled($config['demand_region'] ?? null)
                ? Str::upper((string) $config['demand_region'])
                : null;

            if ($demandRegion === $region) {
                return (string) $key;
            }
        }

        return null;
    }

    /**
     * @param array<string, array<string, mixed>> $markets
     */
    private function contextForMarket(string $marketKey, string $host, bool $matchedDomain, array $markets, string $defaultKey): MarketContext
    {
        $config = (array) ($markets[$marketKey] ?? $markets[$defaultKey] ?? []);

        return new MarketContext(
            market: $marketKey,
            name: (string) ($config['name'] ?? Str::headline($marketKey)),
            host: $host,
            locale: (string) ($config['locale'] ?? config('app.locale', 'en')),
            currency: (string) ($config['currency'] ?? 'USD'),
            catalogScope: (string) ($config['catalog_scope'] ?? $marketKey),
            pricingScope: (string) ($config['pricing_scope'] ?? $marketKey),
            demandRegion: filled($config['demand_region'] ?? null) ? Str::upper((string) $config['demand_region']) : null,
            preferredProductRegions: $this->normalizeRegionList($config['preferred_product_regions'] ?? []),
            salesChannels: $this->normalizeStringList($config['sales_channels'] ?? []),
            matchedDomain: $matchedDomain,
        );
    }

    /**
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => Str::lower(trim((string) $value)),
            Arr::wrap($values),
        ))));
    }

    /**
     * @return array<int, string>
     */
    private function normalizeRegionList(mixed $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => Str::upper(trim((string) $value)),
            Arr::wrap($values),
        ))));
    }

    private function normalizeHost(?string $host): string
    {
        $host = Str::lower(trim((string) $host));
        if ($host === '') {
            return '';
        }

        $parsedHost = parse_url(str_contains($host, '://') ? $host : 'https://'.$host, PHP_URL_HOST);

        return Str::lower(trim((string) ($parsedHost ?: $host)));
    }

    private function shopRegion(Shop $shop): ?string
    {
        return filled($shop->shop_region)
            ? Str::upper((string) $shop->shop_region)
            : (filled($shop->legalEntity?->country_code) ? Str::upper((string) $shop->legalEntity->country_code) : null);
    }
}
