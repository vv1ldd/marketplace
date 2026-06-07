<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Services\MeanlyFirstPartyStorefrontService;
use App\Services\MeanlyRetailCheckoutService;
use App\Services\OrderSupportTicketService;
use App\Services\StorefrontDecisionService;
use App\Services\StorefrontFulfillmentService;
use App\Services\StorefrontTransitionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontCheckoutController extends Controller
{
    public function availability(
        Request $request,
        MeanlyFirstPartyStorefrontService $storefront,
        StorefrontFulfillmentService $fulfillment,
        StorefrontDecisionService $decisions,
    ): JsonResponse {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'nullable|integer|min:1|max:5',
            'client_price_snapshot' => 'nullable',
            'price_snapshot' => 'nullable',
            'stock_hint' => 'nullable',
            'availability_hint' => 'nullable',
            'provider_payload' => 'nullable',
        ]);

        $product = $storefront->marketplaceProductsQuery()
            ->whereKey($data['product_id'])
            ->firstOrFail();
        $availability = $fulfillment->checkoutAvailability($product, (int) ($data['quantity'] ?? 1));

        return response()->json([
            'contract' => [
                'name' => 'storefront-checkout-availability',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
                'dto_boundary' => 'transitions_not_conditions',
            ],
            'decision' => $decisions->checkoutDecision($product, $availability),
        ]);
    }

    public function intent(
        Request $request,
        MeanlyFirstPartyStorefrontService $storefront,
        StorefrontFulfillmentService $fulfillment,
        StorefrontDecisionService $decisions,
    ): JsonResponse {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'nullable|integer|min:1|max:5',
        ]);

        $product = $storefront->marketplaceProductsQuery()
            ->whereKey($data['product_id'])
            ->firstOrFail();
        $quantity = (int) ($data['quantity'] ?? 1);
        $availability = $fulfillment->checkoutAvailability($product, $quantity);
        $decision = $decisions->checkoutDecision($product, $availability);
        $ignoredOverrides = array_values(array_intersect(array_keys($request->all()), [
            'client_price_snapshot',
            'price_snapshot',
            'stock_hint',
            'availability_hint',
            'provider_payload',
        ]));

        return response()->json([
            'contract' => [
                'name' => 'storefront-checkout-intent',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
                'dto_boundary' => 'transitions_not_conditions',
            ],
            'intent' => [
                'idempotency_key' => hash('sha256', implode('|', [
                    'storefront-checkout-intent-v1',
                    $product->id,
                    $quantity,
                    $decision['availability_status'],
                ])),
                'product_id' => (string) $product->id,
                'quantity' => $quantity,
                'decision' => $decision,
                'ignored_client_overrides' => $ignoredOverrides === [] ? null : [
                    'ctg_version' => StorefrontTransitionRegistry::VERSION,
                    'transition_id' => StorefrontTransitionRegistry::IGNORED_CLIENT_OVERRIDE,
                    'fields' => $ignoredOverrides,
                ],
            ],
        ]);
    }

    public function create(
        Request $request,
        MeanlyFirstPartyStorefrontService $storefront,
        StorefrontFulfillmentService $fulfillment,
        StorefrontDecisionService $decisions,
        MeanlyRetailCheckoutService $checkout,
    ): JsonResponse {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'nullable|integer|min:1|max:5',
            'name' => 'nullable|string|max:120',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:64',
            'is_gift' => 'nullable|boolean',
        ]);
        $identity = (array) $request->attributes->get('storefront_identity', []);
        $product = $storefront->marketplaceProductsQuery()
            ->whereKey($data['product_id'])
            ->firstOrFail();
        $quantity = (int) ($data['quantity'] ?? 1);
        $availability = $fulfillment->assertCheckoutAvailability($product, $quantity, null, false);
        $result = $checkout->checkout($product, [
            'name' => $data['name'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'is_gift' => (bool) ($data['is_gift'] ?? false),
            'buyer_l1_address' => data_get($identity, 'entity_l1_address'),
            'delivery_email' => $data['email'],
        ], $quantity, [
            'fulfillment_mode' => $availability['fulfillment_mode'],
            'preorder_acknowledged' => false,
            'availability' => $availability,
            'checkout_payload_hash' => hash('sha256', json_encode([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'buyer_l1_address' => data_get($identity, 'entity_l1_address'),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        ]);

        return response()->json([
            'contract' => [
                'name' => 'storefront-checkout-create',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
                'dto_boundary' => 'transitions_not_conditions',
            ],
            'order' => [
                'order_id' => $result['order']->order_id,
                'order_uuid' => $result['order']->uuid,
                'total_rub' => $result['total_rub'],
                'fulfillment_status' => $result['fulfillment_status'] ?? null,
                'decision' => $decisions->orderSafeDecision($result['order']),
            ],
        ]);
    }

    public function orderSafe(Request $request, Order $order, StorefrontDecisionService $decisions): JsonResponse
    {
        $this->authorizeOrderSafe($request, $order);

        return response()->json([
            'contract' => [
                'name' => 'storefront-order-safe',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
                'dto_boundary' => 'transitions_not_conditions',
            ],
            'decision' => $decisions->orderSafeDecision($order),
        ]);
    }

    public function open(Request $request, Order $order, StorefrontDecisionService $decisions): JsonResponse
    {
        $this->authorizeOrderSafe($request, $order);
        $decision = $decisions->orderSafeDecision($order);
        if (($decision['transition_id'] ?? null) !== StorefrontTransitionRegistry::OPEN_SAFE) {
            return response()->json([
                'contract' => ['name' => 'storefront-order-safe-open', 'version' => 'v1', 'authority' => 'marketplace-commerce'],
                'decision' => $decision,
                'codes' => [],
            ], $decision['failed'] ? 422 : 202);
        }

        $info = $order->info ?? [];
        data_set($info, 'order_safe.opened_at', data_get($info, 'order_safe.opened_at') ?: now()->toJSON());
        data_set($info, 'order_safe.last_opened_at', now()->toJSON());
        data_set($info, 'order_safe.open_count', (int) data_get($info, 'order_safe.open_count', 0) + 1);
        $order->forceFill(['info' => $info])->save();

        return response()->json([
            'contract' => ['name' => 'storefront-order-safe-open', 'version' => 'v1', 'authority' => 'marketplace-commerce'],
            'decision' => $decisions->orderSafeDecision($order->refresh()),
            'codes' => $order->items->map(fn ($item): array => [
                'code' => (string) $item->original_code,
                'sku' => $item->sku,
            ])->filter(fn (array $code): bool => filled($code['code']))->values(),
        ]);
    }

    public function scratch(Request $request, Order $order, StorefrontDecisionService $decisions): JsonResponse
    {
        $this->authorizeOrderSafe($request, $order);
        $data = $request->validate(['scratch_proof' => 'required|string|max:255']);
        $info = $order->info ?? [];
        data_set($info, 'order_safe.scratched_at', data_get($info, 'order_safe.scratched_at') ?: now()->toJSON());
        data_set($info, 'order_safe.scratch_proof', (string) $data['scratch_proof']);
        data_set($info, 'order_safe.delivery_status', 'final_delivered');
        $order->forceFill(['info' => $info, 'status' => 'COMPLETED'])->save();

        return response()->json([
            'contract' => ['name' => 'storefront-order-safe-scratch', 'version' => 'v1', 'authority' => 'marketplace-commerce'],
            'success' => true,
            'decision' => $decisions->orderSafeDecision($order->refresh()),
        ]);
    }

    public function support(Request $request, Order $order, StorefrontDecisionService $decisions, OrderSupportTicketService $support): JsonResponse
    {
        $this->authorizeOrderSafe($request, $order);
        $decision = $decisions->orderSafeDecision($order);
        $ticket = ($decision['failed'] ?? false) ? $support->ticketForProblemSafe($order) : null;

        return response()->json([
            'contract' => ['name' => 'storefront-order-safe-support', 'version' => 'v1', 'authority' => 'marketplace-commerce'],
            'decision' => $decision,
            'ticket' => $ticket ? [
                'id' => $ticket->id,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
            ] : null,
            'actions' => [
                'allowed_actions' => $ticket ? ['VIEW_SUPPORT'] : ['VIEW'],
                'blocked_actions' => $ticket ? [] : ['VIEW_SUPPORT'],
                'next_action' => $ticket ? 'VIEW_SUPPORT' : 'VIEW',
                'blocking_reason' => $ticket ? null : 'order_safe_not_failed',
            ],
        ]);
    }

    private function authorizeOrderSafe(Request $request, Order $order): void
    {
        $identityAddress = strtolower((string) data_get($request->attributes->get('storefront_identity'), 'entity_l1_address'));
        $orderAddress = strtolower((string) (
            data_get($order->client_info, 'buyer_l1_address')
            ?: data_get($order->client_info, 'simple_l1.entity_l1_address')
            ?: data_get($order->info, 'simple_l1.entity_l1_address')
        ));

        abort_if($identityAddress === '' || $orderAddress === '' || ! hash_equals($orderAddress, $identityAddress), 403);
    }
}
