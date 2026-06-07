<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StorefrontCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $category = is_array($this->resource) ? $this->resource : [];

        return [
            'type' => 'storefront_category',
            'slug' => (string) (data_get($category, 'slug') ?? data_get($category, 'value')),
            'name' => (string) (data_get($category, 'name') ?? data_get($category, 'label')),
            'label' => (string) (data_get($category, 'label') ?? data_get($category, 'name')),
            'count' => (int) data_get($category, 'count', 0),
            'seller_offer_count' => (int) data_get($category, 'seller_offer_count', 0),
            'provider_count' => (int) data_get($category, 'provider_count', 0),
            'links' => [
                'self' => data_get($category, 'url'),
                'machine_readable' => data_get($category, 'machine_readable_url'),
            ],
            'actions' => [
                'allowed_actions' => ['VIEW'],
                'blocked_actions' => [],
                'next_action' => 'VIEW',
                'blocking_reason' => null,
            ],
        ];
    }
}
