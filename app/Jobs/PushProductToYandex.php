<?php

namespace App\Jobs;

use App\Http\Services\YmService;
use App\Models\Product;
use App\Models\ProductSalesChannel;
use App\Models\Settings;
use App\Models\Shop;
use App\Services\CanonicalCategoryResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PushProductToYandex implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(
        public readonly int $productId,
        public readonly int $shopId,
    ) {}

    public function handle(): void
    {
        $product = Product::find($this->productId);
        $shop = Shop::find($this->shopId);

        if (! $product || ! $shop) {
            return;
        }

        $channel = ProductSalesChannel::query()
            ->where('product_id', $product->id)
            ->where('shop_id', $shop->id)
            ->where('channel', 'yandex_market')
            ->first();

        if (! $channel?->is_enabled) {
            return;
        }

        try {
            $resolver = app(CanonicalCategoryResolver::class);
            $fallbackCategoryId = (int) ($product->market_category_id ?: $shop->ym_category_id ?: Settings::get('YM_CATEGORY_ID', 989939));
            $categoryId = $resolver->yandexCategoryId($resolver->forProduct($product), $fallbackCategoryId);
            if ((int) $product->market_category_id !== $categoryId) {
                $product->market_category_id = $categoryId;
                $product->save();
            }

            (new YmService($shop))->offerMappingsUpdate([
                ['offer' => $product->toYmOffer($categoryId, $shop->id)],
            ]);

            $channel->update([
                'last_synced_at' => now(),
                'last_error' => null,
            ]);

            $product->update(['send_to_ym_at' => now()]);

            Log::info('PushProductToYandex: товар отправлен', [
                'shop_id' => $shop->id,
                'product_id' => $product->id,
                'sku' => $product->sku,
            ]);
        } catch (\Throwable $e) {
            $channel->update(['last_error' => $e->getMessage()]);

            Log::error('PushProductToYandex: ошибка', [
                'shop_id' => $shop->id,
                'product_id' => $product->id,
                'sku' => $product->sku,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
