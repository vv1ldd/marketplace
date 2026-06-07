<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Product;
use App\Models\StorefrontFavorite;
use App\Services\CanonicalStorefrontHomepageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StorefrontPersonalizationController extends Controller
{
    public function home(Request $request): JsonResponse
    {
        $identity = $this->identity($request);
        $address = $identity['entity_l1_address'];

        $purchaseCategories = $this->purchaseCategories($address);
        $favoriteCategories = $this->favoriteCategories($address);
        $shortcuts = $purchaseCategories
            ->merge($favoriteCategories)
            ->groupBy('slug')
            ->map(function (Collection $items, string $slug): array {
                $first = $items->first();

                return [
                    'type' => 'storefront_category_shortcut',
                    'slug' => $slug,
                    'label' => $first['label'] ?? Str::headline($slug),
                    'href' => '/catalog/'.$slug,
                    'signals' => $items->pluck('signal')->unique()->values()->all(),
                    'score' => $items->sum('score'),
                    'actions' => [
                        'allowed_actions' => ['VIEW'],
                        'blocked_actions' => [],
                        'next_action' => 'VIEW',
                        'blocking_reason' => null,
                    ],
                ];
            })
            ->sortByDesc('score')
            ->take(8)
            ->values();

        return response()->json([
            'contract' => [
                'name' => 'storefront-personalized-home',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
                'dto_boundary' => 'transitions_not_conditions',
            ],
            'identity' => [
                'entity_l1_address' => $address,
            ],
            'category_shortcuts' => $shortcuts,
            'actions' => [
                'allowed_actions' => ['VIEW_PERSONALIZED_SHORTCUTS'],
                'blocked_actions' => [],
                'next_action' => 'VIEW_PERSONALIZED_SHORTCUTS',
                'blocking_reason' => null,
            ],
        ]);
    }

    public function toggleFavorite(Request $request, CanonicalStorefrontHomepageService $homepage): JsonResponse
    {
        $identity = $this->identity($request);
        $address = $identity['entity_l1_address'];
        $data = $request->validate([
            'product_slug' => ['required', 'string', 'max:255'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'category_slug' => ['nullable', 'string', 'max:255'],
            'category_label' => ['nullable', 'string', 'max:255'],
        ]);

        $card = $this->productCard((string) $data['product_slug'], $homepage);
        $categorySlug = (string) ($data['category_slug'] ?? data_get($card, 'category', ''));
        $categoryLabel = (string) ($data['category_label'] ?? data_get($card, 'category_label', $categorySlug));
        $favorite = StorefrontFavorite::query()
            ->where('entity_l1_address', $address)
            ->where('product_slug', $data['product_slug'])
            ->first();

        if ($favorite instanceof StorefrontFavorite) {
            $favorite->delete();
            $isFavorite = false;
        } else {
            StorefrontFavorite::query()->create([
                'entity_l1_address' => $address,
                'product_slug' => $data['product_slug'],
                'product_name' => (string) ($data['product_name'] ?? data_get($card, 'name', $data['product_slug'])),
                'category_slug' => $categorySlug !== '' ? $categorySlug : null,
                'category_label' => $categoryLabel !== '' ? $categoryLabel : null,
                'metadata' => [
                    'source' => 'storefront_projection',
                ],
            ]);
            $isFavorite = true;
        }

        return response()->json([
            'contract' => [
                'name' => 'storefront-favorite-toggle',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
                'dto_boundary' => 'transitions_not_conditions',
            ],
            'favorite' => $isFavorite,
            'product_slug' => $data['product_slug'],
            'actions' => [
                'allowed_actions' => ['TOGGLE_FAVORITE'],
                'blocked_actions' => [],
                'next_action' => 'TOGGLE_FAVORITE',
                'blocking_reason' => null,
            ],
        ]);
    }

    /**
     * @return array{entity_l1_address: string}
     */
    private function identity(Request $request): array
    {
        $identity = (array) $request->attributes->get('storefront_identity', []);
        $address = strtolower((string) data_get($identity, 'entity_l1_address'));
        abort_if($address === '', 403);

        return ['entity_l1_address' => $address];
    }

    /**
     * @return Collection<int, array{slug: string, label: string, signal: string, score: int}>
     */
    private function purchaseCategories(string $address): Collection
    {
        return Order::query()
            ->with('items.game')
            ->latest('id')
            ->limit(100)
            ->get()
            ->filter(fn (Order $order): bool => in_array($address, array_filter([
                strtolower((string) data_get($order->client_info, 'buyer_l1_address')),
                strtolower((string) data_get($order->client_info, 'simple_l1.entity_l1_address')),
                strtolower((string) data_get($order->info, 'simple_l1.entity_l1_address')),
            ]), true))
            ->flatMap(fn (Order $order): Collection => $order->items->map(function ($item): ?array {
                $product = $item->game;
                if (! $product instanceof Product) {
                    return null;
                }

                $slug = (string) ($product->canonical_category ?: $product->category);
                if ($slug === '') {
                    return null;
                }

                return [
                    'slug' => $slug,
                    'label' => (string) ($product->canonical_category ?: $product->category),
                    'signal' => 'purchase',
                    'score' => 3,
                ];
            }))
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array{slug: string, label: string, signal: string, score: int}>
     */
    private function favoriteCategories(string $address): Collection
    {
        return StorefrontFavorite::query()
            ->where('entity_l1_address', $address)
            ->whereNotNull('category_slug')
            ->latest('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (StorefrontFavorite $favorite): array => [
                'slug' => (string) $favorite->category_slug,
                'label' => (string) ($favorite->category_label ?: $favorite->category_slug),
                'signal' => 'favorite',
                'score' => 2,
            ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function productCard(string $slug, CanonicalStorefrontHomepageService $homepage): ?array
    {
        $product = Product::query()->where('slug', $slug)->first();
        if ($product instanceof Product) {
            return [
                'name' => $product->name,
                'category' => $product->canonical_category ?: $product->category,
                'category_label' => $product->canonical_category ?: $product->category,
            ];
        }

        return $homepage->storefrontReadyCards($slug)->firstWhere('slug', $slug);
    }
}
