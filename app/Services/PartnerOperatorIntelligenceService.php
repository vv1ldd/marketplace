<?php

namespace App\Services;

use App\Models\ApiApplication;
use App\Models\LegalEntity;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\ProductSalesChannel;
use App\Models\Shop;
use App\Models\SovereignLedger;
use App\Models\SovereignBalanceRequest;
use App\Models\Ticket;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PartnerOperatorIntelligenceService
{
    /**
     * @return array<string, mixed>
     */
    public function stats(LegalEntity $legalEntity, ?array $l1State = null): array
    {
        $l1State ??= app(L1StateService::class)->reconstructBalance($legalEntity);

        $ordersForEntity = Order::query()
            ->whereHas('shop', fn ($query) => $query->where('legal_entity_id', $legalEntity->id));

        $revenue30Days = (float) (DB::table('orders')
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('shops', 'orders.shop_id', '=', 'shops.id')
            ->where('shops.legal_entity_id', $legalEntity->id)
            ->where('orders.created_at', '>=', now()->subDays(30))
            ->where('orders.progress_id', 4)
            ->sum('order_items.price_rub') / 100);

        $margin30Days = (float) (clone $ordersForEntity)
            ->where('created_at', '>=', now()->subDays(30))
            ->where('progress_id', 4)
            ->sum('margin_base');

        $shops = $legalEntity->shops();
        $activeShops = (clone $shops)->where('is_active', true);
        $productsForEntity = Product::query()
            ->whereHas('shop', fn ($query) => $query->where('legal_entity_id', $legalEntity->id));
        $ticketsForEntity = Ticket::query()
            ->whereHas('shop', fn ($query) => $query->where('legal_entity_id', $legalEntity->id));

        return [
            'balance' => $l1State['available_balance'],
            'reserved_balance' => $l1State['reserved_balance'],
            'total_balance' => $l1State['total_balance'],
            'native_balance' => $l1State['native_available_balance'],
            'native_reserved_balance' => $l1State['native_reserved_balance'],
            'native_total_balance' => $l1State['native_total_balance'],
            'integrity_secured' => $l1State['integrity_secured'],
            'channels_count' => (clone $shops)->count(),
            'active_channels' => (clone $activeShops)->count(),
            'sellers_count' => $legalEntity->sellers()->count(),
            'active_sellers_count' => $legalEntity->sellers()->where('is_active', true)->count(),
            'api_applications_count' => ApiApplication::whereHas('shop', fn ($query) => $query->where('legal_entity_id', $legalEntity->id))->count(),
            'active_api_applications_count' => ApiApplication::whereHas('shop', fn ($query) => $query->where('legal_entity_id', $legalEntity->id))->where('is_active', true)->count(),
            'yandex_connected_channels' => (clone $shops)
                ->whereNotNull('business_id')
                ->whereNotNull('campaign_id')
                ->whereNotNull('api_key')
                ->count(),
            'yandex_incomplete_channels' => (clone $activeShops)
                ->where(function ($query) {
                    $query->whereNull('business_id')
                        ->orWhereNull('campaign_id')
                        ->orWhereNull('api_key');
                })
                ->count(),
            'active_orders' => (clone $ordersForEntity)->where('progress_id', '<>', 4)->count(),
            'completed_orders_30_days' => (clone $ordersForEntity)
                ->where('created_at', '>=', now()->subDays(30))
                ->where('progress_id', 4)
                ->count(),
            'problem_orders' => (clone $ordersForEntity)->where('is_problem', true)->where('progress_id', '<>', 4)->count(),
            'revenue_30_days' => $revenue30Days,
            'margin_30_days' => $margin30Days,
            'market_errors_count' => (clone $productsForEntity)->whereNotNull('ym_errors')->count(),
            'products_count' => (clone $productsForEntity)->count(),
            'active_products_count' => (clone $productsForEntity)->where('is_active', true)->count(),
            'open_tickets_count' => (clone $ticketsForEntity)->whereIn('status', ['open', 'new', 'pending'])->count(),
            'failed_purchase_items_count' => OrderItems::whereHas('order.shop', fn ($query) => $query->where('legal_entity_id', $legalEntity->id))
                ->where('purchase_status', 'failed')
                ->count(),
            'pending_purchase_items_count' => OrderItems::whereHas('order.shop', fn ($query) => $query->where('legal_entity_id', $legalEntity->id))
                ->where('purchase_status', 'pending')
                ->count(),
            'available_vouchers_count' => ProductInventory::whereHas('shop', fn ($query) => $query->where('legal_entity_id', $legalEntity->id))
                ->where('status', 'available')
                ->where('is_used', false)
                ->count(),
            'reserved_vouchers_count' => ProductInventory::whereHas('shop', fn ($query) => $query->where('legal_entity_id', $legalEntity->id))
                ->where('status', 'reserved')
                ->count(),
            'low_stock_count' => WarehouseStock::whereHas('warehouse.shop', fn ($query) => $query->where('legal_entity_id', $legalEntity->id))
                ->where('count', '<', 5)
                ->count(),
            'channel_warehouses_count' => Warehouse::whereHas('shop', fn ($query) => $query->where('legal_entity_id', $legalEntity->id))
                ->whereNotNull('channel')
                ->count(),
        ];
    }

    /**
     * @param Collection<int, SovereignBalanceRequest>|null $sovereignRequests
     * @return array<string, mixed>
     */
    public function payload(LegalEntity $legalEntity, ?array $stats = null, ?Collection $sovereignRequests = null): array
    {
        $stats ??= $this->stats($legalEntity);
        $sovereignRequests ??= SovereignBalanceRequest::where('legal_entity_id', $legalEntity->id)->latest()->get();

        $marketErrorProducts = Product::whereHas('shop', fn ($query) => $query->where('legal_entity_id', $legalEntity->id))
            ->whereNotNull('ym_errors')
            ->with('shop')
            ->latest()
            ->limit(5)
            ->get();

        $pendingSovereignRequests = $sovereignRequests->where('status', 'pending')->values();
        $criticalAlerts = $this->criticalAlerts($stats);
        $recommendations = $this->recommendations($stats);
        $this->meterRecommendationVisibility($legalEntity, $recommendations);
        $tokenomics = $this->tokenomics($legalEntity);
        $firstPartyStorefront = $this->firstPartyStorefront($legalEntity);

        return [
            'summary' => [
                'critical_alerts' => $criticalAlerts->count(),
                'trusted_recommendations' => $recommendations->whereIn('trust_level', ['high_trust', 'usable'])->count(),
                'pending_reviews' => $pendingSovereignRequests->count(),
                'failed_publishes' => $marketErrorProducts->count(),
                'team_members' => $stats['sellers_count'],
                'active_team_members' => $stats['active_sellers_count'],
                'active_channels' => $stats['active_channels'],
                'yandex_connected_channels' => $stats['yandex_connected_channels'],
                'api_applications' => $stats['api_applications_count'],
                'token_usage_sl1' => $tokenomics['total_sl1'],
                'recommendation_hit_rate' => $tokenomics['recommendations']['hit_rate'],
            ],
            'critical_alerts' => $criticalAlerts,
            'trusted_recommendations' => $recommendations,
            'pending_reviews' => $pendingSovereignRequests->take(5)->map(fn (SovereignBalanceRequest $request) => [
                'type' => $request->type,
                'amount' => $request->amount,
                'currency' => $request->currency,
                'status' => $request->status,
                'created_at' => $request->created_at?->format('d.m.Y H:i'),
            ])->values(),
            'failed_publishes' => $marketErrorProducts->map(fn (Product $product) => [
                'name' => $product->name,
                'sku' => $product->sku,
                'shop' => $product->shop?->name,
            ])->values(),
            'scorecard' => [
                'gmv' => (float) ($stats['revenue_30_days'] ?? 0),
                'margin' => $stats['margin_30_days'] > 0 ? round((float) $stats['margin_30_days'], 2) : round(((float) ($stats['revenue_30_days'] ?? 0)) * 0.12, 2),
                'aov' => ($stats['completed_orders_30_days'] ?? 0) > 0
                    ? round(((float) ($stats['revenue_30_days'] ?? 0)) / (int) $stats['completed_orders_30_days'], 2)
                    : 0,
                'forecast_accuracy' => $this->forecastAccuracy($stats),
                'policy_effectiveness' => $this->policyEffectiveness($stats),
                'recommendation_trust' => round((float) $recommendations->avg('priority_score'), 2),
                'orders_30_days' => $stats['completed_orders_30_days'],
                'products_active' => $stats['active_products_count'],
                'token_usage_sl1' => $tokenomics['total_sl1'],
                'token_roi' => $tokenomics['roi'],
                'estimated_value_created' => $tokenomics['estimated_value_rub'],
                'recommendation_hit_rate' => $tokenomics['recommendations']['hit_rate'],
            ],
            'health' => [
                'overall_status' => $criticalAlerts->isEmpty() ? 'healthy' : 'attention_required',
                'sync_health' => ($stats['integrity_secured'] ?? false) ? 'secured' : 'needs_sync',
                'feed_freshness' => (($stats['market_errors_count'] ?? 0) === 0) ? 'fresh' : 'degraded',
                'active_channels' => $stats['active_channels'],
                'total_channels' => $stats['channels_count'],
                'team' => [
                    'total' => $stats['sellers_count'],
                    'active' => $stats['active_sellers_count'],
                ],
                'integrations' => [
                    'api_applications' => $stats['api_applications_count'],
                    'active_api_applications' => $stats['active_api_applications_count'],
                    'yandex_connected_channels' => $stats['yandex_connected_channels'],
                    'yandex_incomplete_channels' => $stats['yandex_incomplete_channels'],
                    'channel_warehouses' => $stats['channel_warehouses_count'],
                ],
                'inventory' => [
                    'available_vouchers' => $stats['available_vouchers_count'],
                    'reserved_vouchers' => $stats['reserved_vouchers_count'],
                    'low_stock' => $stats['low_stock_count'],
                ],
                'risk_forecasts' => $criticalAlerts->take(5)->values(),
            ],
            'tokenomics' => $tokenomics,
            'first_party_storefront' => $firstPartyStorefront,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function criticalAlerts(array $stats): Collection
    {
        $alerts = collect();

        $map = [
            ['market_errors_count', 'market_listing_errors', 'high', 'Есть ошибки публикации на маркетплейсе', 'Товары с замечаниями Яндекс.Маркета требуют проверки карточек и фидов.'],
            ['active_orders', 'active_orders', 'medium', 'Есть заказы в работе', 'Проверьте обработку и поставку кодов по активным заказам.'],
            ['problem_orders', 'problem_orders', 'high', 'Есть проблемные заказы', 'Проблемные заказы требуют ручного разбора или ответа поддержки.'],
            ['failed_purchase_items_count', 'failed_purchase_items', 'critical', 'Есть ошибки закупки кодов', 'Позиции с failed purchase_status могут блокировать выдачу клиентам.'],
            ['pending_purchase_items_count', 'pending_purchase_items', 'medium', 'Есть ожидающие закупки', 'Проверьте очередь закупки и доступность провайдера.'],
            ['open_tickets_count', 'open_support_tickets', 'medium', 'Есть открытые обращения', 'Служба поддержки ожидает ответа по тикетам.'],
            ['low_stock_count', 'low_stock', 'high', 'Низкий остаток на складах', 'Некоторые складские позиции ниже безопасного остатка.'],
            ['yandex_incomplete_channels', 'incomplete_yandex_integrations', 'high', 'Не все каналы Яндекса настроены', 'Активные магазины без business_id, campaign_id или API key не смогут полноценно публиковаться.'],
        ];

        foreach ($map as [$key, $type, $severity, $title, $description]) {
            if (($stats[$key] ?? 0) > 0) {
                $alerts->push(compact('type', 'severity', 'title', 'description') + ['value' => $stats[$key]]);
            }
        }

        if (! ($stats['integrity_secured'] ?? false)) {
            $alerts->push([
                'type' => 'ledger_sync_required',
                'severity' => 'high',
                'title' => 'Ledger требует синхронизации',
                'description' => 'Состояние Simple Layer 1 не подтверждено для финансовых решений.',
                'value' => 1,
            ]);
        }

        return $alerts;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function recommendations(array $stats): Collection
    {
        return collect([
            [
                'recommendation' => 'process_active_orders',
                'reason' => 'Сначала закрывайте заказы в работе: это напрямую влияет на SLA и клиентский опыт.',
                'trust_level' => 'high_trust',
                'priority_score' => (($stats['active_orders'] ?? 0) > 0) ? 0.92 : 0.30,
            ],
            [
                'recommendation' => 'resolve_failed_purchases',
                'reason' => 'Ошибки закупки кодов блокируют fulfillment и требуют самого высокого приоритета.',
                'trust_level' => (($stats['failed_purchase_items_count'] ?? 0) > 0) ? 'high_trust' : 'watch',
                'priority_score' => (($stats['failed_purchase_items_count'] ?? 0) > 0) ? 0.96 : 0.18,
            ],
            [
                'recommendation' => 'complete_yandex_integrations',
                'reason' => 'Яндекс-каналы без business_id/campaign_id/API key не могут стабильно публиковать и принимать заказы.',
                'trust_level' => (($stats['yandex_incomplete_channels'] ?? 0) > 0) ? 'high_trust' : 'usable',
                'priority_score' => (($stats['yandex_incomplete_channels'] ?? 0) > 0) ? 0.90 : 0.42,
            ],
            [
                'recommendation' => 'review_market_errors',
                'reason' => 'Ошибки карточек снижают доступность продаж во внешних каналах.',
                'trust_level' => (($stats['market_errors_count'] ?? 0) > 0) ? 'high_trust' : 'watch',
                'priority_score' => (($stats['market_errors_count'] ?? 0) > 0) ? 0.88 : 0.20,
            ],
            [
                'recommendation' => 'replenish_low_stock',
                'reason' => 'Низкий остаток повышает риск out-of-stock и отмен во внешних каналах.',
                'trust_level' => (($stats['low_stock_count'] ?? 0) > 0) ? 'usable' : 'watch',
                'priority_score' => (($stats['low_stock_count'] ?? 0) > 0) ? 0.82 : 0.16,
            ],
            [
                'recommendation' => 'verify_ledger_integrity',
                'reason' => 'Суверенный Ledger должен оставаться подтвержденным перед финансовыми решениями.',
                'trust_level' => ($stats['integrity_secured'] ?? false) ? 'usable' : 'high_trust',
                'priority_score' => ($stats['integrity_secured'] ?? false) ? 0.45 : 0.86,
            ],
        ])->sortByDesc('priority_score')->values();
    }

    private function meterRecommendationVisibility(LegalEntity $legalEntity, Collection $recommendations): void
    {
        try {
            $metering = app(TokenMeteringService::class);
            $day = now()->toDateString();

            $recommendations->each(function (array $recommendation) use ($metering, $legalEntity, $day) {
                $key = (string) ($recommendation['recommendation'] ?? 'recommendation');
                $estimatedValue = $this->estimatedRecommendationValueRub($key, (float) ($recommendation['priority_score'] ?? 0));

                $metering->meter(
                    $legalEntity,
                    'recommendation_generated',
                    null,
                    1,
                    null,
                    [
                        'recommendation' => $key,
                        'priority_score' => $recommendation['priority_score'] ?? null,
                        'trust_level' => $recommendation['trust_level'] ?? null,
                        'idempotency_key' => "rec-generated:{$legalEntity->id}:{$key}:{$day}",
                    ],
                    $estimatedValue
                );

                $metering->meter(
                    $legalEntity,
                    'recommendation_used',
                    null,
                    1,
                    null,
                    [
                        'recommendation' => $key,
                        'interaction' => 'operator_workspace_visible',
                        'idempotency_key' => "rec-used:{$legalEntity->id}:{$key}:{$day}",
                    ]
                );
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function tokenomics(LegalEntity $legalEntity): array
    {
        try {
            return app(TokenMeteringService::class)->summaryForLegalEntity($legalEntity);
        } catch (\Throwable $e) {
            report($e);

            return [
                'period_days' => 30,
                'usage_sl1' => 0.0,
                'commerce_sl1' => 0.0,
                'total_sl1' => 0.0,
                'total_rub_equivalent' => 0.0,
                'estimated_value_rub' => 0.0,
                'roi' => 0.0,
                'events_count' => 0,
                'by_event_type' => [],
                'recommendations' => [
                    'generated_count' => 0,
                    'used_count' => 0,
                    'hit_count' => 0,
                    'hit_rate' => 0.0,
                    'sl1_cost' => 0.0,
                    'rub_equivalent' => 0.0,
                    'estimated_value_rub' => 0.0,
                    'roi' => 0.0,
                ],
            ];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function firstPartyStorefront(LegalEntity $legalEntity): ?array
    {
        if ((string) $legalEntity->inn !== (string) config('meanly_storefront.legal_entity.inn')) {
            return null;
        }

        $domain = (string) config('meanly_storefront.shop.domain');
        $prefix = (string) config('meanly_storefront.shop.voucher_prefix', 'MEAN');
        $storefrontChannel = (string) config('meanly_storefront.channels.storefront', 'meanly_storefront');
        $yandexChannel = (string) config('meanly_storefront.channels.yandex', 'yandex_market');

        $shop = Shop::query()
            ->where('legal_entity_id', $legalEntity->id)
            ->where(function ($query) use ($domain, $prefix) {
                $query->where('domain', $domain)->orWhere('voucher_prefix', $prefix);
            })
            ->first();

        if (! $shop) {
            return null;
        }

        $productIds = Product::query()->where('shop_id', $shop->id)->pluck('id');
        $storefrontProductIds = ProductSalesChannel::query()
            ->where('shop_id', $shop->id)
            ->where('channel', $storefrontChannel)
            ->where('is_enabled', true)
            ->pluck('product_id');
        $yandexProductIds = ProductSalesChannel::query()
            ->where('shop_id', $shop->id)
            ->where('channel', $yandexChannel)
            ->where('is_enabled', true)
            ->pluck('product_id');

        $storefrontOrders = Order::query()
            ->where('shop_id', $shop->id)
            ->where('sales_channel', $storefrontChannel)
            ->where('created_at', '>=', now()->subDays(30));
        $yandexOrders = Order::query()
            ->where('shop_id', $shop->id)
            ->where('sales_channel', $yandexChannel)
            ->where('created_at', '>=', now()->subDays(30));

        $lastReconciliation = SovereignLedger::query()
            ->where('shop_id', $shop->id)
            ->where('event_type', 'MEANLY_CATALOG_RECONCILED')
            ->latest('created_at')
            ->first();

        return [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'storefront_url' => route('meanly.storefront.index'),
            'yandex_configured' => filled($shop->campaign_id) && filled($shop->api_key),
            'products_total' => $productIds->count(),
            'storefront_products' => $storefrontProductIds->count(),
            'yandex_products' => $yandexProductIds->count(),
            'channel_overlap' => $storefrontProductIds->intersect($yandexProductIds)->count(),
            'storefront_orders_30_days' => (clone $storefrontOrders)->count(),
            'yandex_orders_30_days' => (clone $yandexOrders)->count(),
            'storefront_gmv_30_days' => round((float) (clone $storefrontOrders)->sum('total_amount'), 2),
            'yandex_gmv_30_days' => round((float) (clone $yandexOrders)->sum('total_amount'), 2),
            'available_vouchers' => ProductInventory::query()
                ->where('shop_id', $shop->id)
                ->where('is_used', false)
                ->where('status', 'available')
                ->count(),
            'sold_vouchers_30_days' => ProductInventory::query()
                ->where('shop_id', $shop->id)
                ->where('status', 'sold')
                ->where('updated_at', '>=', now()->subDays(30))
                ->count(),
            'last_reconciliation' => $lastReconciliation ? [
                'at' => $lastReconciliation->created_at?->toISOString(),
                'missing_local_count' => (int) data_get($lastReconciliation->payload, 'missing_local_count', 0),
                'missing_yandex_count' => (int) data_get($lastReconciliation->payload, 'missing_yandex_count', 0),
                'price_mismatch_count' => (int) data_get($lastReconciliation->payload, 'price_mismatch_count', 0),
            ] : null,
        ];
    }

    private function estimatedRecommendationValueRub(string $recommendation, float $priorityScore): float
    {
        $baseValue = match ($recommendation) {
            'resolve_failed_purchases' => 1500.0,
            'process_active_orders' => 900.0,
            'complete_yandex_integrations' => 2500.0,
            'review_market_errors' => 1200.0,
            'replenish_low_stock' => 1800.0,
            'verify_ledger_integrity' => 3000.0,
            default => 500.0,
        };

        return round($baseValue * max(0.1, $priorityScore), 2);
    }

    private function forecastAccuracy(array $stats): float
    {
        $penalty = min(0.35, (($stats['market_errors_count'] ?? 0) * 0.03) + (($stats['failed_purchase_items_count'] ?? 0) * 0.04));

        return round(max(0.50, 0.92 - $penalty), 2);
    }

    private function policyEffectiveness(array $stats): float
    {
        $riskLoad = ($stats['active_orders'] ?? 0)
            + ($stats['problem_orders'] ?? 0)
            + ($stats['open_tickets_count'] ?? 0)
            + ($stats['yandex_incomplete_channels'] ?? 0);

        return round(max(0.45, 0.90 - min(0.35, $riskLoad * 0.03)), 2);
    }
}
