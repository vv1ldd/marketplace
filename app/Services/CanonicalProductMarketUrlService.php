<?php

namespace App\Services;

use App\Support\MarketContext;
use Illuminate\Support\Str;

class CanonicalProductMarketUrlService
{
    /**
     * @return array<string, mixed>
     */
    public function productUrlMatrix(string $identitySlug, ?string $currentMarket = null): array
    {
        $currentMarket ??= $this->currentMarketKey();
        $markets = $this->markets();
        $currentMarket = array_key_exists($currentMarket, $markets)
            ? $currentMarket
            : (string) config('markets.default', 'global');
        $currentHost = $this->currentHost();

        $variants = collect($markets)
            ->flatMap(fn (array $config, string $market): array => $this->domainVariants($market, $config, $identitySlug, $market === $currentMarket ? $currentHost : null))
            ->values();

        $current = $variants->firstWhere('market', $currentMarket)
            ?? $variants->firstWhere('market', (string) config('markets.default', 'global'))
            ?? $variants->first();

        $xDefault = $variants->firstWhere('market', 'global') ?? $current;

        return [
            'current' => $current,
            'x_default' => $xDefault,
            'variants' => $variants->all(),
            'primary_by_market' => collect($markets)
                ->map(fn (array $config, string $market): ?array => $this->domainVariants($market, $config, $identitySlug)[0] ?? null)
                ->filter()
                ->values()
                ->all(),
        ];
    }

    public function productUrl(string $identitySlug, ?string $market = null): string
    {
        return (string) data_get($this->productUrlMatrix($identitySlug, $market), 'current.url', '');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function productPageUrl(string $identitySlug, array $query = [], ?string $market = null): string
    {
        $variant = data_get($this->productUrlMatrix($identitySlug, $market), 'current');
        if (! is_array($variant)) {
            return '';
        }

        $path = route('meanly.canonical-products.show', [
            'identitySlug' => $identitySlug,
        ] + array_filter($query, fn (mixed $value): bool => $value !== null && $value !== ''), false);

        return $this->absoluteUrl((string) $variant['domain'], $path);
    }

    public function productJsonUrl(string $identitySlug, ?string $market = null): string
    {
        return (string) data_get($this->productUrlMatrix($identitySlug, $market), 'current.machine_readable_url', '');
    }

    public function intentJsonUrl(string $identitySlug, string $intent, ?string $market = null): string
    {
        $variant = data_get($this->productUrlMatrix($identitySlug, $market), 'current');
        if (! is_array($variant)) {
            return '';
        }

        return $this->absoluteUrl(
            (string) $variant['domain'],
            route('llms.catalog.canonical-products.intents.show', [
                'identitySlug' => $identitySlug,
                'intent' => $intent,
            ], false),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function markets(): array
    {
        return (array) config('markets.markets', []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function domainVariants(string $market, array $config, string $identitySlug, ?string $preferredHost = null): array
    {
        $locale = (string) ($config['locale'] ?? config('app.locale', 'en'));
        $domains = $this->orderedDomains((array) ($config['domains'] ?? []), $preferredHost);
        $hreflang = $this->hreflang($locale, $market, $config);

        return collect($domains)
            ->map(fn (string $domain, int $index): array => [
                'market' => $market,
                'market_name' => (string) ($config['name'] ?? Str::headline($market)),
                'domain' => $domain,
                'locale' => $locale,
                'currency' => (string) ($config['currency'] ?? 'USD'),
                'catalog_scope' => (string) ($config['catalog_scope'] ?? $market),
                'pricing_scope' => (string) ($config['pricing_scope'] ?? $market),
                'demand_region' => $config['demand_region'] ?? null,
                'hreflang' => $hreflang,
                'is_primary' => $index === 0,
                'url' => $this->absoluteUrl($domain, route('meanly.canonical-products.show', $identitySlug, false)),
                'machine_readable_url' => $this->absoluteUrl($domain, route('llms.catalog.canonical-products.show', $identitySlug, false)),
            ])
            ->all();
    }

    /**
     * @param  array<int, string>  $domains
     * @return array<int, string>
     */
    private function orderedDomains(array $domains, ?string $preferredHost = null): array
    {
        $domains = collect($domains)
            ->map(fn (mixed $domain): string => Str::lower(trim((string) $domain)))
            ->filter()
            ->unique()
            ->values();

        $preferredHost = $preferredHost !== null ? Str::lower(trim($preferredHost)) : null;
        $preferred = $preferredHost !== null && $domains->contains($preferredHost)
            ? $preferredHost
            : null;
        $preferred ??= $domains->first(fn (string $domain): bool => str_starts_with($domain, 'meanly.') && ! str_ends_with($domain, '.test'));
        $preferred ??= $domains->first(fn (string $domain): bool => ! str_starts_with($domain, 'www.') && ! str_ends_with($domain, '.test'));
        if ($preferred === null) {
            $preferred = $domains->first(fn (string $domain): bool => ! str_starts_with($domain, 'www.')) ?? $domains->first();
        }

        return $domains
            ->sortBy(fn (string $domain): int => $domain === $preferred ? 0 : (str_starts_with($domain, 'www.') ? 2 : 1))
            ->values()
            ->all();
    }

    private function hreflang(string $locale, string $market, array $config): string
    {
        $region = strtoupper((string) ($config['demand_region'] ?? ''));

        return match ($market) {
            'global' => $this->baseLocale($locale),
            'latam_ar' => 'es-AR',
            'ru' => 'ru-RU',
            'ge' => 'ka-GE',
            default => $region !== '' ? $this->baseLocale($locale).'-'.$region : $this->baseLocale($locale),
        };
    }

    private function baseLocale(string $locale): string
    {
        return strtolower(explode('-', str_replace('_', '-', $locale))[0] ?: 'en');
    }

    private function currentMarketKey(): string
    {
        $context = app()->bound(MarketContext::class) ? app(MarketContext::class) : null;

        return $context instanceof MarketContext
            ? $context->market
            : (string) config('markets.default', 'global');
    }

    private function currentHost(): ?string
    {
        $context = app()->bound(MarketContext::class) ? app(MarketContext::class) : null;

        return $context instanceof MarketContext ? Str::lower($context->host) : null;
    }

    private function absoluteUrl(string $domain, string $path): string
    {
        $path = '/'.ltrim($path, '/');

        return 'https://'.trim($domain, '/').$path;
    }
}
