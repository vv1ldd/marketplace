<?php

namespace App\Services;

use App\Models\WildflowCatalog;
use Illuminate\Support\Str;

/**
 * Universal SKU generator for all sales channels.
 * Generates neutral, human-readable identifiers that:
 * - Do NOT reveal the source supplier (no WF/WFC prefix)
 * - Are compatible with all channel platforms (Yandex Market, Avito, WB, Ozon, WooCommerce, etc.)
 * - Are stable and deterministic for a given catalog item
 *
 * Format: {BRAND}-{NOMINAL}-{CURRENCY}-{REGION}-{TYPE}-C{ID}
 * Example: STEAM-100-USD-US-GC-C42
 */
class SkuGeneratorService
{
    /**
     * Generate a universal neutral SKU for any sales channel.
     * The resulting SKU is channel-agnostic and safe to expose to sellers.
     */
    public function forCatalogItem(WildflowCatalog $catalog): string
    {
        return $this->forParams(
            $catalog->brand_name ?? '',
            $catalog->retail_price ?? 0,
            $catalog->currency_code ?? 'USD',
            $catalog->region?->code ?? 'GLB',
            $catalog->reward_type ?? '',
            'C' . $catalog->id
        );
    }

    /**
     * Flexible generator that doesn't require a model instance.
     */
    public function forParams(
        string $brandName,
        float $price,
        string $currencyCode,
        string $regionCode = 'GLB',
        string $rewardType = '',
        string $tail = ''
    ): string {
        $brand = $this->slugifyBrand($brandName);
        $region = $this->slugifyRegion($regionCode);
        $currency = $this->slugifyCurrency($currencyCode);
        $nominal = $this->priceSegment($price);
        $type = $this->resolveTypeSuffix($rewardType);

        $parts = array_filter([$brand, $nominal, $currency, $region, $type, $tail]);

        return $this->sanitize(implode('-', $parts));
    }

    /**
     * Generate a channel-specific SKU prefix if the channel has constraints.
     * Falls back to the universal SKU for unknown/unconstrained channels.
     */
    public function forChannel(WildflowCatalog $catalog, string $channel): string
    {
        $base = $this->forCatalogItem($catalog);

        return match ($channel) {
            // Yandex Market: max 255 chars, uppercase, no cyrillic — already handled
            'yandex_market' => $base,

            // Wildberries: often uses seller article, max 100 chars
            'wildberries'   => substr($base, 0, 100),

            // Ozon: seller article, max 80 chars
            'ozon'          => substr($base, 0, 80),

            // Avito: no strict format, use as-is
            'avito'         => $base,

            // WooCommerce: SKU field, no strict limit
            'woocommerce'   => $base,

            // Offline / Telegram / VK / others — use as-is
            default         => $base,
        };
    }

    /**
     * Generate SKUs for all active channels in one call.
     *
     * @return array<string, string> channel => sku
     */
    public function forAllChannels(WildflowCatalog $catalog): array
    {
        $channels = array_keys(config('sales_channels.channels', []));
        $result = [];

        foreach ($channels as $channel) {
            $result[$channel] = $this->forChannel($catalog, $channel);
        }

        return $result;
    }

    // ─────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────

    private function slugifyBrand(string $raw): string
    {
        $slug = Str::slug(Str::ascii($raw), '-');

        return Str::limit($slug ?: 'gift-card', 40, '');
    }

    private function slugifyRegion(string $code): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));

        return substr($clean ?: 'GLB', 0, 8);
    }

    private function slugifyCurrency(string $code): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z]/', '', $code));

        return substr($clean ?: 'USD', 0, 3);
    }

    private function slugifyNominal(WildflowCatalog $catalog): string
    {
        if ($catalog->is_variable_price) {
            return $this->priceSegment($catalog->min_price)
                . '-'
                . $this->priceSegment($catalog->max_price);
        }

        return $this->priceSegment($catalog->retail_price ?? 0);
    }

    private function priceSegment(float $v): string
    {
        $s = rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');

        return str_replace('.', '-', $s);
    }

    private function resolveTypeSuffix(string $rewardType): string
    {
        return match (strtolower($rewardType)) {
            'gift-card', 'gift card'   => 'GC',
            'subscription'             => 'SUB',
            'game-topup', 'game topup' => 'TOP',
            default                    => 'V',
        };
    }

    private function sanitize(string $sku): string
    {
        $sku = preg_replace('/[\x00-\x08\x0A-\x1F\x7F]/', '', $sku) ?? '';
        $sku = trim(preg_replace('/\s+/', '-', $sku) ?? '');
        $sku = preg_replace('/-+/', '-', $sku) ?? '';
        $sku = trim($sku, '-');
        $sku = mb_strtoupper($sku, 'UTF-8');

        return mb_strlen($sku) > 255 ? mb_substr($sku, 0, 255) : ($sku ?: 'ITEM-' . uniqid());
    }
}
