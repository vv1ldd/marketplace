<?php

namespace App\Services;

use App\Models\ProviderProduct;
use App\Models\Shop;
use App\Models\WildflowCatalog;
use Illuminate\Database\Eloquent\Model;

class StandardizationService
{
    /**
     * Standardize a catalog item into the Meanly Golden Schema.
     */
    public function standardizeCatalogItem(WildflowCatalog $item): array
    {
        $buyingPrice = $item->purchase_price;
        $retailPrice = $item->retail_price;
        $minBuyingPrice = $item->min_purchase_price;
        $maxBuyingPrice = $item->max_purchase_price;
        $minRetailPrice = $item->min_price;
        $maxRetailPrice = $item->max_price;

        // Apply 1% margin protection (Safety Buffer)
        $safeRetailPrice = max($retailPrice, $buyingPrice * 1.01);
        $safeMinRetailPrice = max($minRetailPrice, $minBuyingPrice * 1.01);
        $safeMaxRetailPrice = max($maxRetailPrice, $maxBuyingPrice * 1.01);

        $isVariable = $item->is_variable_price;

        $pricing = [
            'currency' => $item->currency_code,
            'buying_price' => round($buyingPrice, 2),
            'retail_price' => round($safeRetailPrice, 2),
            'is_variable' => $isVariable,
        ];

        if ($isVariable) {
            $pricing['min_price'] = round($safeMinRetailPrice, 2);
            $pricing['max_price'] = round($safeMaxRetailPrice, 2);
            $pricing['min_buying_price'] = round($minBuyingPrice, 2);
            $pricing['max_buying_price'] = round($maxBuyingPrice, 2);
        }

        $pricing['is_surcharge_applied'] = $safeRetailPrice > $retailPrice + 0.001;

        $rawData = $item->data ?? [];
        $orderType = $this->detectOrderType($item, $rawData);
        $playerIdFields = $this->getPlayerIdFields($orderType, $rawData);

        return [
            'id' => $item->id,
            'sku' => $item->sku,
            'provider' => [
                'id'   => $item->provider_id,
                'type' => $item->provider?->type ?? 'wildflow',
                'name' => $item->provider?->name ?? 'Wildflow',
            ],
            'identity' => [
                'title' => $this->normalizeTitle($item->title),
                'brand' => $item->brand?->name ?? 'Unknown',
                'upc' => $item->upc,
            ],
            'geography' => [
                'region_code' => $item->region?->code ?? 'GLB',
                'region_name' => $item->region?->name_ru ?? 'Глобально',
                'flag' => $item->region?->flag ?? '🌍',
            ],
            'classification' => [
                'category_path' => $item->category,
                'master_category' => str_contains($item->category ?? '', ' › ') ? explode(' › ', $item->category)[0] : $item->category,
                'reward_type' => $item->reward_type,
                'order_type' => $orderType,
                'requires_player_id' => !empty($playerIdFields),
                'player_id_fields' => $playerIdFields,
            ],
            'pricing' => $pricing,
            'assets' => [
                'image_url' => $item->image,
                'logo_url' => $item->brand_logo_url,
                'card_image_url' => asset("img/card/sh_1/{$item->sku}.jpg"),
                'card_image_dark_url' => asset("img/card/sh_1/{$item->sku}_dark.jpg"),
                'card_image_info_url' => asset("img/card/sh_1/{$item->sku}_info.jpg"),
                'card_image_white_url' => asset("img/card/sh_1/{$item->sku}_white.jpg"),
                'card_image_blend_url' => asset("img/card/sh_1/{$item->sku}_blend.jpg"),
            ],
            'redemption' => [
                'activation_url' => $this->normalizeUrl($item->getActivationServiceUrl()),
                'instructions' => $item->final_instructions,
                'expected_output' => $this->detectExpectedOutput($orderType),
            ],
            'stock' => [
                'in_stock' => $rawData['in_stock'] ?? $rawData['stock'] ?? true,
                'is_backup' => $rawData['is_backup'] ?? false,
            ],
        ];
    }

