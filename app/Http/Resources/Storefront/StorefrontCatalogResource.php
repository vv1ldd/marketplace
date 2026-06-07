<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class StorefrontCatalogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = is_array($this->resource) ? $this->resource : [];
        $browseProducts = data_get($data, 'browse_products');

        return [
            'contract' => [
                'name' => 'storefront-catalog',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
                'dto_boundary' => 'transitions_not_conditions',
            ],
            'surface' => (array) data_get($data, 'surface', []),
            'query' => (string) data_get($data, 'query', ''),
            'quick_chips' => $this->values(data_get($data, 'quick_chips')),
            'products' => [
                'featured' => StorefrontProductResource::collection($this->values(data_get($data, 'featured_products'))),
                'provider_network' => StorefrontProductResource::collection($this->values(data_get($data, 'provider_network_products'))),
                'groups' => StorefrontProductResource::collection($this->values(data_get($data, 'product_groups'))),
                'browse' => StorefrontProductResource::collection($this->browseItems($browseProducts)),
            ],
            'product_groups' => StorefrontProductResource::collection($this->values(data_get($data, 'product_groups'))),
            'categories' => StorefrontCategoryResource::collection($this->values(data_get($data, 'categories'))),
            'brands' => $this->values(data_get($data, 'brands'))->map(fn (array $brand): array => [
                'type' => 'storefront_brand',
                'name' => (string) data_get($brand, 'name'),
                'count' => (int) data_get($brand, 'count', 0),
                'seller_offer_count' => (int) data_get($brand, 'seller_offer_count', 0),
                'links' => [
                    'self' => data_get($brand, 'url'),
                ],
                'actions' => [
                    'allowed_actions' => ['VIEW'],
                    'blocked_actions' => [],
                    'next_action' => 'VIEW',
                    'blocking_reason' => null,
                ],
            ])->values(),
            'stats' => (array) data_get($data, 'stats', []),
            'pagination' => $this->pagination($browseProducts),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function values(mixed $value): Collection
    {
        if ($value instanceof Collection) {
            return $value->values();
        }

        if (is_array($value)) {
            return collect($value)->values();
        }

        return collect();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function browseItems(mixed $value): Collection
    {
        if ($value instanceof LengthAwarePaginator) {
            return collect($value->items())->values();
        }

        return $this->values($value);
    }

    /**
     * @return array<string, int>|null
     */
    private function pagination(mixed $value): ?array
    {
        if (! $value instanceof LengthAwarePaginator) {
            return null;
        }

        return [
            'current_page' => $value->currentPage(),
            'per_page' => $value->perPage(),
            'total' => $value->total(),
            'last_page' => $value->lastPage(),
        ];
    }
}
