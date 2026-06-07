<?php

namespace App\Http\Controllers\Api;

use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\ProviderProduct;
use App\Models\SovereignLedger;
use App\Services\LedgerService;
use App\Services\SimpleLayer1TransactionReferenceService;
use App\Services\StandardizationService;
use App\Services\VaultTransitService;
use App\Services\WildflowService;
use App\Services\Provider\ProviderHub;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SellerOrderController extends Controller
{
    public function __construct(
        protected StandardizationService $standardizer,
        protected VaultTransitService $vault,
        protected LedgerService $ledger,
        protected ProviderHub $providerHub
    ) {}

    /**
     * Display the seller's available fiat balance (RUB) and Kernel partner balance (USD).
     * GET /api/seller/balance
     */
    public function balance(Request $request)
    {
        $legalEntity = $request->legalEntity();
        $localBalance = (float)$legalEntity->available_balance;

        $kernelBalance = 0.0;
        try {
            $wildflowService = new WildflowService();
            // In Kernel, terminal_id is synced using (string)$legalEntity->id
            $partnerData = $wildflowService->getPartner((string)$legalEntity->id);
            $kernelBalance = (float)($partnerData['balance'] ?? 0.0);
        } catch (\Exception $e) {
            Log::error("Failed to get partner balance from Kernel for LE {$legalEntity->id}: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'local_balance' => [
                'amount' => $localBalance,
                'currency' => $legalEntity->currency ?? 'RUB',
            ],
            'kernel_balance' => [
                'amount' => $kernelBalance,
                'currency' => 'USD',
            ],
        ]);
    }

    /**
     * Paginated and filtered catalog of active, standardized products allowed for the seller.
     * GET /api/seller/catalog
     */
    public function catalog(Request $request)
    {
        $legalEntity = $request->legalEntity();
        $shop = $legalEntity->shops()->first();

        if (!$shop) {
            return response()->json([
                'success' => false,
                'code' => 'NO_SHOP_ASSOCIATED',
                'message' => 'У продавца отсутствует привязанный магазин (Shop).',
            ], 400);
        }

        $provider = $request->query('provider');
        $brand = $request->query('brand');
        $category = $request->query('category');
        $perPage = min((int)$request->query('per_page', 100), 500);

        $query = ProviderProduct::with(['provider', 'brand.catalogGroup', 'region'])
            ->where('is_active', true);

        if ($provider) {
            $query->whereHas('provider', fn ($q) => $q->where('type', $provider));
        }

        if ($brand) {
            $query->whereHas('brand', fn ($q) => $q->where('name', 'like', "%{$brand}%"));
        }

        if ($category) {
            $query->where('category', 'like', "%{$category}%");
        }

        // Database-level pre-filtering based on allowed brands if allow_all_brands is false
        if (!$legalEntity->allow_all_brands && !empty($legalEntity->allowed_brands)) {
            $query->whereIn('brand_id', $legalEntity->allowed_brands);
        }

        $paginator = $query->orderBy('provider_id')->orderBy('id')->paginate($perPage);

        $standardizedProducts = collect($paginator->items())
            ->filter(fn ($item) => $legalEntity->canSellProduct($item))
            ->map(fn ($item) => $this->standardizer->standardizeProviderProduct($item, $shop))
            ->values();

        return response()->json([
            'success' => true,
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
            'products' => $standardizedProducts,
        ]);
    }

    /**
     * Create a new purchase order for the seller.
     * POST /api/seller/order
     */
    public function createOrder(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'quantity' => 'nullable|integer|min:1',
            'destination' => 'nullable|string',
            'client_reference' => 'nullable|string|max:128',
        ]);

        $sku = $request->input('sku');
        $quantity = (int)$request->input('quantity', 1);
        $destination = $request->input('destination', '');
        $clientReference = trim((string)$request->input('client_reference', '')) ?: null;

        $legalEntity = $request->legalEntity();
        $terminal = $request->sellerTerminal();
        $shop = $legalEntity->shops()->first();

        if (!$shop) {
            return response()->json([
                'success' => false,
                'code' => 'NO_SHOP_ASSOCIATED',
                'message' => 'У продавца отсутствует привязанный магазин (Shop).',
            ], 400);
        }

        $orderPublicId = $clientReference
            ? $this->sellerOrderId($legalEntity->id, $clientReference)
            : 'SL-ORD-' . strtoupper(Str::random(12));

        if ($clientReference) {
            $existingOrder = Order::with('items')
                ->where('shop_id', $shop->id)
                ->where('order_id', $orderPublicId)
                ->first();

            if ($existingOrder) {
                return $this->sellerOrderResponse($existingOrder, idempotent: true);
            }
        }

        // 1. Locate product using cryptographic blind index
        $skuBidx = $this->vault->computeBlindIndex($sku);
        $product = ProviderProduct::with('provider')
            ->where(function ($q) use ($skuBidx) {
                $q->where('sku_bidx', $skuBidx)
                  ->orWhere('market_sku_bidx', $skuBidx);
            })
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'code' => 'PRODUCT_NOT_FOUND',
                'message' => 'Товар не найден.',
            ], 404);
        }

        if (!$product->is_active) {
            return response()->json([
                'success' => false,
                'code' => 'PRODUCT_INACTIVE',
                'message' => 'Выбранный товар неактивен.',
            ], 400);
        }

        // 2. Access control check
        if (!$legalEntity->canSellProduct($product)) {
            return response()->json([
                'success' => false,
                'code' => 'PRODUCT_RESTRICTED',
                'message' => 'Продажа данного товара ограничена для вашей организации.',
            ], 403);
        }

        // 3. Financial calculations
        $pricePerItemRub = $legalEntity->calculateOrderCost(
            (float)$product->purchase_price,
            (float)$product->retail_price,
            $product->currency ?? 'USD'
        );
        $totalCostRub = $pricePerItemRub * $quantity;

        // 4. Verify balance
        if ($legalEntity->available_balance < $totalCostRub) {
            return response()->json([
                'success' => false,
                'code' => 'INSUFFICIENT_FUNDS',
                'message' => "Недостаточно средств на балансе. Требуется " . number_format($totalCostRub, 2) . " RUB, доступно " . number_format($legalEntity->available_balance, 2) . " RUB.",
            ], 402);
        }

        // 5. Verify daily limit
        if ($terminal && !$terminal->hasRemainingDailyBudget($totalCostRub)) {
            return response()->json([
                'success' => false,
                'code' => 'DAILY_LIMIT_EXCEEDED',
                'message' => 'Превышен суточный лимит расходов для данного терминала.',
            ], 400);
        }

        // 6. Availability Guard
        try {
            $wildflowService = new WildflowService(providerModel: $product->provider);
            $availability = $wildflowService->checkAvailability(
                service_sku: $product->sku,
                quantity: $quantity,
                price: (float)$product->purchase_price,
                provider: $product->provider->type,
                terminalId: (string)$legalEntity->id
            );

            if (!($availability['available'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'code' => 'PRODUCT_OUT_OF_STOCK',
                    'message' => 'Товар временно отсутствует у поставщика.',
                ], 400);
            }
        } catch (\Exception $e) {
            Log::warning("Availability check exception for SKU {$product->sku}: " . $e->getMessage());
            // Fall-open architecture to handle temporary aggregator glitches gracefully
        }

        // 7. Debit balance (Reserve hold state)
        DB::transaction(function () use ($legalEntity, $totalCostRub) {
            $legalEntity->decrement('available_balance', $totalCostRub);
            $legalEntity->increment('reserved_balance', $totalCostRub);
        });

        // 8. Create local Order and OrderItem
        $order = Order::create([
            'order_id' => $orderPublicId,
            'uuid' => Str::uuid()->toString(),
            'status' => 'PROCESSING',
            'progress_id' => 2, // In Progress
            'shop_id' => $shop->id,
            'business_id' => $shop->business_id,
            'total_amount' => $totalCostRub,
            'currency' => 'RUB',
            'cost_amount' => $product->purchase_price * $quantity,
            'cost_currency' => $product->currency ?? 'USD',
            'client_info' => [
                'email' => $destination ?: 'terminal-api@meanly.platform',
                'seller_terminal_id' => $terminal?->terminal_id,
            ],
            'info' => [
                'seller_client_reference' => $clientReference,
                'items' => [
                    [
                        'offerId' => $product->sku,
                        'price' => $pricePerItemRub,
                        'count' => $quantity,
                    ]
                ]
            ]
        ]);

        $orderItem = OrderItems::create([
            'key' => 'SL-VCH-' . strtoupper(Str::random(12)),
            'uuid' => Str::uuid()->toString(),
            'order_id' => $order->id,
            'activate_till' => now()->addYear()->format('Y-m-d'),
            'sku' => $product->sku,
            'count' => $quantity,
            'price_rub' => $pricePerItemRub * 100,
            'price_try' => 0,
            'purchase_status' => 'pending',
        ]);

        $providerReference = $orderItem->providerReference();

        // 9. Dispatch to Provider Hub driver
        $provider = $product->provider;
        $driver = $this->providerHub->forProvider($provider);

        // Record provider start event in Ledger
        $this->ledger->record($shop, 'PROVIDER_ORDER_START', $orderItem, [
            'provider' => $provider->type,
            'sku' => $product->sku,
            'provider_reference' => $providerReference,
            'transaction_ref' => $orderItem->transactionReference(),
        ], $legalEntity);

        try {
            $externalOrderId = $driver->createOrder(
                sku: $product->sku,
                reference: $providerReference,
                price: (float)$product->purchase_price,
                quantity: $quantity,
                meta: [
                    'terminal_id' => (string)$legalEntity->id,
                    'seller_id' => (string)$legalEntity->id,
                    'seller_name' => $legalEntity->name,
                    'email' => $destination ?: 'terminal-api@meanly.platform',
                    'pre_order' => false,
                ]
            );

            if (!$externalOrderId) {
                throw new \Exception("External order reference not returned from provider driver.");
            }

            $sourceReceipt = method_exists($driver, 'lastSourceLedgerReceipt') ? $driver->lastSourceLedgerReceipt() : null;
            $orderItem->update(['provider_order_id' => $externalOrderId]);

            // 10. Synchronous Polling for Codes (12 attempts x 2s)
            $codes = [];
            for ($attempt = 1; $attempt <= 12; $attempt++) {
                sleep(2);
                try {
                    $codes = $driver->getCodes($externalOrderId);
                    if (!empty($codes)) {
                        break;
                    }
                } catch (\Exception $pollEx) {
                    Log::warning("Adaptive Polling attempt {$attempt} failed for {$externalOrderId}: " . $pollEx->getMessage());
                }
            }

            $originalCode = !empty($codes) ? $codes[0] : null;

            if ($originalCode) {
                // Successful completion! Capture the reserved funds
                DB::transaction(function () use ($legalEntity, $totalCostRub) {
                    $legalEntity->decrement('reserved_balance', $totalCostRub);
                });

                $orderItem->update([
                    'purchase_status' => 'success',
                    'original_code' => $originalCode,
                    'purchase_error' => null,
                ]);

                $order->update(['progress_id' => 4]); // Completed

                // Record events in Ledger
                $this->ledger->record($shop, 'VOUCHER_REDEEM_SUCCESS', $orderItem, [
                    'provider' => $provider->type,
                    'external_id' => $externalOrderId,
                    'sku' => $product->sku,
                    'code_masked' => Str::mask($originalCode, '*', 4, -4),
                    ...$this->sourceReceiptPayload($sourceReceipt),
                ], $legalEntity);

                $this->ledger->record($shop, 'FINANCE_CAPTURE', $order, [
                    'amount_rub' => $totalCostRub,
                    'order_item_id' => $orderItem->id,
                ], $legalEntity);

                return response()->json([
                    'success' => true,
                    'transaction_ref' => $orderItem->transactionReference(),
                    'status' => 'completed',
                    'sku' => $product->sku,
                    'quantity' => $quantity,
                    'total_amount' => $totalCostRub,
                    'currency' => 'RUB',
                    'codes' => [$originalCode],
                ]);
            }

            // Soft-fail: order created but codes not yet ready (stays in processing status)
            $orderItem->update(['purchase_status' => 'processing']);

            return response()->json([
                'success' => true,
                'transaction_ref' => $orderItem->transactionReference(),
                'status' => 'processing',
                'sku' => $product->sku,
                'quantity' => $quantity,
                'total_amount' => $totalCostRub,
                'currency' => 'RUB',
                'message' => 'Заказ успешно создан. Коды обрабатываются поставщиком. Проверьте статус позже.',
            ]);

        } catch (\Exception $e) {
            // Auto-refund immediately on hard execution failure
            DB::transaction(function () use ($legalEntity, $totalCostRub) {
                $legalEntity->decrement('reserved_balance', $totalCostRub);
                $legalEntity->increment('available_balance', $totalCostRub);
            });

            $orderItem->update([
                'purchase_status' => 'failed',
                'purchase_error' => $e->getMessage(),
            ]);

            $order->update([
                'status' => 'CANCELLED',
                'is_problem' => 1,
            ]);

            $this->ledger->record($shop, 'PROVIDER_ORDER_FAILED', $orderItem, [
                'provider' => $provider->type,
                'message' => $e->getMessage(),
                'sku' => $product->sku,
            ], $legalEntity);

            return response()->json([
                'success' => false,
                'code' => 'PROVIDER_ORDER_FAILED',
                'message' => 'Не удалось оформить заказ у поставщика: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retrieve details of a specific order by public Simple Layer 1 transaction reference.
     * Legacy order/item UUID and voucher lookups are accepted for backward compatibility.
     * GET /api/seller/order/{reference}
     */
    public function showOrder(Request $request, string $reference)
    {
        $legalEntity = $request->legalEntity();

        $order = $this->resolveOrderByL1Reference($reference)
            ?? Order::with('items')
                ->where('order_id', $reference)
                ->orWhere('uuid', $reference)
                ->first();

        if (!$order) {
            // Find by order item uuid or voucher key
            $keyBidx = $this->vault->computeBlindIndex($reference);
            $item = OrderItems::with('order.items')
                ->where('uuid', $reference)
                ->orWhere('key_bidx', $keyBidx)
                ->first();

            if ($item) {
                $order = $item->order;
            }
        }

        if (!$order) {
            return response()->json([
                'success' => false,
                'code' => 'ORDER_NOT_FOUND',
                'message' => 'Заказ не найден.',
            ], 404);
        }

        // Access control check
        $shop = $order->shop;
        if (!$shop || $shop->legal_entity_id !== $legalEntity->id) {
            return response()->json([
                'success' => false,
                'code' => 'ORDER_FORBIDDEN',
                'message' => 'Доступ к данному заказу ограничен.',
            ], 403);
        }

        $itemsFormatted = $order->items->map(function ($item) {
            $res = [
                'transaction_ref' => $item->transactionReference(),
                'sku' => $item->sku,
                'count' => (int)$item->count,
                'price_rub' => (float)($item->price_rub / 100),
                'purchase_status' => $item->purchase_status,
            ];

            if ($item->purchase_status === 'success' && $item->original_code) {
                $res['codes'] = [$item->original_code];
            }

            return $res;
        });

        $status = 'processing';
        if ($order->progress_id == 4) {
            $status = 'completed';
        } elseif ($order->status === 'CANCELLED' || $order->is_problem) {
            $status = 'failed';
        }

        return response()->json([
            'success' => true,
            'transaction_ref' => $order->transactionReference(),
            'status' => $status,
            'total_amount' => (float)$order->total_amount,
            'currency' => $order->currency ?? 'RUB',
            'created_at' => $order->created_at->toIso8601String(),
            'items' => $itemsFormatted,
        ]);
    }

    private function resolveOrderByL1Reference(string $reference): ?Order
    {
        $prefix = app(SimpleLayer1TransactionReferenceService::class)->fingerprintPrefixFromReference($reference);
        if ($prefix === null) {
            return null;
        }

        $ledgerEntry = SovereignLedger::query()
            ->whereRaw('LOWER(fingerprint) LIKE ?', [$prefix.'%'])
            ->latest('id')
            ->first();

        if (! $ledgerEntry) {
            return null;
        }

        if ($ledgerEntry->entity_type === Order::class) {
            return Order::with('items')->find($ledgerEntry->entity_id);
        }

        if ($ledgerEntry->entity_type === OrderItems::class) {
            return OrderItems::with('order.items')->find($ledgerEntry->entity_id)?->order;
        }

        return null;
    }

    private function sellerOrderId(int $legalEntityId, string $clientReference): string
    {
        return 'SL-ORD-' . strtoupper(substr(hash('sha256', $legalEntityId . '|' . $clientReference), 0, 16));
    }

    private function sellerOrderResponse(Order $order, bool $idempotent = false)
    {
        $order->loadMissing('items');
        $item = $order->items->first();

        $status = 'processing';
        if ((int)$order->progress_id === 4) {
            $status = 'completed';
        } elseif ($order->status === 'CANCELLED' || $order->is_problem) {
            $status = 'failed';
        }

        $payload = [
            'success' => $status !== 'failed',
            'idempotent' => $idempotent,
            'transaction_ref' => $item?->transactionReference() ?? $order->transactionReference(),
            'status' => $status,
            'sku' => $item?->sku,
            'quantity' => (int)($item?->count ?? 0),
            'total_amount' => (float)$order->total_amount,
            'currency' => $order->currency ?? 'RUB',
        ];

        if ($item?->purchase_status === 'success' && $item->original_code) {
            $payload['codes'] = [$item->original_code];
        }

        return response()->json($payload, $status === 'failed' ? 500 : 200);
    }

    private function sourceReceiptPayload(?array $receipt): array
    {
        if (! is_array($receipt)) {
            return [];
        }

        return [
            'digital_goods_source_receipt_hash' => $receipt['event_hash'] ?? null,
            'source_order_reference' => $receipt['reference'] ?? null,
        ];
    }
}
