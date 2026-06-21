<?php

namespace App\Http\Controllers;

use App\Services\AppStoreLookupService;
use App\Services\CanonicalStorefrontHomepageService;
use App\Services\CatalogQueryUnderstandingService;
use App\Services\Llm\LlmProviderManager;
use App\Services\MeanlyAnalyticsService;
use App\Support\StorefrontFrontendRedirect;
use Illuminate\Http\Request;

class StorefrontChatController extends Controller
{
    public function page(Request $request)
    {
        return StorefrontFrontendRedirect::fromRequest($request);
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
        $wholesaleIntent = $this->isWholesaleIntent((string) $message);
        $catalogQuery = $this->catalogQueryForMessage((string) $message, $wholesaleIntent);
        $appStoreIntent = $appStoreLookup->intentFromMessage($catalogQuery);
        $externalResults = $appStoreIntent !== null
            ? $appStoreLookup->search($appStoreIntent['app_query'], $appStoreIntent['region'])
            : [];

        // 1. Получаем релевантные карточки для чата: пользователь пишет свободно,
        // поэтому чистим фразу от служебных слов и добавляем синонимы каталога.
        $cards = $this->catalogCardsForMessage($catalogQuery, $homepageService);

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
            'system' => 'You are Meanly AI. Reply in 1-2 short sentences. Use markdown catalog links only. No technical status notes or long explanations.',
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

            return $this->chatResponse([
                'success' => true,
                'response' => $answer,
                'external_results' => $externalResults,
                'products' => $products,
                'model' => $model,
                'provider' => $response->provider,
                'fallback_used' => $response->fallbackUsed,
            ], $wholesaleIntent, (string) $message);
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

            return $this->chatResponse([
                'success' => true,
                'response' => $this->fallbackProductResponse($products, $externalResults),
                'external_results' => $externalResults,
                'products' => $products,
                'model' => $model,
                'model_unavailable' => true,
            ], $wholesaleIntent, (string) $message);
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
            ->take(3)
            ->map(function (array $product): string {
                if (($product['availability'] ?? '') === 'active_offer' && ($product['price'] ?? '') !== '') {
                    return "- [{$product['name']}]({$product['url']}) — {$product['price']}";
                }

                return "- [{$product['name']}]({$product['url']})";
            })
            ->implode("\n");

        $summaryKey = $this->shouldUseAppleBalanceSummary($products, $externalResults)
            ? 'runtime.chat.apple_balance_summary'
            : 'runtime.chat.catalog_product_summary';

        return $externalSummary.__($summaryKey, ['lines' => $lines]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @param  array<int, array<string, mixed>>  $externalResults
     */
    private function shouldUseAppleBalanceSummary(array $products, array $externalResults): bool
    {
        if ($externalResults !== []) {
            return true;
        }

        return collect($products)->contains(fn (array $product): bool => $this->isAppleGiftCardProduct($product));
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function isAppleGiftCardProduct(array $product): bool
    {
        $haystack = $this->normalizeChatText(implode(' ', array_filter([
            $product['name'] ?? null,
            $product['brand'] ?? null,
            $product['product_family'] ?? null,
        ])));

        return str_contains($haystack, 'apple')
            || str_contains($haystack, 'itunes')
            || str_contains($haystack, 'app store');
    }

    private function catalogCardsForMessage(string $message, CanonicalStorefrontHomepageService $homepageService)
    {
        $understanding = app(CatalogQueryUnderstandingService::class)->understand($message);
        $cards = collect();

        foreach ($this->chatCatalogQueries($message, $understanding) as $query) {
            $cards = $cards->concat($homepageService->storefrontReadyCards($query, 120));
        }

        return $cards
            ->unique('slug')
            ->sortByDesc(fn (array $card): int => $this->chatCatalogScore($card, $understanding))
            ->take(60)
            ->values();
    }

    private function isAppleGiftCardCard(array $card): bool
    {
        return $this->isAppleGiftCardProduct([
            'name' => $card['name'] ?? null,
            'brand' => $card['brand'] ?? null,
            'product_family' => $card['product_family'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $understanding
     * @return array<int, string>
     */
    private function chatCatalogQueries(string $message, array $understanding): array
    {
        $filters = (array) ($understanding['filters'] ?? []);
        $queries = collect([
            $understanding['rewritten_query'] ?? null,
            $understanding['canonical_query'] ?? null,
            $understanding['normalized_query'] ?? null,
        ]);

        $brandSearchTerm = $this->chatBrandSearchTerm(isset($filters['brand']) ? (string) $filters['brand'] : null);
        $region = isset($filters['region']) ? (string) $filters['region'] : null;

        if ($brandSearchTerm !== null) {
            $queries->push($region ? "{$brandSearchTerm} {$region}" : $brandSearchTerm);
        }

        if ($this->isAppleGiftCardBrand(isset($filters['brand']) ? (string) $filters['brand'] : null)) {
            $appleRegion = $region ?? '';
            $queries->push(trim("apple app store itunes gift card {$appleRegion}"));
            $queries->push(trim("itunes app store {$appleRegion}"));
        }

        if ($region) {
            $queries->push($region);
        }

        $queries->push($message);

        return $queries
            ->map(fn (mixed $query): string => trim((string) $query))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function chatBrandSearchTerm(?string $brand): ?string
    {
        if ($brand === null || $brand === '') {
            return null;
        }

        return mb_strtolower($brand);
    }

    /**
     * @param  array<string, mixed>  $understanding
     */
    private function chatCatalogScore(array $card, array $understanding): int
    {
        $filters = (array) ($understanding['filters'] ?? []);
        $normalized = $this->normalizeChatText((string) ($understanding['original_query'] ?? ''));
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

        $region = isset($filters['region']) ? (string) $filters['region'] : null;
        if ($region && $this->cardMatchesRegion($card, $region)) {
            $score += 30;
        } elseif ($region) {
            $score -= 25;
        }

        return $score;
    }

    private function isAppleGiftCardBrand(?string $brand): bool
    {
        if ($brand === null || $brand === '') {
            return false;
        }

        return str_contains($this->normalizeChatText($brand), 'apple');
    }

    private function cardMatchesRegion(array $card, string $region): bool
    {
        $region = $this->normalizeChatText($region);
        $haystack = $this->normalizeChatText(implode(' ', array_filter([
            $card['region'] ?? null,
            data_get($card, 'variant_group.regions.0'),
            ...(array) ($card['variant_group']['regions'] ?? []),
        ])));

        if ($haystack === '' || $region === '') {
            return false;
        }

        if (str_contains($haystack, $region)) {
            return true;
        }

        return match ($region) {
            'turkey' => str_contains($haystack, 'tr') || str_contains($haystack, 'turk'),
            'united states', 'us' => str_contains($haystack, 'us') || str_contains($haystack, 'usa'),
            'united kingdom', 'gb' => str_contains($haystack, 'gb') || str_contains($haystack, 'uk'),
            'austria' => str_contains($haystack, 'at') || str_contains($haystack, 'austria'),
            'indonesia' => str_contains($haystack, 'indonesia') || preg_match('/\bid\b/', $haystack) === 1,
            default => false,
        };
    }

    private function normalizeChatText(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace(['ё', 'ı', 'İ'], ['е', 'i', 'i'], $value);
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?: '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?: '');
    }

    /**
     * @var array<int, string>
     */
    private const WHOLESALE_MARKERS = [
        'оптом',
        'опт',
        'оптов',
        'оптовые',
        'оптовая',
        'оптовый',
        'wholesale',
        'bulk',
        'b2b',
        'large order',
        'volume order',
        'партией',
        'партия',
        'крупным',
        'крупный опт',
    ];

    private function isWholesaleIntent(string $message): bool
    {
        $haystack = mb_strtolower(trim($message));

        foreach (self::WHOLESALE_MARKERS as $marker) {
            if (str_contains($haystack, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function catalogQueryForMessage(string $message, bool $wholesaleIntent): string
    {
        if (! $wholesaleIntent) {
            return $message;
        }

        $query = $message;
        foreach (self::WHOLESALE_MARKERS as $marker) {
            $pattern = '/'.preg_quote($marker, '/').'/iu';
            $query = preg_replace($pattern, ' ', $query) ?? $query;
        }

        $query = trim(preg_replace('/\s+/u', ' ', $query) ?: '');

        return $query !== '' ? $query : $message;
    }

    /**
     * @return array{active: bool, email: string, message: string}|null
     */
    private function wholesalePayload(bool $active, string $message): ?array
    {
        if (! $active) {
            return null;
        }

        $email = (string) config('meanly_storefront.b2b.email', config('acquiring.company.email', 'support@meanly.one'));

        return [
            'active' => true,
            'email' => $email,
            'message' => __(
                'runtime.chat.b2b_wholesale_note',
                ['email' => $email],
                $this->messageLocaleHint($message),
            ),
        ];
    }

    private function messageLocaleHint(string $message): ?string
    {
        return preg_match('/\p{Cyrillic}/u', $message) === 1 ? 'ru' : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function chatResponse(array $payload, bool $wholesaleIntent, string $message): \Illuminate\Http\JsonResponse
    {
        $payload['b2b'] = $this->wholesalePayload($wholesaleIntent, $message);

        return response()->json($payload);
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
