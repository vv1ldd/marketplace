<?php

namespace App\Services;

use App\Models\Shop;
use App\Support\MarketContext;
use Illuminate\Support\Arr;

class MarketChannelPolicy
{
    public function __construct(private readonly MarketContextResolver $markets)
    {
    }

    /**
     * @return array<int, string>
     */
    public function allowedChannelsForMarket(MarketContext|string $market): array
    {
        if ($market instanceof MarketContext) {
            return $market->salesChannels;
        }

        $channels = config("markets.markets.{$market}.sales_channels", []);

        return $this->normalizeChannels($channels);
    }

    /**
     * @return array<int, string>
     */
    public function allowedChannelsForShop(Shop $shop): array
    {
        return $this->allowedChannelsForMarket($this->markets->resolveForShop($shop));
    }

    public function isAllowedForMarket(string $channel, MarketContext|string $market): bool
    {
        return in_array($this->normalizeChannel($channel), $this->allowedChannelsForMarket($market), true);
    }

    public function isAllowedForShop(string $channel, Shop $shop): bool
    {
        return in_array($this->normalizeChannel($channel), $this->allowedChannelsForShop($shop), true);
    }

    private function normalizeChannel(string $channel): string
    {
        return strtolower(trim($channel));
    }

    /**
     * @return array<int, string>
     */
    private function normalizeChannels(mixed $channels): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (mixed $channel): string => $this->normalizeChannel((string) $channel),
            Arr::wrap($channels),
        ))));
    }
}
