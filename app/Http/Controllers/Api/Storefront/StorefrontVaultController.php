<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\User;
use App\Services\StorefrontDecisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontVaultController extends Controller
{
    public function index(Request $request, StorefrontDecisionService $decisions): JsonResponse
    {
        $identity = (array) $request->attributes->get('storefront_identity', []);
        $address = strtolower((string) data_get($identity, 'entity_l1_address'));
        abort_if($address === '', 403);
        $user = User::findByEntityL1Address($address);
        if ($user instanceof User) {
            $identity['username'] = $user->username;
            $identity['display_alias'] = $user->publicUsername() ?: ($identity['display_alias'] ?? null);
        }

        $orders = Order::query()
            ->with('items')
            ->latest('id')
            ->limit(100)
            ->get()
            ->filter(fn (Order $order): bool => in_array($address, array_filter([
                strtolower((string) data_get($order->client_info, 'buyer_l1_address')),
                strtolower((string) data_get($order->client_info, 'simple_l1.entity_l1_address')),
                strtolower((string) data_get($order->info, 'simple_l1.entity_l1_address')),
            ]), true))
            ->take(20)
            ->values();

        return response()->json([
            'contract' => [
                'name' => 'storefront-vault',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
                'dto_boundary' => 'transitions_not_conditions',
            ],
            'identity' => $identity,
            'items' => $orders->map(fn (Order $order): array => [
                'type' => 'storefront_vault_item',
                'order_id' => $order->order_id,
                'order_uuid' => $order->uuid,
                'decision' => $decisions->orderSafeDecision($order),
            ])->values(),
            'actions' => [
                'allowed_actions' => ['VIEW_VAULT'],
                'blocked_actions' => [],
                'next_action' => 'VIEW_VAULT',
                'blocking_reason' => null,
            ],
            'authority_surfaces' => $this->authoritySurfacesFor($address, $user),
        ]);
    }

    private function authoritySurfacesFor(string $entityL1Address, ?User $user = null): array
    {
        $user ??= User::findByEntityL1Address($entityL1Address);
        if (! $user) {
            return [];
        }

        $surfaces = [];
        if ($user->legalEntities()->exists() || $user->managedLegalEntities()->exists()) {
            $surfaces[] = [
                'key' => 'merchant',
                'label' => 'Merchant workspace',
                'description' => 'Seller tools granted to this Meanly identity.',
                'href' => '/merchant',
                'grant' => 'meanly.partner.workspace',
            ];
        }

        if ($user->hasOpsSovereignAccess()) {
            $surfaces[] = [
                'key' => 'ops',
                'label' => 'Ops',
                'description' => 'Operations access granted to this sovereign identity.',
                'href' => '/ops',
                'grant' => 'meanly.ops',
            ];
        }

        return $surfaces;
    }
}
