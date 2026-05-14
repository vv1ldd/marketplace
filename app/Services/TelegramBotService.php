<?php

namespace App\Services;

use App\Models\DirectChannel;
use App\Models\Product;
use Illuminate\Support\Facades\Http;

class TelegramBotService
{
    protected $token;
    protected $channel;

    public function __construct(DirectChannel $channel)
    {
        $this->channel = $channel;
        $this->token = $channel->settings['telegram_bot_token'] ?? null;
    }

    public function handle($update)
    {
        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        } elseif (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);
        }
    }

    protected function handleMessage($message)
    {
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';

        if ($text === '/start') {
            $this->sendGroups($chatId);
        }
    }

    protected function handleCallback($callback)
    {
        $chatId = $callback['message']['chat']['id'];
        $data = $callback['data'];

        if (str_starts_with($data, 'group_')) {
            $groupId = str_replace('group_', '', $data);
            $this->sendBrands($chatId, $groupId);
        } elseif (str_starts_with($data, 'brand_')) {
            $parts = explode(':', str_replace('brand_', '', $data));
            $brandId = $parts[0];
            $groupId = $parts[1] ?? null;
            $this->sendProducts($chatId, $brandId, $groupId);
        } elseif (str_starts_with($data, 'prod_')) {
            $parts = explode(':', str_replace('prod_', '', $data));
            $productId = $parts[0];
            $groupId = $parts[1] ?? null;
            $this->sendProductDetail($chatId, $productId, $groupId);
        } elseif (str_starts_with($data, 'buy_')) {
            $productId = str_replace('buy_', '', $data);
            $this->handlePurchase($chatId, $productId);
        } elseif ($data === 'show_groups') {
            $this->sendGroups($chatId);
        }
    }

    public function sendGroups($chatId)
    {
        // Получаем наши канонические группы, у которых есть бренды с активными товарами в канале
        $groups = \App\Models\CatalogGroup::where('is_active', true)
            ->where(function ($query) {
                // Вариант 1: Через бренды
                $query->whereHas('brands', function ($bq) {
                    $bq->whereHas('products', function ($pq) {
                        $pq->where('is_active', true)
                            ->whereHas('directChannels', function ($dq) {
                                $dq->where('direct_channels.id', $this->channel->id)
                                    ->where('direct_channel_product.is_enabled', true);
                            });
                    });
                })
                // Вариант 2: Через маппинг категорий (fallback)
                ->orWhereHas('mappings', function ($mq) {
                    $mq->whereHas('provider', function ($pq) {
                        $pq->whereHas('products', function ($prodQ) {
                            $prodQ->where('is_active', true)
                                ->whereHas('directChannels', function ($dq) {
                                    $dq->where('direct_channels.id', $this->channel->id)
                                        ->where('direct_channel_product.is_enabled', true);
                                });
                        });
                    });
                });
            })
            ->orderBy('sort_order')
            ->get();

        if ($groups->isEmpty()) {
            $this->sendFallbackGroups($chatId);
            return;
        }

        $buttons = $groups->map(function ($group) {
            return [['text' => ($group->icon ?: '📦') . " " . $group->name, 'callback_data' => 'group_' . $group->id]];
        })->toArray();

        $this->sendMessage($chatId, "Добро пожаловать в наш магазин! 🎮\nВыберите интересующую вас категорию:", [
            'inline_keyboard' => $buttons
        ]);
    }

    public function sendBrands($chatId, $groupId)
    {
        $group = \App\Models\CatalogGroup::find($groupId);
        
        // Получаем бренды: либо привязанные напрямую к группе, либо через маппинг категорий
        $brands = \App\Models\Brand::where(function ($q) use ($groupId) {
            $q->where('catalog_group_id', $groupId)
              ->orWhereHas('products', function ($pq) use ($groupId) {
                  $pq->whereIn('category', function ($sub) use ($groupId) {
                      $sub->select('provider_category_name')
                          ->from('provider_category_mappings')
                          ->where('catalog_group_id', $groupId);
                  });
              });
        })
        ->whereHas('products', function ($pq) {
            $pq->where('is_active', true)
              ->whereHas('directChannels', function ($dq) {
                  $dq->where('direct_channels.id', $this->channel->id)
                    ->where('direct_channel_product.is_enabled', true);
              });
        })->get();

        $buttons = $brands->map(function ($brand) use ($groupId) {
            return [['text' => "📁 " . $brand->name, 'callback_data' => 'brand_' . $brand->id . ':' . $groupId]];
        })->toArray();

        $buttons[] = [['text' => "⬅️ Назад", 'callback_data' => 'show_groups']];

        $this->sendMessage($chatId, "Бренды в категории " . $group->name . ":", [
            'inline_keyboard' => $buttons
        ]);
    }

    public function sendFallbackGroups($chatId)
    {
        // ... (предыдущая логика парсинга строк)
        $categories = Product::where('is_active', true)
            ->whereHas('directChannels', function ($q) {
                $q->where('direct_channels.id', $this->channel->id)
                  ->where('direct_channel_product.is_enabled', true);
            })
            ->select('category')
            ->distinct()
            ->get()
            ->map(fn($p) => explode(' › ', $p->category)[0])
            ->unique()
            ->filter()
            ->values();

        $buttons = $categories->map(function ($cat) {
            return [['text' => "📦 " . $cat, 'callback_data' => 'fallback_group_' . $cat]];
        })->toArray();

        $this->sendMessage($chatId, "Выберите категорию:", [
            'inline_keyboard' => $buttons
        ]);
    }

    protected function getGroupIcon($group)
    {
        return match (mb_strtolower($group)) {
            'gaming & streaming' => '🎮',
            'software' => '💻',
            'retail' => '🛒',
            'travel & entertainment' => '✈️',
            'food & drink' => '🍕',
            'fashion & accessories' => '👕',
            'books, movies & music' => '📚',
            'sport & fitness' => '🏃',
            'health & beauty' => '💄',
            'finance' => '💳',
            default => '📦',
        };
    }

    public function sendProducts($chatId, $brandId, $groupId)
    {
        $brand = \App\Models\Brand::find($brandId);
        $products = Product::where('brand_id', $brandId)
            ->where('is_active', true)
            ->where(function ($q) use ($groupId) {
                if ($groupId) {
                    $q->whereIn('category', function ($sub) use ($groupId) {
                        $sub->select('provider_category_name')
                            ->from('provider_category_mappings')
                            ->where('catalog_group_id', $groupId);
                    })->orWhereHas('brand', fn($bq) => $bq->where('catalog_group_id', $groupId));
                }
            })
            ->whereHas('directChannels', function ($q) {
                $q->where('direct_channels.id', $this->channel->id)
                  ->where('direct_channel_product.is_enabled', true);
            })
            ->limit(10)
            ->get();

        $buttons = $products->map(function ($prod) use ($groupId) {
            return [['text' => "🎁 " . $prod->name . " — " . ($prod->purchase_price_rub / 100) . "₽", 'callback_data' => 'prod_' . $prod->id . ':' . $groupId]];
        })->toArray();

        $buttons[] = [['text' => "⬅️ Назад", 'callback_data' => 'group_' . $groupId]];

        $this->sendMessage($chatId, "Товары бренда " . $brand->name . ":\nВыберите товар для покупки:", [
            'inline_keyboard' => $buttons
        ]);
    }

    public function sendProductDetail($chatId, $productId, $groupId = null)
    {
        $product = Product::find($productId);
        
        $text = "<b>" . $product->name . "</b>\n\n";
        $text .= "💰 Цена: " . ($product->purchase_price_rub / 100) . " руб.\n\n";
        $text .= $product->description ? strip_tags($product->description) : "Описание скоро появится.";

        $buttons = [
            [['text' => "💳 Купить", 'callback_data' => 'buy_' . $product->id]],
            [['text' => "⬅️ Назад к списку", 'callback_data' => 'brand_' . $product->brand_id . ($groupId ? ':' . $groupId : '')]],
        ];

        if ($product->image) {
            $imageUrl = str_starts_with($product->image, 'http') ? $product->image : config('app.url') . '/' . $product->image;
            $this->sendPhoto($chatId, $imageUrl, $text, [
                'inline_keyboard' => $buttons
            ]);
        } else {
            $this->sendMessage($chatId, $text, [
                'inline_keyboard' => $buttons
            ]);
        }
    }

    public function handlePurchase($chatId, $productId)
    {
        $product = Product::find($productId);
        $settings = $this->channel->settings ?? [];
        $sbp = $settings['sbp_details'] ?? 'Реквизиты не настроены. Пожалуйста, обратитесь к менеджеру.';

        $reply = "Отличный выбор! 🎁 <b>{$product->name}</b>\n\n";
        $reply .= "Для завершения покупки переведите " . ($product->purchase_price_rub / 100) . "₽ по реквизитам:\n\n";
        $reply .= "<code>{$sbp}</code>\n\n";
        $reply .= "После оплаты <b>обязательно пришлите скриншот чека</b> в этот чат. Наш менеджер проверит оплату и отправит вам код активации.";

        $this->sendMessage($chatId, $reply);
    }

    protected function sendMessage($chatId, $text, $replyMarkup = null)
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup) {
            $payload['reply_markup'] = $replyMarkup;
        }

        Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", $payload);
    }

    protected function sendPhoto($chatId, $photo, $caption, $replyMarkup = null)
    {
        $payload = [
            'chat_id' => $chatId,
            'photo' => $photo,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup) {
            $payload['reply_markup'] = $replyMarkup;
        }

        Http::post("https://api.telegram.org/bot{$this->token}/sendPhoto", $payload);
    }
}