    /**
     * Standardize a ProviderProduct (Fazer, or any provider) into the Meanly Golden Schema.
     */
    public function standardizeProviderProduct(ProviderProduct $item, ?Shop $shop = null): array
    {
        $financeService = app(FinanceService::class);

        $purchasePriceUsd = (float) ($item->purchase_price ?? 0);
        $currency         = $item->currency ?? 'USD';

        $rateToRub = $financeService->getRate($currency);
        $purchasePriceRub = $purchasePriceUsd * $rateToRub;
        $retailPriceRubBase = (float) ($item->retail_price ?? 0) * $rateToRub;
        
        $priceForSellerRub = $this->getPurchasePriceForShop($purchasePriceRub, $retailPriceRubBase, $shop);

        $rawData = is_array($item->data) ? $item->data : [];
        $orderType = $this->detectOrderType($item, $rawData);
        $playerIdFields = $this->getPlayerIdFields($orderType, $rawData);

        return [
            'id'       => $item->id,
            'sku'      => $item->sku,
            'provider' => [
                'id'   => $item->provider_id,
                'type' => $item->provider?->type ?? 'unknown',
                'name' => $item->provider?->name ?? 'Unknown',
            ],
            'identity' => [
                'title' => $this->normalizeTitle($item->name),
                'brand' => $item->brand?->name ?? $item->category ?? 'Unknown',
                'upc'   => $rawData['upc'] ?? null,
            ],
            'geography' => [
                'region_code' => $item->region?->code ?? 'GLB',
                'region_name' => $item->region?->name_ru ?? 'Глобально',
                'flag'        => $item->region?->flag ?? '🌍',
            ],
            'classification' => [
                'category_path'   => $item->category,
                'master_category' => $item->brand?->catalogGroup?->name ?? $item->category,
                'order_type'      => $orderType,
                'requires_player_id' => !empty($playerIdFields),
                'player_id_fields'   => $playerIdFields,
            ],
            'pricing'  => [
                'currency'        => 'RUB',
                'buying_price'    => round($purchasePriceRub, 2),
                'retail_price'    => round($priceForSellerRub, 2),
                'msrp_price'      => round($retailPriceRubBase, 2),
                'source_currency' => $currency,
                'source_retail_price'   => round((float)$item->retail_price, 4),
                'is_variable'     => false,
                'is_surcharge_applied' => $priceForSellerRub > $purchasePriceRub * 1.005,
            ],
            'assets'   => [
                'image_url'  => $item->image ?? $item->brand?->logo_url ?? null,
                'logo_url'   => $item->brand?->logo_url ?? null,
            ],
            'redemption' => [
                'activation_url' => $this->normalizeUrl($item->data['activation_url'] ?? null),
                'instructions'   => $item->data['instructions'] ?? $item->data['redemption_instructions'] ?? null,
                'expected_output' => $this->detectExpectedOutput($orderType),
            ],
            'stock' => [
                'in_stock'   => $rawData['in_stock'] ?? $rawData['stock'] ?? null,
                'is_backup'  => $rawData['is_backup'] ?? false,
            ],
        ];
    }

    /**
     * Calculate the selling price for a Shop based on tariff_type and markup_percent.
     */
    public function getPurchasePriceForShop(float $purchasePriceRub, float $msrpPriceRub, ?Shop $shop): float
    {
        if (! $shop) {
            return round(max($msrpPriceRub, $purchasePriceRub * 1.10), 2);
        }

        $entity = $shop->legalEntity;

        $markupPct = ($shop->markup_percent > 0) ? $shop->markup_percent : ($entity->markup_percent ?? 0);
        
        if ($markupPct > 0) {
            return round($purchasePriceRub * (1 + $markupPct / 100), 2);
        }

        $tariff = $shop->tariff_type ?? $entity->tariff_type ?? 'retail';
        
        $costPlus1 = $purchasePriceRub * 1.01;

        return match ($tariff) {
            'privileged' => round($costPlus1, 2), // Wholesale: Cost + 1%
            'retail'     => round(max($msrpPriceRub, $purchasePriceRub * 1.05), 2), // Retail: Use MSRP (min +5%)
            default      => round(max($msrpPriceRub, $purchasePriceRub * 1.05), 2),
        };
    }

