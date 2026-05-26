<?php

namespace App\Http\Controllers;

use App\Models\Order\Order;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Services\CanonicalStorefrontHomepageService;
use App\Services\LlmProductFactsService;
use App\Services\MarketplaceDiscoveryService;
use App\Services\BuyerWalletService;
use App\Services\BuyerWalletTransactionService;
use App\Services\MeanlyFirstPartyStorefrontService;
use App\Services\MeanlyAnalyticsService;
use App\Services\MeanlyRetailCheckoutService;
use App\Services\IntentLedgerService;
use App\Services\OrderSupportTicketService;
use App\Services\SimpleL1ProtocolClient;
use App\Services\StorefrontFulfillmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MeanlyStorefrontController extends Controller
{
    public function index(Request $request, MeanlyFirstPartyStorefrontService $storefront, CanonicalStorefrontHomepageService $homepage, \App\Services\CatalogSearchLogService $logService)
    {
        $shop = $storefront->shop();
        $data = $homepage->homepage($request);
        $query = $data['query'];
        $products = $data['browse_products'];

        if (filled($query)) {
            $logService->log($query, 'storefront', $products->total());
        }

        $catalogJsonLd = $homepage->itemListJsonLd($products, 'Meanly canonical provider network marketplace');

        return view('storefront.index', compact('shop', 'products', 'query', 'catalogJsonLd') + ['homepage' => $data]);
    }

    public function search(Request $request, CanonicalStorefrontHomepageService $homepage, \App\Services\CatalogSearchLogService $logService): JsonResponse
    {
        $data = $homepage->searchPage($request);
        $query = $data['query'];
        $products = $data['browse_products'];
        $products->setPath(route('home'));

        if (filled($query)) {
            $logService->log($query, 'storefront', $products->total());
        }

        return response()->json([
            'query' => $query,
            'total' => $products->total(),
            'html' => view('storefront.partials.search-results', compact('query', 'products'))->render(),
        ]);
    }

    public function show(Request $request, string $slug, MeanlyFirstPartyStorefrontService $storefront, MarketplaceDiscoveryService $discovery, LlmProductFactsService $llmFacts, StorefrontFulfillmentService $fulfillment)
    {
        $shop = $storefront->shop();
        $product = $storefront->marketplaceProductsQuery()
            ->where('slug', $slug)
            ->firstOrFail();
        $discovery->rememberRecentlyViewed($request, $product);
        $productFacts = $llmFacts->productFacts($product);
        $productJsonLd = $llmFacts->productJsonLd($product);
        $checkoutAvailability = $fulfillment->checkoutAvailability($product, 1);

        // Funnel Telemetry: increment views count if search session active
        try {
            if (session()->has('last_search_log_id')) {
                app(\App\Services\CatalogSearchLogService::class)->incrementViews((int) session()->get('last_search_log_id'));
            }
        } catch (\Throwable $e) {
            // Ignore telemetry exceptions
        }

        return view('storefront.show', compact('shop', 'product', 'productFacts', 'productJsonLd', 'checkoutAvailability'));
    }


    public function toggleFavorite(Request $request, Product $product, MeanlyFirstPartyStorefrontService $storefront, MarketplaceDiscoveryService $discovery)
    {
        $shop = $storefront->shop();
        $visibleProduct = $storefront->marketplaceProductsQuery()->whereKey($product->id)->firstOrFail();
        $state = $discovery->toggleFavorite($request, $visibleProduct, $shop);

        return response()->json(['success' => true] + $state);
    }

    public function checkout(
        Request $request,
        MeanlyFirstPartyStorefrontService $storefront,
        MeanlyRetailCheckoutService $checkout,
        StorefrontFulfillmentService $fulfillment,
    )
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1|max:5',
            'name' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:64',
            'is_gift' => 'nullable|boolean',
            'payment_method' => 'nullable|string|max:32',
            'fulfillment_mode' => 'nullable|in:instant',
            'preorder_acknowledged' => 'nullable|boolean',
        ]);

        if ($this->isRubtWalletPayment($data['payment_method'] ?? null)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Оплата RUBT требует Passkey-подписи. Используйте защищенный RUBT checkout.',
            ]);
        }

        $shop = $storefront->shop();
        $product = $storefront->marketplaceProductsQuery()
            ->whereKey($data['product_id'])
            ->firstOrFail();
        $availability = $fulfillment->assertCheckoutAvailability(
            $product,
            (int) $data['quantity'],
            $data['fulfillment_mode'] ?? null,
            $request->boolean('preorder_acknowledged'),
        );
        $customer = $this->checkoutCustomer($request, $data);
        $simpleL1Intent = $this->submitSimpleL1CheckoutIntent($request, $product, (int) $data['quantity'], $availability, $customer);

        $result = $checkout->checkout($product, $customer, (int) $data['quantity'], [
            'fulfillment_mode' => $availability['fulfillment_mode'],
            'preorder_acknowledged' => $availability['preorder_acknowledged'],
            'availability' => $availability,
        ]);
        $this->attachSimpleL1IntentProjection($result['order'], $simpleL1Intent);
        if (($result['fulfillment_status'] ?? null) === 'provider_redeem_pending') {
            $fulfillment->fulfillProviderOrder($result['order']);
            $result['order'] = $result['order']->refresh();
        }

        app(MeanlyAnalyticsService::class)->track('checkout.order.created', [
            'quantity' => (int) $data['quantity'],
            'payment_method' => 'standard_checkout',
            'fulfillment_status' => $result['fulfillment_status'] ?? null,
            'availability_status' => $availability['status'] ?? null,
            'total_rub' => $result['total_rub'] ?? null,
        ], [
            'event_type' => 'checkout',
            'surface' => 'storefront',
            'product_id' => $product->id,
            'order_id' => $result['order']->id ?? null,
            'shop_id' => $shop->id,
            'category' => $product->canonical_category ?? $product->category,
            'currency' => 'RUB',
        ]);

        $safeUrl = URL::signedRoute('meanly.storefront.orders.safe.show', [
            'order' => $result['order']->uuid,
        ]);
        $safeStatusUrl = URL::signedRoute('meanly.storefront.orders.safe.status', [
            'order' => $result['order']->uuid,
        ]);
        $safeOpenUrl = URL::signedRoute('meanly.storefront.orders.safe.open', [
            'order' => $result['order']->uuid,
        ]);

        if ($request->wantsJson()) {
            $payload = [
                'success' => true,
                'order_id' => $result['order']->order_id,
                'total_rub' => $result['total_rub'],
                'vouchers' => $result['vouchers'],
                'safe_status' => $this->orderSafeStatus($result['order'])['status'],
                'fulfillment_mode' => $availability['fulfillment_mode'],
                'safe_url' => $safeUrl,
                'safe_status_url' => $safeStatusUrl,
                'safe_open_url' => $safeOpenUrl,
            ];

            if ($request->user()) {
                $payload['cabinet_safe_url'] = $this->cabinetSafeUrl($result['order']);
            }

            return response()->json($payload);
        }

        return view('storefront.checkout-success', [
            'shop' => $shop,
            'order' => $result['order'],
            'vouchers' => $result['vouchers'],
            'totalRub' => $result['total_rub'],
            'safeUrl' => $safeUrl,
        ]);
    }

    public function checkoutAvailability(
        Request $request,
        MeanlyFirstPartyStorefrontService $storefront,
        StorefrontFulfillmentService $fulfillment,
    ) {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1|max:5',
        ]);

        $product = $storefront->marketplaceProductsQuery()
            ->whereKey($data['product_id'])
            ->firstOrFail();

        // Funnel Telemetry: increment carts count if search session active
        try {
            if (session()->has('last_search_log_id')) {
                app(\App\Services\CatalogSearchLogService::class)->incrementCarts((int) session()->get('last_search_log_id'));
            }
        } catch (\Throwable $e) {
            // Ignore telemetry exceptions
        }

        $availability = $fulfillment->checkoutAvailability($product, (int) $data['quantity']);

        app(MeanlyAnalyticsService::class)->track('checkout.availability.checked', [
            'quantity' => (int) $data['quantity'],
            'status' => $availability['status'] ?? null,
            'source' => $availability['source'] ?? null,
            'local_available' => $availability['local_available'] ?? null,
        ], [
            'event_type' => 'checkout',
            'surface' => 'storefront',
            'product_id' => $product->id,
            'shop_id' => $product->shop_id,
            'category' => $product->canonical_category ?? $product->category,
        ]);

        return response()->json($availability);
    }


    public function walletOptions(
        Request $request,
        MeanlyFirstPartyStorefrontService $storefront,
        BuyerWalletService $wallets,
        BuyerWalletTransactionService $transactions,
        StorefrontFulfillmentService $fulfillment,
    ) {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1|max:5',
            'name' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:64',
            'is_gift' => 'nullable|boolean',
            'fulfillment_mode' => 'nullable|in:instant',
            'preorder_acknowledged' => 'nullable|boolean',
        ]);

        $product = $storefront->marketplaceProductsQuery()
            ->whereKey($data['product_id'])
            ->firstOrFail();
        $availability = $fulfillment->assertCheckoutAvailability(
            $product,
            (int) $data['quantity'],
            $data['fulfillment_mode'] ?? null,
            $request->boolean('preorder_acknowledged'),
        );
        $customer = $this->checkoutCustomer($request, $data);
        $envelope = $transactions->buildPurchaseDebitEnvelope(
            $user,
            $product,
            $customer,
            (int) $data['quantity'],
            $availability['fulfillment_mode'],
        );
        $balance = $wallets->balance($user);

        if ($balance['available_minor'] < $envelope['amount_minor']) {
            throw ValidationException::withMessages([
                'wallet' => 'Недостаточно RUBT на балансе для оплаты заказа.',
            ]);
        }

        $signing = $transactions->authenticationOptions($user, $request, $envelope);
        $pending = [
            'envelope' => $envelope,
            'signing_options' => $signing['json'],
            'checkout' => [
                'product_id' => (int) $product->id,
                'quantity' => (int) $envelope['quantity'],
                'customer' => $customer,
                'fulfillment_mode' => $availability['fulfillment_mode'],
                'preorder_acknowledged' => $availability['preorder_acknowledged'],
                'availability' => $availability,
            ],
            'expires_at' => now()->addMinutes(10)->timestamp,
        ];

        session([
            'buyer_wallet_checkout.'.$envelope['pending_tx_id'] => $pending,
        ]);

        app(MeanlyAnalyticsService::class)->track('checkout.wallet.options_created', [
            'quantity' => (int) $envelope['quantity'],
            'amount_minor' => $envelope['amount_minor'],
            'asset' => 'RUBT',
            'fulfillment_mode' => $availability['fulfillment_mode'] ?? null,
        ], [
            'event_type' => 'checkout',
            'surface' => 'storefront',
            'product_id' => $product->id,
            'user_id' => $user->id,
            'shop_id' => $product->shop_id,
            'category' => $product->canonical_category ?? $product->category,
            'currency' => 'RUBT',
        ]);

        return response()->json($signing['options'] + [
            'pending_tx_id' => $envelope['pending_tx_id'],
            'tx_hash' => $envelope['tx_hash'],
            'tx_nonce' => $envelope['payload']['nonce'],
            'l1_address' => $envelope['payload']['buyer_l1_address'],
            'amount_minor' => $envelope['amount_minor'],
            'amount' => $envelope['amount'],
            'asset' => 'RUBT',
            'fulfillment_mode' => $availability['fulfillment_mode'],
            'canonical_payload' => $envelope['payload'],
            'canonical_json' => $envelope['canonical_json'],
        ]);
    }

    public function walletConfirm(
        Request $request,
        MeanlyFirstPartyStorefrontService $storefront,
        MeanlyRetailCheckoutService $checkout,
        BuyerWalletService $wallets,
        BuyerWalletTransactionService $transactions,
        StorefrontFulfillmentService $fulfillment,
    ) {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'pending_tx_id' => 'required|string',
            'tx_hash' => 'required|string|size:64',
            'assertion' => 'required|array',
        ]);

        $sessionKey = 'buyer_wallet_checkout.'.$data['pending_tx_id'];
        $pending = session($sessionKey);
        if (! is_array($pending)) {
            throw ValidationException::withMessages([
                'pending_tx_id' => 'Контекст RUBT-подписи утерян. Обновите страницу и повторите оплату.',
            ]);
        }

        if ((int) ($pending['expires_at'] ?? 0) < now()->timestamp) {
            session()->forget($sessionKey);
            throw ValidationException::withMessages([
                'pending_tx_id' => 'Контекст RUBT-подписи истек. Подпишите оплату заново.',
            ]);
        }

        $envelope = $pending['envelope'] ?? null;
        if (! is_array($envelope) || ! hash_equals((string) ($envelope['tx_hash'] ?? ''), (string) $data['tx_hash'])) {
            throw ValidationException::withMessages([
                'tx_hash' => 'RUBT transaction hash does not match the pending checkout.',
            ]);
        }

        $proof = $transactions->verifyAssertion(
            $user,
            $data['assertion'],
            (string) $pending['signing_options'],
            $envelope,
        );

        $product = $storefront->marketplaceProductsQuery()
            ->whereKey((int) data_get($pending, 'checkout.product_id'))
            ->firstOrFail();
        $quantity = (int) data_get($pending, 'checkout.quantity', 1);
        $customer = (array) data_get($pending, 'checkout.customer', []);
        $availability = $fulfillment->assertCheckoutAvailability(
            $product,
            $quantity,
            (string) data_get($pending, 'checkout.fulfillment_mode', 'instant'),
            (bool) data_get($pending, 'checkout.preorder_acknowledged', false),
        );
        $balance = $wallets->balance($user);

        if ($balance['available_minor'] < (int) $envelope['amount_minor']) {
            throw ValidationException::withMessages([
                'wallet' => 'Недостаточно RUBT на балансе для оплаты заказа.',
            ]);
        }

        $result = DB::transaction(function () use ($user, $wallets, $checkout, $product, $customer, $quantity, $envelope, $proof, $availability) {
            $walletEntry = $wallets->debitRUBT(
                user: $user,
                amountMinor: (int) $envelope['amount_minor'],
                entryType: 'BUYER_PURCHASE_DEBIT',
                idempotencyKey: 'buyer-wallet-checkout:'.$envelope['tx_hash'],
                payload: [
                    'source' => 'meanly_storefront_wallet_checkout',
                    'simple_layer_one' => $proof,
                    'checkout' => [
                        'product_id' => (int) $product->id,
                        'sku' => (string) $product->sku,
                        'quantity' => $quantity,
                        'fulfillment_mode' => $availability['fulfillment_mode'],
                    ],
                ],
                txHash: (string) $envelope['tx_hash'],
                nonce: (int) data_get($envelope, 'payload.nonce'),
            );

            $result = $checkout->checkout($product, $customer, $quantity, [
                'method' => 'buyer_wallet_rubt',
                'status' => 'captured',
                'wallet_ledger_entry_id' => $walletEntry->id,
                'simple_layer_one' => $proof,
                'tx_hash' => (string) $envelope['tx_hash'],
                'tx_nonce' => (int) data_get($envelope, 'payload.nonce'),
                'fulfillment_mode' => $availability['fulfillment_mode'],
                'preorder_acknowledged' => $availability['preorder_acknowledged'],
                'availability' => $availability,
            ]);

            $walletEntry->payload = array_merge($walletEntry->payload ?? [], [
                'checkout_result' => [
                    'order_id' => $result['order']->id,
                    'order_reference' => $result['order']->order_id,
                    'vouchers_count' => count($result['vouchers'] ?? []),
                    'fulfillment_mode' => $availability['fulfillment_mode'],
                ],
            ]);
            $walletEntry->save();

            return $result + ['wallet_ledger_entry' => $walletEntry->refresh()];
        });

        if (($result['fulfillment_status'] ?? null) === 'provider_redeem_pending') {
            $fulfillment->fulfillProviderOrder($result['order']);
            $result['order'] = $result['order']->refresh();
        }

        session()->forget($sessionKey);

        $safeUrl = URL::signedRoute('meanly.storefront.orders.safe.show', [
            'order' => $result['order']->uuid,
        ]);
        $safeStatusUrl = URL::signedRoute('meanly.storefront.orders.safe.status', [
            'order' => $result['order']->uuid,
        ]);
        $safeOpenUrl = URL::signedRoute('meanly.storefront.orders.safe.open', [
            'order' => $result['order']->uuid,
        ]);

        session([
            'storefront_order_safe.'.$result['order']->uuid => true,
        ]);

        app(MeanlyAnalyticsService::class)->track('checkout.wallet.confirmed', [
            'quantity' => $quantity,
            'amount_minor' => $envelope['amount_minor'],
            'fulfillment_status' => $result['fulfillment_status'] ?? null,
            'safe_status' => $this->orderSafeStatus($result['order'])['status'],
            'asset' => 'RUBT',
        ], [
            'event_type' => 'checkout',
            'surface' => 'storefront',
            'product_id' => $product->id,
            'order_id' => $result['order']->id ?? null,
            'user_id' => $user->id,
            'shop_id' => $product->shop_id,
            'category' => $product->canonical_category ?? $product->category,
            'currency' => 'RUBT',
        ]);

        return response()->json([
            'success' => true,
            'order_id' => $result['order']->order_id,
            'total_rub' => $result['total_rub'],
            'safe_status' => $this->orderSafeStatus($result['order'])['status'],
            'fulfillment_mode' => $availability['fulfillment_mode'],
            'safe_url' => $safeUrl,
            'safe_status_url' => $safeStatusUrl,
            'safe_open_url' => $safeOpenUrl,
            'cabinet_safe_url' => $this->cabinetSafeUrl($result['order']),
            'redirect_url' => $safeUrl,
            'wallet_ledger_entry_id' => $result['wallet_ledger_entry']->id,
            'tx_hash' => $proof['tx_hash'],
            'tx_nonce' => $proof['tx_nonce'],
            'l1_address' => $proof['l1_address'],
        ]);
    }

    public function orderSafe(Request $request, Order $order)
    {
        $this->authorizeOrderSafe($request, $order);

        return view('storefront.order-safe', [
            'order' => $order->loadMissing(['shop', 'items']),
            'safe' => $this->orderSafeStatus($order),
            'statusUrl' => URL::signedRoute('meanly.storefront.orders.safe.status', ['order' => $order->uuid]),
            'openUrl' => URL::signedRoute('meanly.storefront.orders.safe.open', ['order' => $order->uuid]),
        ]);
    }

    public function orderSafeStatusJson(Request $request, Order $order)
    {
        $this->authorizeOrderSafe($request, $order);

        return response()->json($this->orderSafeStatus($order));
    }

    public function openOrderSafe(Request $request, Order $order)
    {
        $this->authorizeOrderSafe($request, $order);

        $safe = $this->orderSafeStatus($order);
        if (! $safe['ready']) {
            return response()->json($safe, $safe['failed'] ? 422 : 202);
        }

        $codes = $this->orderSafeCodes($order);
        if ($codes === []) {
            return response()->json([
                'status' => 'preparing',
                'label' => 'Готовим код',
                'message' => 'Код закреплен за заказом, но еще не готов к выдаче.',
                'codes' => [],
            ], 202);
        }

        $info = $order->info ?? [];
        data_set($info, 'order_safe.opened_at', data_get($info, 'order_safe.opened_at') ?: now()->toJSON());
        data_set($info, 'order_safe.last_opened_at', now()->toJSON());
        data_set($info, 'order_safe.open_count', (int) data_get($info, 'order_safe.open_count', 0) + 1);
        $order->forceFill(['info' => $info])->save();

        app(IntentLedgerService::class)->recordForOrder(
            order: $order,
            eventType: 'ORDER_SAFE_OPEN_INTENT',
            intentType: 'order.safe.open',
            payload: [
                'codes_count' => count($codes),
                'open_count' => (int) data_get($info, 'order_safe.open_count', 0),
                'safe_status' => $safe['status'] ?? null,
                'opened_at' => data_get($info, 'order_safe.last_opened_at'),
            ],
            request: $request,
            user: $request->user(),
            resource: 'order_safe:'.$order->id,
        );

        app(MeanlyAnalyticsService::class)->track('fulfillment.issue.opened', [
            'codes_count' => count($codes),
            'open_count' => (int) data_get($info, 'order_safe.open_count', 0),
            'safe_status' => $safe['status'] ?? null,
        ], [
            'event_type' => 'fulfillment',
            'surface' => 'storefront',
            'order_id' => $order->id,
            'shop_id' => $order->shop_id,
            'currency' => $order->currency,
        ]);

        return response()->json([
            'status' => $safe['status'],
            'label' => 'Выдача открыта',
            'message' => 'Код активации готов. Сотрите карту и сохраните код в надежном месте.',
            'paid' => true,
            'ready' => true,
            'failed' => false,
            'codes' => $codes,
            'scratched' => !empty(data_get($info, 'order_safe.scratch_proof')),
            'scratch_proof' => data_get($info, 'order_safe.scratch_proof'),
        ]);
    }

    public function recordOrderScratch(Request $request, Order $order)
    {
        $this->authorizeOrderSafe($request, $order);

        $data = $request->validate([
            'scratch_proof' => 'required|string|max:255',
        ]);

        $info = $order->info ?? [];
        $wasAlreadyScratched = filled(data_get($info, 'order_safe.scratch_proof'));
        $scratchedAt = data_get($info, 'order_safe.scratched_at') ?: now()->toJSON();
        $scratchProof = $wasAlreadyScratched
            ? (string) data_get($info, 'order_safe.scratch_proof')
            : (string) $data['scratch_proof'];

        data_set($info, 'order_safe.scratched_at', $scratchedAt);
        data_set($info, 'order_safe.scratch_proof', $scratchProof);
        data_set($info, 'order_safe.delivery_status', 'final_delivered');

        $order->status = 'COMPLETED';
        $order->info = $info;
        $order->save();

        $order->loadMissing('items');
        foreach ($order->items as $item) {
            $itemInfo = $item->client_info ?? [];
            data_set($itemInfo, 'delivery_status', 'final_delivered');
            $item->purchase_status = 'completed';
            $item->client_info = $itemInfo;
            $item->save();
        }

        if (! $wasAlreadyScratched) {
            app(IntentLedgerService::class)->recordForOrder($order, 'ORDER_CODE_REVEAL_INTENT', 'voucher.reveal', [
                'items_count' => $order->items->count(),
                'delivery_status' => 'final_delivered',
                'reveal_surface' => 'storefront_inline_scratch_card',
                'revealed_at' => $scratchedAt,
                'scratch_proof_hash' => hash('sha256', $scratchProof),
            ], $request, user: $request->user(), resource: 'order_safe:'.$order->id);
        }

        app(MeanlyAnalyticsService::class)->track('fulfillment.issue.scratched', [
            'items_count' => $order->items->count(),
            'delivery_status' => 'final_delivered',
            'first_reveal' => ! $wasAlreadyScratched,
        ], [
            'event_type' => 'fulfillment',
            'surface' => 'storefront',
            'order_id' => $order->id,
            'shop_id' => $order->shop_id,
            'currency' => $order->currency,
            'mirror_to_ledger' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Скретч-карта стерта, статус заказа переведен в финальную доставку.',
            'status' => 'COMPLETED',
            'scratched_at' => data_get($info, 'order_safe.scratched_at'),
            'scratch_proof' => $scratchProof,
        ]);
    }

    public function orderSafeSupportTicket(Request $request, Order $order, OrderSupportTicketService $supportTickets)
    {
        $this->authorizeOrderSafe($request, $order);

        $safe = $this->orderSafeStatus($order);
        abort_unless($safe['failed'], 404);

        $ticket = $supportTickets->ticketForProblemSafe($order);
        abort_unless($ticket, 404);

        return view('storefront.order-support-ticket', [
            'order' => $order->loadMissing(['shop', 'items.game']),
            'safe' => $safe,
            'ticket' => $ticket->loadMissing(['messages.user', 'messages.seller']),
            'replyUrl' => URL::signedRoute('meanly.storefront.orders.safe.support-ticket.reply', [
                'order' => $order->uuid,
            ]),
            'messagesUrl' => URL::signedRoute('meanly.storefront.orders.safe.support-ticket.messages', [
                'order' => $order->uuid,
            ]),
            'safeUrl' => URL::signedRoute('meanly.storefront.orders.safe.show', [
                'order' => $order->uuid,
            ]),
        ]);
    }

    public function orderSafeSupportTicketMessages(Request $request, Order $order, OrderSupportTicketService $supportTickets)
    {
        $this->authorizeOrderSafe($request, $order);

        $safe = $this->orderSafeStatus($order);
        abort_unless($safe['failed'], 404);

        $ticket = $supportTickets->ticketForProblemSafe($order);
        abort_unless($ticket, 404);

        return response()->json([
            'success' => true,
            'ticket' => [
                'id' => $ticket->id,
                'status' => $ticket->status,
                'subject' => $ticket->subject,
            ],
            'messages' => $this->supportTicketMessages($ticket),
        ]);
    }

    public function replyOrderSafeSupportTicket(Request $request, Order $order, OrderSupportTicketService $supportTickets)
    {
        $this->authorizeOrderSafe($request, $order);

        $safe = $this->orderSafeStatus($order);
        abort_unless($safe['failed'], 404);

        $data = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $ticket = $supportTickets->ticketForProblemSafe($order);
        abort_unless($ticket, 404);

        $ticket->messages()->create([
            'user_id' => $request->user()?->id,
            'message' => $data['message'],
            'is_admin_reply' => false,
        ]);

        $ticket->update([
            'status' => $ticket->status === 'closed' ? 'in_progress' : $ticket->status,
            'last_reply_at' => now(),
        ]);

        app(IntentLedgerService::class)->recordForOrder(
            order: $order,
            eventType: 'SUPPORT_TICKET_REPLY_INTENT',
            intentType: 'support.ticket.reply',
            payload: [
                'ticket_id' => $ticket->id,
                'ticket_status' => $ticket->status,
                'message_hash' => hash('sha256', $data['message']),
                'replied_at' => now()->toIso8601String(),
            ],
            request: $request,
            user: $request->user(),
            scope: 'support.ticket',
            resource: 'ticket:'.$ticket->id,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'messages' => $this->supportTicketMessages($ticket->refresh()),
            ]);
        }

        return redirect()
            ->to(URL::signedRoute('meanly.storefront.orders.safe.support-ticket', ['order' => $order->uuid]))
            ->with('status', 'Сообщение отправлено в поддержку.');
    }

    /**
     * @return array<int, array{id:int,role:string,author:string,message:string,created_at:string|null}>
     */
    private function supportTicketMessages(\App\Models\Ticket $ticket): array
    {
        return $ticket->messages()
            ->with(['user', 'seller'])
            ->orderBy('created_at')
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'role' => $message->is_admin_reply ? 'assistant' : 'user',
                    'author' => $message->is_admin_reply ? 'Поддержка Meanly' : 'Клиент',
                    'message' => (string) $message->message,
                    'created_at' => $message->created_at?->format('d.m.Y H:i'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function checkoutCustomer(Request $request, array $data): array
    {
        $user = $request->user();
        $isGift = $request->boolean('is_gift');
        $accountEmail = trim((string) ($user?->email ?? ''));
        $submittedEmail = trim((string) ($data['email'] ?? ''));
        $requiresRecipientEmail = $isGift || ! $user || $accountEmail === '';

        if ($requiresRecipientEmail && $submittedEmail === '') {
            throw ValidationException::withMessages([
                'email' => 'Укажите email для доставки кода.',
            ]);
        }

        $deliveryEmail = $requiresRecipientEmail ? $submittedEmail : $accountEmail;

        return [
            'name' => $isGift
                ? ($data['name'] ?? null)
                : $this->checkoutBuyerName($user, $data['name'] ?? null),
            'email' => $deliveryEmail,
            'phone' => $data['phone'] ?? null,
            'is_gift' => $isGift,
            'buyer_user_id' => $user?->id,
            'buyer_email' => $accountEmail !== '' ? $accountEmail : null,
            'delivery_email' => $deliveryEmail,
        ];
    }

    private function checkoutBuyerName($user, ?string $fallback = null): ?string
    {
        if (! $user) {
            return $fallback;
        }

        if (method_exists($user, 'getFullName')) {
            $name = trim((string) $user->getFullName());
            if ($name !== '') {
                return $name;
            }
        }

        return $fallback;
    }

    private function submitSimpleL1CheckoutIntent(Request $request, Product $product, int $quantity, array $availability, array $customer): ?array
    {
        $identity = session('simple_l1_identity');

        if (! is_array($identity) || empty($identity['proof_token']) || empty($identity['entity_l1_address'])) {
            return null;
        }

        $payload = [
            'type' => 'commerce.order.checkout',
            'product_id' => $product->id,
            'product_slug' => $product->slug,
            'quantity' => $quantity,
            'fulfillment_mode' => $availability['fulfillment_mode'] ?? null,
            'amount' => (string) ($product->price ?? $product->sell_price ?? ''),
            'currency' => 'RUB',
            'buyer_user_id' => $customer['buyer_user_id'] ?? null,
            'delivery_email_hash' => isset($customer['delivery_email'])
                ? hash('sha256', strtolower((string) $customer['delivery_email']))
                : null,
        ];

        $idempotencyKey = hash('sha256', implode('|', [
            'commerce.order.checkout',
            (string) $identity['entity_l1_address'],
            (string) $product->id,
            (string) $quantity,
            (string) ($customer['delivery_email'] ?? ''),
        ]));

        $response = app(SimpleL1ProtocolClient::class)->submitIntent(
            proofToken: (string) $identity['proof_token'],
            capability: 'marketplace.checkout.create',
            scope: 'marketplace:meanly',
            payload: $payload,
            idempotencyKey: $idempotencyKey,
        );

        if (($response['intent']['status'] ?? null) !== 'accepted') {
            throw ValidationException::withMessages([
                'simple_l1' => 'Simple L1 intent was not accepted by the protocol gateway.',
            ]);
        }

        return $response;
    }

    private function attachSimpleL1IntentProjection(Order $order, ?array $intentResponse): void
    {
        if (! $intentResponse) {
            return;
        }

        $intent = $intentResponse['intent'] ?? [];
        $identity = $intentResponse['identity'] ?? [];
        $info = $order->info ?? [];

        data_set($info, 'simple_l1', [
            'intent_id' => $intent['intent_id'] ?? null,
            'intent_status' => $intent['status'] ?? null,
            'entity_l1_address' => $identity['entity_l1_address'] ?? null,
            'key_l1_address' => $identity['key_l1_address'] ?? null,
            'capability' => $intent['capability'] ?? null,
            'scope' => $intent['scope'] ?? null,
            'payload_hash' => $intent['payload_hash'] ?? null,
            'decision' => $intent['decision'] ?? null,
            'reason_codes' => $intent['reason_codes'] ?? [],
        ]);

        $order->forceFill(['info' => $info])->save();
    }

    private function isRubtWalletPayment(?string $paymentMethod): bool
    {
        return in_array(strtolower(trim((string) $paymentMethod)), [
            'rubt',
            'rubt_balance',
            'rub_token',
            'buyer_wallet_rubt',
            'wallet',
        ], true);
    }

    private function cabinetSafeUrl(Order $order): string
    {
        $anchor = 'safe-'.$order->uuid;

        return route('cabinet.dashboard', ['safe' => $order->uuid]).'#'.$anchor;
    }

    private function authorizeOrderSafe(Request $request, Order $order): void
    {
        if ($request->hasValidSignature()) {
            session(['storefront_order_safe.'.$order->uuid => true]);

            return;
        }

        if (session()->has('storefront_order_safe.'.$order->uuid)) {
            return;
        }

        $user = $request->user();
        if ($user && (
            (int) $order->user_id === (int) $user->id
            || (int) data_get($order->client_info, 'buyer_user_id') === (int) $user->id
        )) {
            return;
        }

        abort(403);
    }

    /**
     * @return array{status:string,label:string,message:string,paid:bool,ready:bool,failed:bool,order_id:string|null,total_rub:float,scratched:bool,scratch_proof:string|null,support_ticket_id?:int|null,support_ticket_url?:string|null,support_ticket_messages_url?:string|null,support_ticket_reply_url?:string|null}
     */
    private function orderSafeStatus(Order $order): array
    {
        if (in_array((string) data_get($order->info, 'order_safe.status'), ['provider_redeem_pending', 'preorder_pending'], true)) {
            app(StorefrontFulfillmentService::class)->pollProviderOrder($order);
            $order->refresh();
        }

        $order->loadMissing('items');
        $paymentStatus = (string) data_get($order->info, 'payment_status', '');
        $paid = $paymentStatus === 'captured'
            || in_array((string) $order->status, ['COMPLETED', 'PROCESSING'], true)
            || (bool) data_get($order->info, 'wallet_ledger_entry_id');
        $safeStatus = (string) data_get($order->info, 'order_safe.status', '');
        $safeSource = (string) data_get($order->info, 'order_safe.source', 'local');
        $isPreorder = $safeStatus === 'preorder_pending'
            || (string) data_get($order->info, 'order_safe.fulfillment_mode') === StorefrontFulfillmentService::FULFILLMENT_PREORDER;
        $hasFailure = in_array((string) $order->status, ['FAILED', 'CANCELLED'], true)
            || $order->items->contains(fn ($item) => (string) $item->purchase_status === 'failed')
            || $safeStatus === 'provider_redeem_failed';
        $hasCodes = $this->orderSafeCodes($order) !== [];

        $scratchProof = data_get($order->info, 'order_safe.scratch_proof');
        $scratched = !empty($scratchProof);

        if ($hasFailure) {
            $support = $this->supportTicketPayload($order);

            return [
                'status' => $safeSource === 'provider' ? 'provider_redeem_failed' : 'failed',
                'label' => 'Нужна проверка',
                'message' => 'Платеж зафиксирован, но выдача кода не завершилась. Тикет поддержки открыт: площадка проверит заказ или оформит возврат.',
                'paid' => $paid,
                'ready' => false,
                'failed' => true,
                'order_id' => $order->order_id,
                'total_rub' => (float) $order->total_amount,
                'scratched' => $scratched,
                'scratch_proof' => $scratchProof,
                'support_ticket_id' => $support['support_ticket_id'],
                'support_ticket_url' => $support['support_ticket_url'],
                'support_ticket_messages_url' => $support['support_ticket_messages_url'],
                'support_ticket_reply_url' => $support['support_ticket_reply_url'],
            ];
        }

        if ($paid && $hasCodes) {
            $status = $safeSource === 'provider' ? 'provider_code_ready' : 'local_code_ready';

            return [
                'status' => $status,
                'label' => 'Сейф готов',
                'message' => 'Платеж подтвержден, код закреплен за заказом и готов к выдаче.',
                'paid' => true,
                'ready' => true,
                'failed' => false,
                'order_id' => $order->order_id,
                'total_rub' => (float) $order->total_amount,
                'scratched' => $scratched,
                'scratch_proof' => $scratchProof,
            ];
        }

        if ($paid) {
            return [
                'status' => $isPreorder ? 'preorder_pending' : ($safeSource === 'provider' ? 'provider_redeem_pending' : 'preparing'),
                'label' => $isPreorder ? 'Предзаказ принят' : 'Готовим код',
                'message' => $isPreorder
                    ? 'Платеж подтвержден. Поставщик принял предзаказ, и сейф обновится, когда код будет готов.'
                    : ($safeSource === 'provider'
                    ? 'Платеж подтвержден. Запрашиваем код у поставщика и обновим сейф, когда выдача завершится.'
                    : 'Платеж подтвержден. Сейф ожидает завершения выдачи кода.'),
                'paid' => true,
                'ready' => false,
                'failed' => false,
                'order_id' => $order->order_id,
                'total_rub' => (float) $order->total_amount,
                'scratched' => $scratched,
                'scratch_proof' => $scratchProof,
            ];
        }

        return [
            'status' => 'paid',
            'label' => 'Ожидаем подтверждение',
            'message' => 'Проверяем подтверждение оплаты и готовим сейф заказа.',
            'paid' => false,
            'ready' => false,
            'failed' => false,
            'order_id' => $order->order_id,
            'total_rub' => (float) $order->total_amount,
            'scratched' => $scratched,
            'scratch_proof' => $scratchProof,
        ];
    }

    /**
     * @return array{support_ticket_id:int|null,support_ticket_url:string|null,support_ticket_messages_url:string|null,support_ticket_reply_url:string|null}
     */
    private function supportTicketPayload(Order $order): array
    {
        $ticket = app(OrderSupportTicketService::class)->ticketForProblemSafe($order);

        if (! $ticket) {
            return [
                'support_ticket_id' => null,
                'support_ticket_url' => null,
                'support_ticket_messages_url' => null,
                'support_ticket_reply_url' => null,
            ];
        }

        return [
            'support_ticket_id' => $ticket->id,
            'support_ticket_url' => URL::signedRoute('meanly.storefront.orders.safe.support-ticket', [
                'order' => $order->uuid,
            ]),
            'support_ticket_messages_url' => URL::signedRoute('meanly.storefront.orders.safe.support-ticket.messages', [
                'order' => $order->uuid,
            ]),
            'support_ticket_reply_url' => URL::signedRoute('meanly.storefront.orders.safe.support-ticket.reply', [
                'order' => $order->uuid,
            ]),
        ];
    }

    /**
     * @return array<int, array{code:string,redeem_url:string,sku:string|null}>
     */
    private function orderSafeCodes(Order $order): array
    {
        $order->loadMissing('items');
        $itemIds = $order->items->pluck('id')->all();
        $safeSource = (string) data_get($order->info, 'order_safe.source', 'local');

        if ($safeSource === 'provider') {
            return $this->providerSafeCodes($order);
        }

        $inventoryCodes = ProductInventory::query()
            ->whereIn('order_item_id', $itemIds)
            ->where('is_used', true)
            ->where('status', 'sold')
            ->get()
            ->map(function (ProductInventory $inventory) {
                $code = trim((string) $inventory->voucher);

                return $code === '' ? null : [
                    'code' => $code,
                    'redeem_url' => route('redeem.code', ['code' => $code]),
                    'sku' => $inventory->sku,
                ];
            })
            ->filter()
            ->values();

        if ($inventoryCodes->isNotEmpty()) {
            return $inventoryCodes->all();
        }

        $providerCodes = $this->providerSafeCodes($order);

        if ($providerCodes !== []) {
            return $providerCodes;
        }

        return $order->items
            ->map(function ($item) {
                $code = trim((string) $item->original_code);

                return $code === '' ? null : [
                    'code' => $code,
                    'redeem_url' => route('redeem.code', ['code' => $code]),
                    'sku' => $item->sku,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{code:string,redeem_url:string|null,sku:string|null}>
     */
    private function providerSafeCodes(Order $order): array
    {
        return $order->items
            ->flatMap(function ($item) {
                return collect((array) data_get($item->client_info, 'provider_redemption.codes', []))
                    ->map(function ($code) use ($item) {
                        $code = trim((string) $code);
                        if ($code === '') {
                            return null;
                        }

                        return [
                            'code' => $code,
                            'redeem_url' => data_get($item->client_info, 'provider_redemption.activation_url'),
                            'sku' => $item->sku,
                        ];
                    })
                    ->filter()
                    ->values();
            })
            ->values()
            ->all();
    }
}
