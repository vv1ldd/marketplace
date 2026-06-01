<?php

namespace App\Http\Controllers;

use App\Services\AppStoreLookupService;
use App\Services\CanonicalStorefrontHomepageService;
use App\Services\Llm\LlmProviderManager;
use App\Services\MeanlyAnalyticsService;
use Illuminate\Http\Request;

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
        LlmProviderManager $llm,
    )
    {
        $message = $request->input('message');
        $chatHistory = $request->input('history', []); // Ожидаем массив ['role' => 'user|assistant', 'content' => '...']

        if (empty(trim((string) $message))) {
            return response()->json([
                'success' => false,
                'error' => __('runtime.chat.empty_request')
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
                'price' => $offerPrice !== null ? ($offerPrice.' ₽') : __('runtime.chat.coming_soon'),
                'availability' => $card['has_selected_offer'] ? 'active_offer' : 'catalog_only',
                'cta' => $card['cta_label'] ?? ($card['has_selected_offer'] ? __('runtime.chat.buy') : __('runtime.chat.open_product')),
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

        $prompt = $this->buildSystemPrompt($catalog, $message, $chatHistory, $externalResults);
        $products = $this->productSuggestions($catalog);
        $response = $llm->generateText($prompt, [
            'timeout' => 60,
            'temperature' => 0.2,
            'max_tokens' => 900,
            'system' => 'You are Meanly AI, a concise marketplace shopping assistant.',
        ]);
        $model = trim($response->provider.':'.($response->model ?? ''), ':');

        if ($response->ok) {
            $answer = $this->ensureProductSuggestionsInResponse($response->text, $products, $externalResults);

            app(MeanlyAnalyticsService::class)->track('ai.chat.completed', [
                'message_length' => mb_strlen((string) $message),
                'catalog_candidates' => count($catalog),
                'products_count' => count($products),
                'external_results_count' => count($externalResults),
                'model' => $model,
                'provider' => $response->provider,
                'fallback_used' => $response->fallbackUsed,
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
                'model' => $model,
                'provider' => $response->provider,
                'fallback_used' => $response->fallbackUsed,
            ]);
        }

        if ($products !== []) {
            app(MeanlyAnalyticsService::class)->track('ai.chat.fallback', [
                'message_length' => mb_strlen((string) $message),
                'catalog_candidates' => count($catalog),
                'products_count' => count($products),
                'external_results_count' => count($externalResults),
                'model' => $model,
                'provider' => $response->provider,
                'model_unavailable' => true,
                'error' => $response->error,
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
            'provider' => $response->provider,
            'error' => $response->error,
        ], [
            'event_type' => 'ai',
            'surface' => 'ai',
            'severity' => 'error',
            'status_code' => 500,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return response()->json([
            'success' => false,
            'error' => __('runtime.chat.llm_error', ['error' => $response->error]),
        ], 500);
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
                'price' => (string) ($item['price'] ?? __('runtime.chat.coming_soon')),
                'availability' => (string) ($item['availability'] ?? 'catalog_only'),
                'cta' => (string) ($item['cta'] ?? __('runtime.chat.open_product')),
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
            $installPrice = $first['install_price_label'] ?? (__('runtime.chat.install_price', ['price' => $first['price']]));
            $note = $first['monetization_note'] ?? __('runtime.chat.iap_note');
            $externalSummary = __('runtime.chat.external_summary', ['name' => $first['name'], 'country' => $first['country'], 'price' => $installPrice, 'note' => $note]);
        }

        $lines = collect($products)
            ->take(2)
            ->map(function (array $product): string {
                $isGrouped = (bool) ($product['is_grouped'] ?? false);
                $note = $isGrouped
                    ? __('runtime.chat.grouped_product_hint')
                    : ($product['availability'] === 'active_offer'
                        ? $product['price']
                        : __('runtime.chat.catalog_only_hint'));

                return "- [{$product['name']}]({$product['url']}) — {$product['region']}, {$note}";
            })
            ->implode("\n");

        return $externalSummary.__('runtime.chat.apple_balance_summary', ['lines' => $lines]);
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

    private function buildSystemPrompt(array $catalog, string $userMessage, array $chatHistory, array $externalResults = []): string
    {
        $catalogJson = json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $externalJson = json_encode($externalResults, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        $historyText = "";
        foreach ($chatHistory as $msg) {
            $role = ($msg['role'] ?? 'user') === 'user' ? __('runtime.chat.user_role') : 'Meanly AI';
            $historyText .= "{$role}: " . ($msg['content'] ?? '') . "\n";
        }

        return __('runtime.chat.system_prompt', [
            'catalog' => $catalogJson,
            'external' => $externalJson,
            'history' => $historyText,
            'message' => $userMessage,
        ]);
    }
}
