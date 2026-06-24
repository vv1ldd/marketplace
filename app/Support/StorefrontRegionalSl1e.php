<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Keeps OAuth / WebAuthn identity on the storefront region that started the flow.
 * meanly.ru never borrows meanly.one client ids or callback hosts, and vice versa.
 */
final class StorefrontRegionalSl1e
{
    public function __construct(
        public readonly string $marketKey,
        public readonly string $storefrontHost,
        public readonly string $clientId,
        public readonly string $clientName,
    ) {}

    public static function forRequest(Request $request): self
    {
        return self::forHost(StorefrontRequestHost::resolve($request));
    }

    public static function forHost(?string $host): self
    {
        $normalizedHost = StorefrontRequestHost::normalizeHost($host);
        $marketKey = self::marketKeyForHost($normalizedHost);
        $storefrontHost = self::canonicalStorefrontHost($marketKey, $normalizedHost);

        return new self(
            marketKey: $marketKey,
            storefrontHost: $storefrontHost,
            clientId: $storefrontHost,
            clientName: self::clientNameForMarket($marketKey),
        );
    }

    public function matchesHost(?string $host): bool
    {
        $otherMarket = self::marketKeyForHost(StorefrontRequestHost::normalizeHost($host));

        return $otherMarket === $this->marketKey;
    }

    public function assertMatchesHost(?string $host): void
    {
        $normalized = StorefrontRequestHost::normalizeHost($host);

        if ($normalized === null) {
            return;
        }

        if (! $this->matchesHost($normalized)) {
            throw new HttpException(422, 'Identity flow must stay on the same storefront region.');
        }
    }

    public function assertMatchesRedirectUri(string $redirectUri): void
    {
        $redirectHost = StorefrontRequestHost::normalizeHost(
            (string) parse_url($redirectUri, PHP_URL_HOST),
        );

        if ($redirectHost === null) {
            throw new HttpException(422, 'redirectUri is invalid.');
        }

        $this->assertMatchesHost($redirectHost);
    }

    private static function marketKeyForHost(?string $host): string
    {
        $host = StorefrontRequestHost::normalizeHost($host);
        if ($host === null) {
            return (string) config('markets.default', 'global');
        }

        $markets = (array) config('markets.markets', []);
        $defaultKey = (string) config('markets.default', 'global');

        foreach ($markets as $key => $config) {
            $domains = self::normalizeDomainList($config['domains'] ?? []);
            if (in_array($host, $domains, true)) {
                return (string) $key;
            }
        }

        foreach ($markets as $key => $config) {
            if ((string) $key === $defaultKey) {
                continue;
            }

            foreach (self::normalizeDomainList($config['domains'] ?? []) as $domain) {
                if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                    return (string) $key;
                }
            }
        }

        return $defaultKey;
    }

    private static function canonicalStorefrontHost(string $marketKey, ?string $requestHost): string
    {
        $domains = self::normalizeDomainList(
            (array) data_get(config('markets.markets', []), "{$marketKey}.domains", []),
        );

        $preferred = (array) config('simple_l1.market_canonical_hosts', []);
        $configured = StorefrontRequestHost::normalizeHost((string) ($preferred[$marketKey] ?? ''));
        if ($configured !== null && in_array($configured, $domains, true)) {
            return $configured;
        }

        if ($requestHost !== null) {
            $withoutWww = str_starts_with($requestHost, 'www.')
                ? substr($requestHost, 4)
                : $requestHost;

            if (in_array($withoutWww, $domains, true)) {
                return $withoutWww;
            }

            if (in_array($requestHost, $domains, true)) {
                return $requestHost;
            }
        }

        foreach ($domains as $domain) {
            if (! str_starts_with($domain, 'www.')) {
                return $domain;
            }
        }

        if ($requestHost !== null) {
            return str_starts_with($requestHost, 'www.') ? substr($requestHost, 4) : $requestHost;
        }

        return (string) config('simple_l1.client_id', 'meanly.one');
    }

    private static function clientNameForMarket(string $marketKey): string
    {
        $names = (array) config('simple_l1.market_client_names', []);

        return (string) ($names[$marketKey] ?? config('simple_l1.client_name', 'Meanly One'));
    }

    /**
     * @return array<int, string>
     */
    private static function normalizeDomainList(mixed $domains): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $domain): string => Str::lower(trim((string) $domain)),
            Arr::wrap($domains),
        ))));
    }
}
