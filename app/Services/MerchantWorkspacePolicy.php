<?php

namespace App\Services;

use App\Support\MarketContext;
use Illuminate\Support\Arr;

class MerchantWorkspacePolicy
{
    public function __construct(private readonly MarketContextResolver $markets)
    {
    }

    /**
     * @return array<int, string>
     */
    public function modulesForMarket(MarketContext|string|null $market = null): array
    {
        $context = $this->resolveMarket($market);
        $modules = config("markets.markets.{$context->market}.merchant_modules");

        if (! is_array($modules) || $modules === []) {
            $modules = config('markets.merchant_modules_default', []);
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $module): string => strtolower(trim((string) $module)),
            Arr::wrap($modules),
        ))));
    }

    public function isModuleAllowed(string $module, MarketContext|string|null $market = null): bool
    {
        return in_array(strtolower(trim($module)), $this->modulesForMarket($market), true);
    }

    public function requiresInnForChannels(MarketContext|string|null $market = null): bool
    {
        $context = $this->resolveMarket($market);

        return in_array('yandex_market', $context->salesChannels, true);
    }

    private function resolveMarket(MarketContext|string|null $market): MarketContext
    {
        if ($market instanceof MarketContext) {
            return $market;
        }

        if (is_string($market) && $market !== '') {
            return $this->markets->resolveForMarketKey($market);
        }

        return market();
    }
}
