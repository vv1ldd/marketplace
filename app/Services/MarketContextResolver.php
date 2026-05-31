<?php

namespace App\Services;

use App\Support\MarketContext;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class MarketContextResolver
{
    public function resolve(Request $request): MarketContext
    {
        $host = Str::lower($request->getHost());
        $markets = (array) config('markets.markets', []);
        $defaultKey = (string) config('markets.default', 'global');
        $matchedKey = $this->marketKeyForHost($host, $markets) ?? $defaultKey;
        $matchedDomain = $this->marketKeyForHost($host, $markets) !== null;
        $config = (array) ($markets[$matchedKey] ?? $markets[$defaultKey] ?? []);

        return new MarketContext(
            market: $matchedKey,
            name: (string) ($config['name'] ?? Str::headline($matchedKey)),
            host: $host,
            locale: (string) ($config['locale'] ?? config('app.locale', 'en')),
            currency: (string) ($config['currency'] ?? 'USD'),
            demandRegion: filled($config['demand_region'] ?? null) ? Str::upper((string) $config['demand_region']) : null,
            preferredProductRegions: array_values(array_map(
                static fn (string $region): string => Str::upper(trim($region)),
                Arr::wrap($config['preferred_product_regions'] ?? []),
            )),
            matchedDomain: $matchedDomain,
        );
    }

    /**
     * @param array<string, array<string, mixed>> $markets
     */
    private function marketKeyForHost(string $host, array $markets): ?string
    {
        foreach ($markets as $key => $config) {
            $domains = array_map(
                static fn (mixed $domain): string => Str::lower(trim((string) $domain)),
                Arr::wrap($config['domains'] ?? []),
            );

            if (in_array($host, $domains, true)) {
                return (string) $key;
            }
        }

        return null;
    }
}