    /**
     * Detect what kind of order this product will create.
     */
    protected function detectOrderType(Model|WildflowCatalog|ProviderProduct $item, array $rawData): string
    {
        $cat = mb_strtolower($item->category ?? '');
        $sku = mb_strtolower($item->sku ?? '');
        $name = mb_strtolower($item->name ?? $item->title ?? '');
        
        if (str_contains($cat, 'telegram') || str_contains($sku, 'telegram')) return 'telegram';
        if (str_contains($cat, 'roblox')   || str_contains($sku, 'roblox'))   return 'roblox_packs';
        if (str_contains($cat, 'steam') && str_contains($name, 'gift'))       return 'steam_gift';
        
        if ($item->type === 'game' || ($item instanceof WildflowCatalog && $item->reward_type === 'game_key')) return 'game_key';
        if (str_contains($sku, 'steam') && str_contains($sku, 'key')) return 'game_key';
        
        if (str_contains($cat, 'steam')) return 'steam_topup';
        
        if (isset($rawData['face_value']) || ($item instanceof WildflowCatalog && $item->reward_type === 'Gift-Card')) return 'gift_card';

        if (str_contains($name, 'topup') || str_contains($cat, 'direct')) {
            return 'topup';
        }

        return 'gift_card';
    }

    /**
     * Get player ID fields based on order type and provider data.
     */
    protected function getPlayerIdFields(string $orderType, array $rawData): array
    {
        $playerIdFields = [];
        
        if ($orderType === 'roblox_packs') {
            $playerIdFields = [
                ['name' => 'login', 'label' => 'Roblox Login', 'required' => true, 'type' => 'text'],
                ['name' => 'password', 'label' => 'Roblox Password', 'required' => true, 'type' => 'password'],
                ['name' => 'backup_codes', 'label' => 'Backup Codes (Optional)', 'required' => false, 'type' => 'textarea'],
            ];
        }

        if ($orderType === 'steam_gift') {
            $playerIdFields = [
                ['name' => 'invite_url', 'label' => 'Steam Friend Invite URL', 'required' => true, 'type' => 'text'],
            ];
        }

        if ($orderType === 'steam_topup') {
            $playerIdFields = [
                ['name' => 'player_id', 'label' => 'Steam Login', 'required' => true, 'type' => 'text', 'placeholder' => 'Enter Steam account login'],
            ];
        }

        if ($orderType === 'topup' && empty($playerIdFields)) {
            $playerIdFields = [
                ['name' => 'player_id', 'label' => 'Player ID / User ID', 'required' => true, 'type' => 'text'],
            ];
        }

        if (in_array($orderType, ['telegram', 'stars'])) {
            $playerIdFields = [
                ['name' => 'player_id', 'label' => 'Telegram Username / ID', 'required' => true, 'type' => 'text', 'placeholder' => '@username or 12345678'],
            ];
        }

        return $playerIdFields;
    }

    /**
     * Standardize a retail code/card into the Meanly Golden Schema.
     */
    public function standardizeRetailCode(array $rawCard, WildflowCatalog $product): array
    {
        return [
            'type' => 'digital_code',
            'product' => [
                'sku' => $product->sku,
                'title' => $product->title,
                'brand' => $product->brand?->name,
            ],
            'credentials' => [
                'code' => $rawCard['card_number'] ?? null,
                'pin' => $rawCard['pin_code'] ?? null,
                'serial' => $rawCard['serial_number'] ?? null,
                'valid_until' => $rawCard['expiry_date'] ?? null,
            ],
            'redemption' => [
                'activation_url' => $this->normalizeUrl($product->getActivationServiceUrl()),
                'instructions' => $product->final_instructions,
            ],
            'raw_metadata' => [
                'provider_order_id' => $rawCard['order_id'] ?? null,
            ],
        ];
    }

    /**
     * Clean up messy titles from providers.
     */
    protected function normalizeTitle(string $title): string
    {
        $title = str_ireplace([
            'Direct Topup', 'Instant Delivery', 'Global', 'Fast Delivery', 
            'Direct', 'Official', 'Top-up', 'Topup', 'Cis', 'Europe', 'USA'
        ], '', $title);

        return trim(preg_replace('/\s+/', ' ', $title));
    }

    /**
     * Define what the seller will get back after a successful order.
     */
    protected function detectExpectedOutput(string $orderType): array
    {
        return match ($orderType) {
            'gift_card', 'game_key' => [
                'fields' => ['code', 'pin', 'serial', 'expiry_date'],
                'format' => 'digital_credentials'
            ],
            'topup', 'steam_topup', 'roblox_packs' => [
                'fields' => ['transaction_id', 'status', 'final_balance'],
                'format' => 'fulfillment_status'
            ],
            'telegram', 'stars' => [
                'fields' => ['order_id', 'status'],
                'format' => 'service_activation'
            ],
            default => [
                'fields' => ['data'],
                'format' => 'generic'
            ]
        };
    }

    protected function normalizeUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        return str_starts_with($url, 'http') ? $url : "https://{$url}";
    }
}
