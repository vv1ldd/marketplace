<?php

namespace App\Http\Controllers;

use App\Services\AppStoreLookupService;
use App\Services\CanonicalStorefrontHomepageService;
use App\Services\MeanlyAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class StorefrontChatController extends Controller
{
    public function page()
    {
        return view('storefront.ai-chat');
    }

    /**
     * Обрабатывает AJAX запрос из ИИ-чата витрины
     */
    public function chat(
        Request $request,
        CanonicalStorefrontHomepageService $homepageService,
        AppStoreLookupService $appStoreLookup,
    )
    {
        $message = $request->input('message');
        $chatHistory = $request->input('history', []); // Ожидаем массив ['role' => 'user|assistant', 'content' => '...']

        if (empty(trim((string) $message))) {
            return response()->json([
                'success' => false,
                'error' => 'Запрос пуст'
            ], 400);
        }

        $startedAt = microtime(true);
        $appStoreIntent = $appStoreLookup->intentFromMessage((string) $message);
        $externalResults = $appStoreIntent !== null
            ? $appStoreLookup->search($appStoreIntent['app_query'], $appStoreIntent['region'])
            : [];

        // 1. Получаем релевантные карточки для чата: пользователь пишет свободно,
        // поэтому чистим фразу от служебных слов и добавляем синонимы каталога.
        $cards = $this->catalogCardsForMessage((string) $message, $homepageService);

        if ($appStoreIntent !== null) {
            $giftCardCards = $this->catalogCardsForMessage($appStoreIntent['gift_card_query'], $homepageService)
                ->filter(fn (array $card): bool => $this->isAppleGiftCardCard($card))
                ->values();

            if ($giftCardCards->isNotEmpty()) {
                $cards = $homepageService->groupedCardsForInterface($giftCardCards);
            }
        }

        // 2. Формируем компактный каталог для контекста LLM
        $catalog = $cards->map(function ($card): array {
            $offerPrice = data_get($card, 'selected_offer.price.amount');
            $variantGroup = (array) ($card['variant_group'] ?? []);

            return [
                'name' => $card['name'],
                'brand' => $card['brand'] ?? 'Meanly',
                'region' => $card['region'] ?? 'global',
                'category' => $card['category_label'] ?? '',
                'price' => $offerPrice !== null ? ($offerPrice.' ₽') : 'Скоро в продаже',
                'availability' => $card['has_selected_offer'] ? 'active_offer' : 'catalog_only',
                'cta' => $card['cta_label'] ?? ($card['has_selected_offer'] ? 'Купить' : 'Открыть товар'),
                'url' => $this->catalogUrlForPrompt((string) $card['url']),
                'is_grouped' => (bool) ($variantGroup['is_grouped'] ?? false),
                'variant_count' => (int) ($variantGroup['variant_count'] ?? 1),
                'region_count' => (int) ($variantGroup['region_count'] ?? 0),
                'nominal_count' => (int) ($variantGroup['nominal_count'] ?? 0),
                'regions' => (array) ($variantGroup['regions'] ?? []),
                'nominals' => (array) ($variantGroup['nominals'] ?? []),
            ];
        })->toArray();

        // Фильтруем каталог, если он слишком большой для контекста, отбирая наиболее релевантные по совпадению слов
        if (count($catalog) > 80) {
            $keywords = array_filter(explode(' ', mb_strtolower($message)), fn($w) => mb_strlen($w) > 2);
            $catalog = collect($catalog)->sortByDesc(function($item) use ($keywords) {
                $score = 0;
                $haystack = mb_strtolower($item['name'] . ' ' . $item['brand'] . ' ' . $item['region'] . ' ' . $item['category']);
                foreach ($keywords as $kw) {
                    if (str_contains($haystack, $kw)) {
                        $score += 10;
                    }
                }
                return $score;
            })->take(50)->values()->toArray();
        }

        // 3. Формируем промпт
        $model = config('services.ollama.model', 'gemma4');
        $ollamaUrl = config('services.ollama.url', 'http://localhost:11434');

        $prompt = $this->buildSystemPrompt($catalog, $message, $chatHistory, $externalResults);
        $products = $this->productSuggestions($catalog);

        try {
            $response = Http::timeout(60)
                ->post(rtrim($ollamaUrl, '/') . '/api/generate', [
                    'model' => $model,
                    'prompt' => $prompt,
                    'stream' => false,
                ]);

            if ($response->successful()) {
                $answer = $this->ensureProductSuggestionsInResponse(
                    (string) $response->json('response'),
                    $products,
                    $externalResults,
                );

                app(MeanlyAnalyticsService::class)->track('ai.chat.completed', [
                    'message_length' => mb_strlen((string) $message),
                    'catalog_candidates' => count($catalog),
                    'products_count' => count($products),
                    'external_results_count' => count($externalResults),
                    'model' => $model,
                    'model_unavailable' => false,
                ], [
                    'event_type' => 'ai',
                    'surface' => 'ai',
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);

                return response()->json([
                    'success' => true,
                    'response' => $answer,
                    'external_results' => $externalResults,
                    'products' => $products,
                    'model' => $model
                ]);
            }

            if ($products !== []) {
                app(MeanlyAnalyticsService::class)->track('ai.chat.fallback', [
                    'message_length' => mb_strlen((string) $message),
                    'catalog_candidates' => count($catalog),
                    'products_count' => count($products),
                    'external_results_count' => count($externalResults),
                    'model' => $model,
                    'model_unavailable' => true,
                    'upstream_status' => $response->status(),
                ], [
                    'event_type' => 'ai',
                    'surface' => 'ai',
                    'severity' => 'warning',
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);

                return response()->json([
                    'success' => true,
                    'response' => $this->fallbackProductResponse($products, $externalResults),
                    'external_results' => $externalResults,
                    'products' => $products,
                    'model' => $model,
                    'model_unavailable' => true,
                ]);
            }

            app(MeanlyAnalyticsService::class)->track('ai.chat.failed', [
                'message_length' => mb_strlen((string) $message),
                'catalog_candidates' => count($catalog),
                'products_count' => count($products),
                'external_results_count' => count($externalResults),
                'model' => $model,
                'upstream_status' => $response->status(),
            ], [
                'event_type' => 'ai',
                'surface' => 'ai',
                'severity' => 'error',
                'status_code' => 500,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Ошибка Ollama: ' . $response->body()
            ], 500);

        } catch (\Exception $e) {
            if ($products !== []) {
                app(MeanlyAnalyticsService::class)->trackException($e, 'ai.chat.fallback_exception', [
                    'message_length' => mb_strlen((string) $message),
                    'catalog_candidates' => count($catalog),
                    'products_count' => count($products),
                    'external_results_count' => count($externalResults),
                    'model' => $model,
                    'model_unavailable' => true,
                ], [
                    'event_type' => 'ai',
                    'surface' => 'ai',
                    'severity' => 'warning',
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);

                return response()->json([
                    'success' => true,
                    'response' => $this->fallbackProductResponse($products, $externalResults),
                    'external_results' => $externalResults,
                    'products' => $products,
                    'model' => $model,
                    'model_unavailable' => true,
                ]);
            }

            app(MeanlyAnalyticsService::class)->trackException($e, 'ai.chat.exception', [
                'message_length' => mb_strlen((string) $message),
                'catalog_candidates' => count($catalog),
                'products_count' => count($products),
                'external_results_count' => count($externalResults),
                'model' => $model,
            ], [
                'event_type' => 'ai',
                'surface' => 'ai',
                'severity' => 'error',
                'status_code' => 500,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json([
                'success' => false,
                'error' => "Не удалось связаться с локальным ИИ-сервером Ollama. Убедитесь, что Ollama запущен и загружена модель {$model}. Детали ошибки: " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $catalog
     * @return array<int, array<string, mixed>>
     */
    private function productSuggestions(array $catalog): array
    {
        return collect($catalog)
            ->take(6)
            ->map(fn (array $item): array => [
                'name' => (string) ($item['name'] ?? ''),
                'brand' => (string) ($item['brand'] ?? 'Meanly'),
                'region' => (string) ($item['region'] ?? 'global'),
                'category' => (string) ($item['category'] ?? ''),
                'price' => (string) ($item['price'] ?? 'Скоро в продаже'),
                'availability' => (string) ($item['availability'] ?? 'catalog_only'),
                'cta' => (string) ($item['cta'] ?? 'Открыть товар'),
                'url' => (string) ($item['url'] ?? '#'),
                'is_grouped' => (bool) ($item['is_grouped'] ?? false),
                'variant_count' => (string) ($item['variant_count'] ?? '1'),
                'region_count' => (string) ($item['region_count'] ?? '0'),
                'nominal_count' => (string) ($item['nominal_count'] ?? '0'),
            ])
            ->filter(fn (array $item): bool => $item['name'] !== '' && $item['url'] !== '#')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     */
    private function ensureProductSuggestionsInResponse(string $response, array $products, array $externalResults = []): string
    {
        if ($products === []) {
            return $response;
        }

        $normalized = $this->normalizeChatText($response);
        $alreadyLinked = str_contains($response, '](');
        $claimsNoProduct = str_contains($normalized, 'нет в каталоге')
            || str_contains($normalized, 'не нахожу')
            || str_contains($normalized, 'не найден')
            || str_contains($normalized, 'нет товара');

        if (! $alreadyLinked || $claimsNoProduct) {
            return $this->fallbackProductResponse($products, $externalResults);
        }

        return $response;
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     */
    private function fallbackProductResponse(array $products, array $externalResults = []): string
    {
        $externalSummary = '';
        if ($externalResults !== []) {
            $first = $externalResults[0];
            $installPrice = $first['install_price_label'] ?? ('Установка: '.$first['price']);
            $note = $first['monetization_note'] ?? 'Подписки и покупки внутри приложения могут оплачиваться отдельно.';
            $externalSummary = "По App Store нашёл {$first['name']} для региона {$first['country']}: {$installPrice}. {$note}\n\n";
        }

        $lines = collect($products)
            ->take(2)
            ->map(function (array $product): string {
                $isGrouped = (bool) ($product['is_grouped'] ?? false);
                $note = $isGrouped
                    ? "групповой товар: выберите регион и номинал на странице"
                    : ($product['availability'] === 'active_offer'
                        ? $product['price']
                        : 'есть в каталоге, активный оффер появится позже');

                return "- [{$product['name']}]({$product['url']}) — {$product['region']}, {$note}";
            })
            ->implode("\n");

        return "{$externalSummary}Чтобы оплатить подписку или покупки внутри приложения, обычно нужен баланс Apple ID того же региона. Поэтому даю не отдельный номинал, а групповой товар Meanly: внутри можно выбрать страну и номинал.\n{$lines}";
    }

    private function catalogCardsForMessage(string $message, CanonicalStorefrontHomepageService $homepageService)
    {
        $cards = collect();

        foreach ($this->chatCatalogQueries($message) as $query) {
            $cards = $cards->concat($homepageService->storefrontReadyCards($query, 120));
        }

        return $cards
            ->unique('slug')
            ->sortByDesc(fn (array $card): int => $this->chatCatalogScore($card, $message))
            ->take(60)
            ->values();
    }

    private function isAppleGiftCardCard(array $card): bool
    {
        $haystack = $this->normalizeChatText(implode(' ', array_filter([
            $card['name'] ?? null,
            $card['brand'] ?? null,
            $card['product_family'] ?? null,
        ])));

        return str_contains($haystack, 'apple')
            || str_contains($haystack, 'itunes')
            || str_contains($haystack, 'app store');
    }

    /**
     * @return array<int, string>
     */
    private function chatCatalogQueries(string $message): array
    {
        $normalized = $this->normalizeChatText($message);
        $tokens = preg_split('/\s+/', $normalized) ?: [];
        $stopWords = [
            'а', 'ах', 'в', 'во', 'вот', 'вы', 'да', 'дай', 'дайте', 'даже', 'для', 'если', 'же', 'и',
            'ка', 'как', 'ко', 'мне', 'можно', 'на', 'найди', 'нет', 'нужен', 'нужна', 'нужно', 'о',
            'от', 'по', 'покажи', 'подбери', 'про', 'с', 'ссылку', 'товар', 'товары', 'у', 'хочу',
            'что', 'this', 'show', 'find', 'give', 'link', 'for', 'the', 'please', 'me', 'with',
            'without', 'available', 'availability', 'stock', 'out', 'of', 'in',
        ];
        $keywords = collect($tokens)
            ->filter(fn (string $token): bool => mb_strlen($token) > 1 && ! in_array($token, $stopWords, true))
            ->values();

        $queries = collect();
        $keywordsQuery = $keywords->implode(' ');

        if ($keywordsQuery !== '') {
            $queries->push($keywordsQuery);
        }

        $region = $this->chatRegionQuery($normalized);

        if (str_contains($normalized, 'apple')) {
            $appleQueries = [
                'apple',
                'itunes',
                'app store',
                'apple app store',
                'apple app store itunes',
            ];

            foreach ($appleQueries as $query) {
                $queries->push($region ? "{$query} {$region}" : $query);
            }
        }

        foreach (['steam', 'playstation', 'psn', 'xbox', 'spotify', 'google play', 'amazon'] as $brand) {
            if (str_contains($normalized, $brand)) {
                $queries->push($region ? "{$brand} {$region}" : $brand);
            }
        }

        if ($region) {
            $queries->push($region);
        }

        $queries->push($message);

        return $queries
            ->map(fn (string $query): string => trim($query))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function chatRegionQuery(string $normalized): ?string
    {
        return match (true) {
            str_contains($normalized, 'turkey'),
            str_contains($normalized, 'turkiye'),
            str_contains($normalized, 'турц'),
            preg_match('/\btr\b/', $normalized) === 1 => 'turkey',
            str_contains($normalized, 'united states'),
            str_contains($normalized, 'usa'),
            str_contains($normalized, 'сша'),
            preg_match('/\bus\b/', $normalized) === 1 => 'united states',
            str_contains($normalized, 'uae'),
            str_contains($normalized, 'оаэ') => 'uae',
            default => null,
        };
    }

    private function chatCatalogScore(array $card, string $message): int
    {
        $normalized = $this->normalizeChatText($message);
        $haystack = $this->normalizeChatText(implode(' ', array_filter([
            $card['name'] ?? null,
            $card['brand'] ?? null,
            $card['region'] ?? null,
            $card['category_label'] ?? null,
        ])));
        $score = (bool) ($card['has_selected_offer'] ?? false) ? 8 : 0;

        foreach (preg_split('/\s+/', $normalized) ?: [] as $token) {
            if (mb_strlen($token) > 2 && str_contains($haystack, $token)) {
                $score += 10;
            }
        }

        if (str_contains($normalized, 'apple') && (str_contains($haystack, 'itunes') || str_contains($haystack, 'app store'))) {
            $score += 35;
        }

        if (($region = $this->chatRegionQuery($normalized)) && str_contains($haystack, $region)) {
            $score += 30;
        }

        return $score;
    }

    private function normalizeChatText(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace(['ё', 'ı', 'İ'], ['е', 'i', 'i'], $value);
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?: '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?: '');
    }

    private function catalogUrlForPrompt(string $url): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? $url;

        return isset($parts['query']) ? "{$path}?{$parts['query']}" : $path;
    }

    /**
     * Строит изолированный промпт для Meanly AI.
     */
    private function buildSystemPrompt(array $catalog, string $userMessage, array $chatHistory, array $externalResults = []): string
    {
        $catalogJson = json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $externalJson = json_encode($externalResults, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        $historyText = "";
        foreach ($chatHistory as $msg) {
            $role = ($msg['role'] ?? 'user') === 'user' ? 'Пользователь' : 'Meanly AI';
            $historyText .= "{$role}: " . ($msg['content'] ?? '') . "\n";
        }

        return <<<EOT
Ты — Meanly AI, интеллектуальный ИИ-ассистент цифрового маркетплейса Meanly.
Твоя единственная цель — помочь пользователю подобрать товар ИЗ НАШЕГО КАТАЛОГА.

ДОСТУПНЫЙ КАТАЛОГ ТОВАРОВ (в формате JSON):
{$catalogJson}

ВНЕШНИЕ APP STORE ФАКТЫ (если есть, в формате JSON):
{$externalJson}

КРИТИЧЕСКИЕ ПРАВИЛА ОБЩЕНИЯ:
1. Отвечай дружелюбно, профессионально, кратко и только на РУССКОМ языке.
2. Сопоставляй запрос пользователя с товарами в каталоге. Обращай внимание на бренд, регион (например, US, TR, global) и цену.
3. НИКОГДА НЕ ПИШИ ВНЕШНИЕ ССЫЛКИ И ВНЕШНИЕ ДОМЕНЫ (такие как www.example.com, playstation.com и т.д.).
4. При предложении товара ОБЯЗАТЕЛЬНО вставляй ссылку на него СТРОГО в формате Markdown: [Название товара](url_из_каталога).
5. Ссылку (URL) бери СТРОГО ИЗ ПОЛЯ "url" в JSON каталога! Например, если у товара поле "url" равно "/catalog/products/psn-70-usd-us", то ссылка в ответе должна быть строго: [PlayStation Network 70 USD](/catalog/products/psn-70-usd-us).
6. Если товар есть в JSON каталоге, но availability = "catalog_only" или price = "Скоро в продаже", НЕ говори, что товара нет. Скажи, что он найден в каталоге, но сейчас без активного оффера, и дай ссылку "Открыть товар".
7. Если пользователь пишет "Apple ID", понимай это как Apple App Store / iTunes gift card.
8. Если есть внешние App Store факты, различай install_price и подписку: price/install_price из Apple Search API — это цена установки приложения, НЕ обязательно цена подписки. Если monetization_note говорит о подписке/IAP, обязательно объясни это.
9. Для Apple Music, Spotify, YouTube, Netflix и похожих сервисов: если App Store показывает Free, говори "приложение бесплатно скачать, подписка оплачивается отдельно", а не "подписка бесплатная".
10. Если в каталоге есть grouped товар (is_grouped=true), рекомендуй именно его одной ссылкой: это страница выбора региона и номинала. Не перечисляй каждый номинал как отдельный товар.
11. Объясняй связку: "цена установки/подписки в App Store" → "для оплаты нужен баланс Apple ID региона" → "на Meanly открываем групповой товар и выбираем регион/номинал".
12. Не выдумывай товары и ссылки, которых нет в JSON каталоге. Только если JSON каталог пустой, вежливо ответь: "К сожалению, этого товара сейчас нет в каталоге."
13. Пиши структурированно, используй списки, абзацы и эмодзи (🎮, 🔑, 💳).

ИСТОРИЯ ДИАЛОГА:
{$historyText}
Пользователь: {$userMessage}
Meanly AI:
EOT;
    }
}
