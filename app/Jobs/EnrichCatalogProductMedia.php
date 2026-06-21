<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductSalesChannel;
use App\Models\ProviderProduct;
use App\Models\Shop;
use App\Models\WildflowCatalog;
use App\Services\CardImageService;
use App\Services\VideoInstructionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class EnrichCatalogProductMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public readonly int $productId,
        public readonly int $providerProductId,
        public readonly ?int $catalogItemId,
        public readonly int $shopId,
        public readonly bool $isVariablePrice = false,
        public readonly ?string $fallbackName = null,
        public readonly bool $pushToYandex = false,
    ) {}

    public function handle(): void
    {
        $product = Product::find($this->productId);
        $providerProduct = ProviderProduct::find($this->providerProductId);
        $shop = Shop::find($this->shopId);
        $catalogItem = $this->catalogItemId ? WildflowCatalog::find($this->catalogItemId) : null;

        if (! $product || ! $providerProduct || ! $shop) {
            return;
        }

        try {
            $kit = $catalogItem
                ? app(CardImageService::class)->generateForCatalogItem($catalogItem, $shop)
                : [];

            $product->update([
                'image' => $kit['images']['main'] ?? $product->image ?? $providerProduct->image,
                'pictures' => $kit['images'] ?? $product->pictures ?? [],
                'name' => isset($kit['title']) && ! $this->isVariablePrice
                    ? $kit['title']
                    : ($product->name ?: $this->fallbackName),
                'description' => $kit['description'] ?? $product->description,
            ]);
        } catch (\Throwable $e) {
            Log::warning('EnrichCatalogProductMedia: card enrichment failed', [
                'product_id' => $this->productId,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $videoUrl = app(VideoInstructionService::class)->generateForProduct($product->refresh());
            if ($videoUrl) {
                $product->update(['videos' => [$videoUrl]]);
            }
        } catch (\Throwable $e) {
            Log::warning('EnrichCatalogProductMedia: video generation failed', [
                'product_id' => $this->productId,
                'error' => $e->getMessage(),
            ]);
        }

        if (! $this->pushToYandex) {
            return;
        }

        $yandexChannel = ProductSalesChannel::query()
            ->where('product_id', $this->productId)
            ->where('shop_id', $this->shopId)
            ->where('channel', 'yandex_market')
            ->where('is_enabled', true)
            ->exists();

        if ($yandexChannel) {
            PushProductToYandex::dispatch($this->productId, $this->shopId);
        }
    }
}
