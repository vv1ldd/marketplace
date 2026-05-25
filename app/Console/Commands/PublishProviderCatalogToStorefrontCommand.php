<?php

namespace App\Console\Commands;

use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductSalesChannel;
use App\Models\ProviderProduct;
use App\Services\MeanlyFirstPartyStorefrontService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class PublishProviderCatalogToStorefrontCommand extends Command
{
    protected $signature = 'meanly:publish-provider-catalog
        {--provider=wildflow : Provider type to publish}
        {--limit= : Limit provider products processed}
        {--dry-run : Count changes without writing products}
        {--rebuild-identities : Rebuild canonical identities and commerce entities after publishing}';

    protected $description = 'Publish active provider catalog items as first-party Meanly storefront products.';

    public function handle(MeanlyFirstPartyStorefrontService $storefront): int
    {
        $providerType = (string) $this->option('provider');
        $limit = $this->option('limit') !== null && $this->option('limit') !== ''
            ? max(1, (int) $this->option('limit'))
            : null;
        $dryRun = (bool) $this->option('dry-run');

        $shop = $storefront->shop();
        $channel = $storefront->storefrontChannel();
        $rates = Currency::query()
            ->get(['code', 'rate_to_rub', 'manual_rate', 'is_shadow'])
            ->mapWithKeys(fn (Currency $currency): array => [
                strtoupper((string) $currency->code) => (float) $currency->effective_rate,
            ])
            ->all();

        $query = ProviderProduct::query()
            ->with('provider')
            ->where('is_active', true)
            ->whereHas('provider', fn ($query) => $query->where('type', $providerType));

        if ($limit !== null) {
            $query->limit($limit);
        }

        $stats = [
            'seen' => 0,
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'exposed' => 0,
            'skipped' => 0,
        ];

        $process = function ($items) use ($shop, $channel, $rates, $dryRun, &$stats): void {
            foreach ($items as $item) {
                $stats['seen']++;

                $sku = trim((string) $item->sku);
                if ($sku === '' || blank($item->name)) {
                    $stats['skipped']++;
                    continue;
                }

                $product = Product::query()->firstOrNew([
                    'shop_id' => $shop->id,
                    'sku' => $sku,
                ]);
                $wasNew = ! $product->exists;
                $payload = $this->productPayload($item, $rates);

                if (! $wasNew) {
                    if ((string) $product->wildflow_catalog_sku === $sku) {
                        unset($payload['wildflow_catalog_sku']);
                    }

                    if ((int) data_get($product->data, 'provider_product_id') === (int) $item->id) {
                        unset($payload['data']);
                    }
                }

                $product->fill($payload);

                if ($dryRun) {
                    $wasNew ? $stats['created']++ : ($product->isDirty() ? $stats['updated']++ : $stats['unchanged']++);
                    $stats['exposed']++;
                    continue;
                }

                if ($wasNew || $product->isDirty()) {
                    $product->save();
                    $wasNew ? $stats['created']++ : $stats['updated']++;
                } else {
                    $stats['unchanged']++;
                }

                ProductSalesChannel::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'shop_id' => $shop->id,
                        'channel' => $channel,
                    ],
                    [
                        'is_enabled' => true,
                        'last_error' => null,
                    ],
                );
                $stats['exposed']++;
            }
        };

        if ($limit !== null) {
            $process($query->get());
        } else {
            $query->orderBy('id')->chunkById(300, $process);
        }

        $this->info(($dryRun ? 'Dry run complete.' : 'Provider catalog published.').' Shop #'.$shop->id.' channel '.$channel);
        foreach ($stats as $name => $value) {
            $this->line($name.': '.$value);
        }

        if (! $dryRun && (bool) $this->option('rebuild-identities')) {
            Artisan::call('catalog:rebuild-identities');
            $this->line(trim(Artisan::output()));
            Artisan::call('commerce:rebuild-entities');
            $this->line(trim(Artisan::output()));
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(ProviderProduct $item, array $rates): array
    {
        $currency = strtoupper((string) ($item->currency ?: 'RUB'));
        $rate = in_array($currency, ['RUB', 'RUBT'], true) ? 1.0 : (float) ($rates[$currency] ?? 1.0);
        $retail = (float) ($item->retail_price ?: $item->max_price ?: $item->min_price ?: $item->purchase_price ?: 0);
        $purchase = (float) ($item->purchase_price ?: $retail);

        return [
            'provider_id' => $item->provider_id,
            'brand_id' => $item->brand_id,
            'wildflow_catalog_sku' => (string) $item->sku,
            'name' => $item->name,
            'type' => 'giftcard',
            'category' => $item->category,
            'canonical_category' => $item->canonical_category,
            'price_rub' => (int) max(1, round($retail * $rate * 100)),
            'purchase_price' => (int) round($purchase),
            'purchase_currency' => $currency,
            'purchase_price_rub' => (int) max(1, round($purchase * $rate * 100)),
            'image' => $item->image,
            'data' => [
                'source' => 'provider_product',
                'provider_product_id' => $item->id,
                'provider_type' => $item->provider?->type,
                'provider_sku' => (string) $item->sku,
                'auto_purchase' => true,
            ],
            'is_active' => true,
            'is_manual' => false,
        ];
    }
}
