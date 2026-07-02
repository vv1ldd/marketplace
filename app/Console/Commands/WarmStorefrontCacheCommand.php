<?php

namespace App\Console\Commands;

use App\Models\ExternalSearchQuerySignal;
use App\Services\CanonicalStorefrontHomepageService;
use App\Services\MarketContextResolver;
use App\Services\PricingContextResolver;
use App\Support\MarketContext;
use App\Support\PricingContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;

class WarmStorefrontCacheCommand extends Command
{
    protected $signature = 'catalog:warm-cache
        {--market=* : Limit warming to specific market keys (default: the default market only)}
        {--all-markets : Warm every configured market instead of just the default one}
        {--searches=6 : How many top search terms to pre-warm per market}';

    protected $description = 'Pre-warm hot storefront caches (homepage, intent corridors, top searches) so runtime never pays the cold-start cost.';

    public function handle(CanonicalStorefrontHomepageService $homepage, MarketContextResolver $resolver): int
    {
        $markets = $this->targetMarkets();
        $searchLimit = max(0, (int) $this->option('searches'));

        $totals = ['homepage' => 0, 'categories' => 0, 'searches' => 0];

        foreach ($markets as $marketKey) {
            $context = $resolver->resolveForMarketKey($marketKey);
            $searchTerms = $this->warmSearchTerms($context, $searchLimit);

            $warmed = $this->withMarketContext($context, fn (): array => $homepage->warmStorefrontCaches($searchTerms));

            foreach ($totals as $key => $_) {
                $totals[$key] += $warmed[$key] ?? 0;
            }

            $this->line(sprintf(
                '[%s/%s] homepage=%d corridors=%d searches=%d',
                $context->market,
                $context->locale,
                $warmed['homepage'] ?? 0,
                $warmed['categories'] ?? 0,
                $warmed['searches'] ?? 0,
            ));
        }

        $this->info(sprintf(
            'Warmed storefront caches: homepage=%d corridors=%d searches=%d across %d market(s).',
            $totals['homepage'],
            $totals['categories'],
            $totals['searches'],
            count($markets),
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function targetMarkets(): array
    {
        $requested = array_filter((array) $this->option('market'));

        if ($requested !== []) {
            return array_values(array_unique($requested));
        }

        $configured = array_keys((array) config('markets.markets', []));
        $default = (string) config('markets.default', 'global');

        // By default warm only the primary market to keep the 3-min loop cheap.
        // Secondary markets warm lazily on first request (Cache::remember), or
        // eagerly via --all-markets on a slower cadence.
        if (! $this->option('all-markets')) {
            return $configured !== [] && in_array($default, $configured, true)
                ? [$default]
                : [$default];
        }

        if ($configured !== []) {
            return $configured;
        }

        return [$default];
    }

    /**
     * @return array<int, string>
     */
    private function warmSearchTerms(MarketContext $context, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $terms = [];

        if (Schema::hasTable('external_search_query_signals')) {
            $terms = ExternalSearchQuerySignal::query()
                ->when($context->locale !== '', fn ($query) => $query->where(function ($q) use ($context): void {
                    $q->whereNull('locale')->orWhere('locale', $context->locale);
                }))
                ->whereNotNull('normalized_query')
                ->where('normalized_query', '<>', '')
                ->orderByDesc('impressions')
                ->orderByDesc('volume')
                ->limit($limit * 2)
                ->pluck('normalized_query')
                ->all();
        }

        $terms = array_values(array_unique(array_filter(array_map('trim', $terms))));

        if (count($terms) < $limit) {
            $terms = array_values(array_unique(array_merge($terms, $this->fallbackSearchTerms())));
        }

        return array_slice($terms, 0, $limit);
    }

    /**
     * @return array<int, string>
     */
    private function fallbackSearchTerms(): array
    {
        $corridors = array_keys((array) config('catalog_taxonomy.intent_corridors', []));

        return array_values(array_unique(array_merge($corridors, [
            'playstation',
            'steam',
            'xbox',
            'amazon',
            'nintendo',
            'google play',
            'apple',
            'roblox',
        ])));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withMarketContext(MarketContext $context, callable $callback): mixed
    {
        $hadMarket = App::bound(MarketContext::class);
        $previousMarket = $hadMarket ? App::make(MarketContext::class) : null;
        $hadPricing = App::bound(PricingContext::class);
        $previousPricing = $hadPricing ? App::make(PricingContext::class) : null;
        $previousLocale = App::getLocale();

        App::instance(MarketContext::class, $context);
        App::instance(
            PricingContext::class,
            App::make(PricingContextResolver::class)->resolve($context),
        );

        if ($context->locale !== '') {
            App::setLocale($context->locale);
        }

        try {
            return $callback();
        } finally {
            App::setLocale($previousLocale);

            if ($hadMarket && $previousMarket !== null) {
                App::instance(MarketContext::class, $previousMarket);
            } else {
                App::forgetInstance(MarketContext::class);
            }

            if ($hadPricing && $previousPricing !== null) {
                App::instance(PricingContext::class, $previousPricing);
            } else {
                App::forgetInstance(PricingContext::class);
            }
        }
    }
}
