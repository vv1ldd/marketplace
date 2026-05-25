<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order\OrderItems;
use App\Models\Shop;
use App\Models\WildflowCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only API для внешнего леджера (provable-ops): маппинг SKU из wildflow_catalogs
 * и факты redeem / активации по order_items (без секретов клиента).
 */
class LedgerApiController extends Controller
{
    /**
     * Снимок каталога: offerId Маркета, ключ прокси Wildflow, признаки для EZ.
     *
     * Колонки БД (историческое именование): sku = витринный идентификатор (VOUCHER-GC-…),
     * service_sku = ключ партнёра из ответа api.wildflow.dev (см. provable-ops wildflow-proxy-sku-flow).
     */
    public function catalogMap(Request $request): JsonResponse
    {
        $ledgerShopId = $request->attributes->get('ledger_shop_id');

        $whitelist = config('app.ledger_ip_whitelist');
        if ($ledgerShopId === null && $whitelist && ! in_array($request->ip(), explode(',', $whitelist))) {
            return response()->json(['error' => 'Unauthorized IP: '.$request->ip()], 403);
        }

        $validated = $request->validate([
            'updated_since' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:2000',
        ]);

        $limit = (int) ($validated['limit'] ?? 500);
        $limit = min($limit, 2000);

        $query = WildflowCatalog::query()->orderBy('id');

        if ($ledgerShopId !== null) {
            $shop = Shop::query()->whereKey($ledgerShopId)->first();
            $skus = $shop ? $shop->products()->pluck('products.sku') : collect();

            if ($skus->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'count' => 0,
                    'items' => [],
                ]);
            }

            $query->whereIn('sku', $skus);
        }

        if (! empty($validated['updated_since'])) {
            $query->where('updated_at', '>=', $validated['updated_since']);
        }

        $rows = $query->limit($limit)->get([
            'id',
            'sku',
            'service_sku',
            'type',
            'category',
            'bussiness_id',
            'data',
            'updated_at',
        ]);

        $items = $rows->map(function (WildflowCatalog $row) {
            $payload = $row->data['data'] ?? [];

            $skuSupplier = data_get($payload, 'product.sku')
                ?? data_get($payload, 'sku')
                ?? data_get($payload, 'product_code');

            return [
                'catalog_row_id' => $row->id,
                'sku_marketplace' => $row->sku,
                'sku_proxy_key' => $row->service_sku,
                'catalog_type' => $row->type,
                'category' => $row->category,
                'ym_business_id' => $row->bussiness_id,
                'sku_supplier' => $skuSupplier,
                'sku_map_version' => 'wildflow-catalog-row@'.$row->updated_at->toIso8601String(),
                'updated_at' => $row->updated_at->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'count' => $items->count(),
            'items' => $items,
        ]);
    }

    /**
     * События по ваучерам: verify (is_redeemed) и/или activate (is_activated).
     * Не отдаём пароли PSN / 2FA из client_info — только безопасные поля.
     */
    public function redeemEvents(Request $request): JsonResponse
    {
        $ledgerShopId = $request->attributes->get('ledger_shop_id');

        $whitelist = config('app.ledger_ip_whitelist');
        if ($ledgerShopId === null && $whitelist && ! in_array($request->ip(), explode(',', $whitelist))) {
            return response()->json(['error' => 'Unauthorized IP: '.$request->ip()], 403);
        }

        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = min((int) ($validated['per_page'] ?? 50), 100);

        $query = OrderItems::query()
            ->with(['order:id,order_id,status,sub_status,code_activated'])
            ->where(function ($q) {
                $q->where('is_redeemed', true)->orWhere('is_activated', true);
            });

        if ($ledgerShopId !== null) {
            $query->whereHas('order', fn ($q) => $q->where('shop_id', $ledgerShopId));
        }

        if (! empty($validated['from'])) {
            $query->where('updated_at', '>=', $validated['from'].' 00:00:00');
        }
        if (! empty($validated['to'])) {
            $query->where('updated_at', '<=', $validated['to'].' 23:59:59');
        }

        $paginator = $query
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $validated['page'] ?? 1);

        $events = collect($paginator->items())->map(function (OrderItems $item) {
            $order = $item->order;

            return [
                'transaction_ref' => $item->transactionReference(),
                'redeem_code_masked' => $this->maskRedeemKey($item->key),
                'sku_marketplace' => $item->sku,
                'quantity' => $item->count,
                'is_redeemed' => (bool) $item->is_redeemed,
                'is_activated' => (bool) $item->is_activated,
                'activated_at' => $item->activated_at?->toIso8601String(),
                'updated_at' => $item->updated_at->toIso8601String(),
                'type_form_id' => $item->type_form_id ?? null,
                'client_contact' => $this->safeClientInfo($item->client_info),
                'order' => $order ? [
                    'transaction_ref' => $order->transactionReference(),
                    'source_order_id' => $order->order_id,
                    'status' => $order->status,
                    'sub_status' => $order->sub_status,
                    'code_activated' => (bool) $order->code_activated,
                ] : null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $events,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function trace(Request $request, string $reference): JsonResponse
    {
        $ledgerShopId = $request->attributes->get('ledger_shop_id');

        $whitelist = config('app.ledger_ip_whitelist');
        if ($ledgerShopId === null && $whitelist && ! in_array($request->ip(), explode(',', $whitelist))) {
            return response()->json(['error' => 'Unauthorized IP: '.$request->ip()], 403);
        }

        $legalEntityId = null;
        if ($ledgerShopId !== null) {
            $legalEntityId = Shop::query()->whereKey($ledgerShopId)->value('legal_entity_id');
        }

        $trace = app(\App\Services\SimpleLayer1TraceService::class)->trace($reference, $legalEntityId);

        if (! $trace) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Simple Layer 1 transaction reference not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'trace' => $trace,
        ]);
    }

    private function maskRedeemKey(?string $key): ?string
    {
        if ($key === null || strlen($key) < 8) {
            return null;
        }

        return substr($key, 0, 4).'…'.substr($key, -4);
    }

    /**
     * @param  array<string, mixed>|null  $info
     * @return array<string, mixed>|null
     */
    private function safeClientInfo(?array $info): ?array
    {
        if ($info === null || $info === []) {
            return null;
        }

        $keys = ['email', 'first_name', 'last_name', 'phone', 'type_id'];

        return array_intersect_key($info, array_flip($keys));
    }
}
