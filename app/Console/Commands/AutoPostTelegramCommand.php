<?php

namespace App\Console\Commands;

use App\Models\DirectChannel;
use App\Models\Product;
use App\Models\TelegramPost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AutoPostTelegramCommand extends Command
{
    protected $signature = 'telegram:auto-post {--channel= : Specific channel ID} {--product= : Specific Product ID} {--dry-run : Simulate without posting}';
    protected $description = 'Automated CustDev posting to Telegram channels';

    public function handle()
    {
        $this->info('Starting Auto-Post Telegram...');

        $channelsQuery = DirectChannel::where('type', 'telegram_bot')
            ->where('is_active', true);

        if ($this->option('channel')) {
            $channelsQuery->where('id', $this->option('channel'));
        }

        $channels = $channelsQuery->get();

        if ($channels->isEmpty()) {
            $this->warn('No active Telegram channels found.');
            return;
        }

        foreach ($channels as $channel) {
            $this->processChannel($channel);
        }

        $this->info('Done.');
    }

    protected function processChannel(DirectChannel $channel)
    {
        $settings = $channel->settings ?? [];
        $botToken = $settings['telegram_bot_token'] ?? null;
        $chatId = $settings['telegram_channel_id'] ?? null;
        $template = $settings['telegram_message_template'] ?? "🔥 {product_name}\n💰 {price} ₽\n👉 {buy_link}";
        $margin = $settings['margin_percent'] ?? 5;
        $fee = $settings['marketplace_fee_percent'] ?? 0;
        $minPrice = $settings['min_price'] ?? 300;

        if (!$botToken || !$chatId) {
            $this->error("Channel {$channel->id} missing Bot Token or Chat ID.");
            return;
        }

        // Выбираем товар
        if ($this->option('product')) {
            $product = Product::find($this->option('product'));
        } else {
            $query = Product::where('is_active', true)
                ->whereNotNull('purchase_price_rub')
                ->where('purchase_price_rub', '>', 0);

            // Фильтр по провайдерам
            $assortmentType = $settings['assortment_type'] ?? 'all';
            if ($assortmentType === 'providers' && !empty($settings['selected_providers'])) {
                $query->whereIn('provider_id', $settings['selected_providers']);
            }

            // Фильтр по брендам
            if ($assortmentType === 'brands' && !empty($settings['selected_brands'])) {
                $query->whereIn('brand_id', $settings['selected_brands']);
            }

            // Ручной выбор (через Pivot)
            if ($assortmentType === 'manual') {
                $query->whereHas('directChannels', function ($q) use ($channel) {
                    $q->where('direct_channels.id', $channel->id)
                      ->where('direct_channel_product.is_enabled', true);
                });
            }

            // Исключаем товары, которые постили недавно в этот канал (например, за последние 24 часа)
            $recentlyPostedIds = TelegramPost::where('direct_channel_id', $channel->id)
                ->where('created_at', '>', now()->subHours(24))
                ->pluck('product_id')
                ->toArray();
            
            if (!empty($recentlyPostedIds)) {
                $query->whereNotIn('id', $recentlyPostedIds);
            }

            $product = $query->inRandomOrder()->first();
        }

        if (!$product) {
            $this->warn("No active products available for Channel {$channel->id} with current filters.");
            return;
        }

        // Считаем цену
        // purchase_price_rub в БД хранится в копейках (100-based)
        $basePrice = (float) ($product->purchase_price_rub / 100);
        
        if ($basePrice <= 0) {
            $this->warn("Product {$product->id} has no valid base price.");
            return;
        }

        $finalPrice = round(($basePrice * (1 + $margin / 100)) / (1 - $fee / 100));

        if ($finalPrice < $minPrice) {
            $this->warn("Product {$product->id} price {$finalPrice} is below min price {$minPrice}. Skipping.");
            return;
        }

        // Создаем "черновик" поста для получения ID
        $post = TelegramPost::create([
            'direct_channel_id' => $channel->id,
            'product_id' => $product->id,
            'wildflow_catalog_id' => $product->catalog_id, // Сохраняем для обратной совместимости, если есть
            'posted_price' => $finalPrice,
        ]);

        $buyLink = route('telegram.click', ['id' => $post->id]);

        $productName = $product->name ?? 'Неизвестный товар';
        
        $text = str_replace(
            ['{product_name}', '{price}', '{buy_link}', '{region}'],
            [$productName, $finalPrice, $buyLink, $product->region_id ?? 'Global'],
            $template
        );

        $this->info("Posting {$productName} for {$finalPrice} to {$chatId}...");

        if ($this->option('dry-run')) {
            $this->info("Dry run: [TEXT]\n{$text}");
            return;
        }

        // Отправка в Telegram
        $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '🛍 Купить', 'url' => $buyLink]
                    ]
                ]
            ])
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $post->update([
                'message_id' => $data['result']['message_id'] ?? null
            ]);
            $this->info("Successfully posted message ID: {$post->message_id}");
        } else {
            $this->error("Failed to post: " . $response->body());
            $post->delete(); // Очищаем черновик, если не запостили
        }
    }
}
