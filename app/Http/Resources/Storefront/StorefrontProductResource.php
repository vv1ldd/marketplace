<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StorefrontProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $card = is_array($this->resource) ? $this->resource : [];
        $selectedOffer = data_get($card, 'selected_offer');
        $hasSelectedOffer = is_array($selectedOffer);
        $actions = $this->actionContract($hasSelectedOffer);

        return [
            'type' => 'storefront_product',
            'id' => (string) data_get($card, 'id'),
            'slug' => (string) data_get($card, 'slug'),
            'name' => (string) data_get($card, 'name'),
            'category' => [
                'slug' => (string) data_get($card, 'category'),
                'label' => (string) data_get($card, 'category_label'),
            ],
            'brand' => data_get($card, 'brand'),
            'product_family' => data_get($card, 'product_family'),
            'face_value' => data_get($card, 'face_value'),
            'face_value_currency' => data_get($card, 'face_value_currency'),
            'region' => data_get($card, 'region', 'global'),
            'status_label' => data_get($card, 'status_label'),
            'variant_group' => (array) data_get($card, 'variant_group', []),
            'seller_offer_count' => (int) data_get($card, 'seller_offer_count', 0),
            'provider_count' => (int) data_get($card, 'provider_count', 0),
            'selected_offer' => $hasSelectedOffer ? [
                'product_id' => (string) data_get($selectedOffer, 'product_id'),
                'name' => data_get($selectedOffer, 'name'),
                'seller_name' => data_get($selectedOffer, 'seller.name'),
                'price' => data_get($selectedOffer, 'price'),
                'availability' => data_get($selectedOffer, 'availability'),
            ] : null,
            'availability' => [
                'available' => $hasSelectedOffer,
                'checkout_allowed' => in_array('CHECKOUT', $actions['allowed_actions'], true),
                'source' => $hasSelectedOffer ? 'marketplace_backend' : 'provider_network',
            ],
            'actions' => $actions,
            'links' => [
                'self' => data_get($card, 'url'),
                'machine_readable' => data_get($card, 'machine_readable_at'),
            ],
        ];
    }

    /**
     * @return array{allowed_actions: array<int, string>, blocked_actions: array<int, string>, next_action: string, blocking_reason: string|null}
     */
    private function actionContract(bool $checkoutAllowed): array
    {
        if ($checkoutAllowed) {
            return [
                'allowed_actions' => ['VIEW', 'ADD_TO_CART', 'CHECKOUT'],
                'blocked_actions' => [],
                'next_action' => 'CHECKOUT',
                'blocking_reason' => null,
            ];
        }

        return [
            'allowed_actions' => ['VIEW'],
            'blocked_actions' => ['ADD_TO_CART', 'CHECKOUT'],
            'next_action' => 'VIEW_PROVIDER_NETWORK',
            'blocking_reason' => 'no_selected_offer',
        ];
    }
}
