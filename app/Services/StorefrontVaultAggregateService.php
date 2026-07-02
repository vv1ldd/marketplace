<?php

namespace App\Services;

use App\Models\Architecture\ExecutionRecord;
use App\Models\Architecture\OfferSnapshot;
use App\Models\CanonicalProductIdentity;
use App\Models\LegalEntity;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StorefrontVaultAggregateService
{
    public function __construct(
        private readonly CanonicalCategoryResolver $categoryResolver,
        private readonly StorefrontDecisionService $decisions,
    ) {}

    /**
     * @param  array<string, mixed>  $identity
     * @return array<string, mixed>
     */
    public function aggregate(array $identity, ?User $user, string $entityAddress, Collection $orders): array
    {
        return [
            'identity' => $this->identitySummary($identity, $entityAddress),
            'balances' => $this->balances($user),
            'inventory' => $this->inventory($orders),
            'executions' => $this->executions($orders),
        ];
    }

    /**
     * @param  array<string, mixed>  $identity
     * @return array<string, mixed>
     */
    private function identitySummary(array $identity, string $entityAddress): array
    {
        $username = trim((string) ($identity['username'] ?? $identity['display_alias'] ?? ''));
        if ($username !== '' && ! str_starts_with($username, '@')) {
            $username = '@'.$username;
        }

        return [
            'username' => $username !== '' ? $username : null,
            'status' => filled($entityAddress) ? 'durable' : 'pending',
            'vault_key_ref' => $this->shortenVaultKey($entityAddress),
            'entity_l1_address' => $entityAddress,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function balances(?User $user): array
    {
        if (! $user) {
            return [
                'partner_credit' => 0.0,
                'currency' => 'USD',
            ];
        }

        $entity = LegalEntity::query()
            ->where('user_id', $user->id)
            ->orderByDesc('available_balance')
            ->first();

        if (! $entity) {
            return [
                'partner_credit' => 0.0,
                'currency' => 'USD',
            ];
        }

        return [
            'partner_credit' => (float) $entity->available_balance,
            'currency' => strtoupper((string) ($entity->currency ?: 'RUB')),
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array<int, array<string, mixed>>
     */
    private function inventory(Collection $orders): array
    {
        $items = [];

        foreach ($orders as $order) {
            $order->loadMissing(['items.game']);
            $decision = $this->decisions->orderSafeDecision($order);
            if (! ($decision['paid'] ?? false)) {
                continue;
            }

            $safe = (array) data_get($order->info, 'order_safe', []);
            $isRevealed = filled(data_get($safe, 'scratch_proof')) || filled(data_get($safe, 'opened_at'));

            foreach ($order->items as $item) {
                if (! $this->isInventoryEligible($order, $item, $decision)) {
                    continue;
                }

                $meta = $this->resolveInventoryMeta($order, $item);
                $items[] = [
                    'id' => 'ent_'.$item->id,
                    'order_uuid' => $order->uuid,
                    'order_item_id' => $item->id,
                    'brand' => $meta['brand'],
                    'category_slug' => $meta['category_slug'],
                    'intent_key' => $meta['intent_key'],
                    'denomination' => $meta['denomination'],
                    'currency' => $meta['currency'],
                    'region' => $meta['region'],
                    'is_revealed' => $isRevealed,
                    'activation_url' => $meta['activation_url'],
                    'safe_url' => $decision['links']['safe'] ?? null,
                ];
            }
        }

        return array_values($items);
    }

    /**
     * @param  array<string, mixed>  $decision
     */
    private function isInventoryEligible(Order $order, OrderItems $item, array $decision): bool
    {
        if ($decision['ready'] ?? false) {
            return true;
        }

        $executionId = data_get($order->info, 'order_safe.execution_record_id');
        if (filled($executionId)) {
            $state = ExecutionRecord::query()->whereKey($executionId)->value('state');

            return $state === ExecutionRecord::STATE_ISSUED;
        }

        return filled($item->original_code);
    }

    /**
     * @return array{brand: string, category_slug: string, intent_key: string|null, denomination: string|null, currency: string|null, region: string|null, activation_url: string}
     */
    private function resolveInventoryMeta(Order $order, OrderItems $item): array
    {
        $snapshotId = data_get($order->info, 'order_safe.offer_snapshot_id');
        $identity = null;

        if (filled($snapshotId)) {
            $snapshot = OfferSnapshot::query()->find($snapshotId);
            if ($snapshot?->canonical_product_identity_id) {
                $identity = CanonicalProductIdentity::query()->find($snapshot->canonical_product_identity_id);
            }
        }

        /** @var Product|null $product */
        $product = $item->relationLoaded('game') ? $item->game : $item->game()->with('brand')->first();

        $brand = (string) ($identity?->brand ?: $product?->brand?->name ?: $product?->vendor ?: 'Digital good');
        $categorySlug = (string) ($identity?->discovery_intent ?: ($product
            ? $this->categoryResolver->forProduct($product)
            : 'gift_cards'));
        $intentKey = $this->categoryResolver->discoveryIntentKey($categorySlug);
        $denomination = $identity?->face_value !== null
            ? number_format((float) $identity->face_value, 2, '.', '')
            : ($item->nominal_amount !== null ? number_format((float) $item->nominal_amount, 2, '.', '') : null);
        $currency = strtoupper((string) ($identity?->face_value_currency ?: $item->nominal_currency ?: $order->currency ?: 'USD'));
        $region = strtoupper((string) ($identity?->region ?: data_get($product?->data, 'region', 'GLOBAL')));
        $activationSlug = Str::slug($brand) ?: 'digital-good';

        return [
            'brand' => $brand,
            'category_slug' => $categorySlug,
            'intent_key' => $intentKey,
            'denomination' => $denomination,
            'currency' => $currency,
            'region' => $region !== '' ? $region : 'GLOBAL',
            'activation_url' => '/docs/activation/'.$activationSlug,
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array<int, array<string, mixed>>
     */
    private function executions(Collection $orders): array
    {
        $orderIds = $orders->pluck('id')->filter()->values();
        if ($orderIds->isEmpty()) {
            return [];
        }

        return ExecutionRecord::query()
            ->with(['entitlement', 'order'])
            ->whereIn('order_id', $orderIds)
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->map(fn (ExecutionRecord $record): array => [
                'id' => $record->id,
                'intent_id' => $record->intent_id,
                'title' => $this->executionTitle($record),
                'state' => $record->state,
                'order_uuid' => $record->order?->uuid,
                'created_at' => $record->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function executionTitle(ExecutionRecord $record): string
    {
        $identity = $record->entitlement;
        if ($identity instanceof CanonicalProductIdentity) {
            $brand = $identity->brand ?: 'Digital good';
            $denomination = $identity->face_value !== null
                ? number_format((float) $identity->face_value, 2, '.', '').' '.strtoupper((string) $identity->face_value_currency)
                : '';
            $region = filled($identity->region) ? ' ('.strtoupper((string) $identity->region).')' : '';

            return trim("{$brand} {$denomination}{$region}");
        }

        return 'Digital fulfillment';
    }

    private function shortenVaultKey(string $address): string
    {
        $normalized = trim($address);
        if ($normalized === '') {
            return '';
        }

        if (strlen($normalized) <= 12) {
            return $normalized;
        }

        return substr($normalized, 0, 6).'...'.substr($normalized, -4);
    }
}
