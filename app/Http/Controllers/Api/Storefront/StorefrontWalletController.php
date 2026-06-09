<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontWalletController extends Controller
{
    public function assets(Request $request): JsonResponse
    {
        $identity = (array) $request->attributes->get('storefront_identity', []);
        $address = strtolower((string) data_get($identity, 'entity_l1_address'));
        abort_if($address === '', 403);

        $user = User::findByEntityL1Address($address);
        if ($user instanceof User) {
            $identity['username'] = $user->username;
            $identity['display_alias'] = $user->publicUsername() ?: ($identity['display_alias'] ?? null);
        }

        return response()->json([
            'contract' => [
                'name' => 'storefront-vault-wallet-coins',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
                'network' => 'simple-layer-1',
                'mode' => 'preview',
                'dto_boundary' => 'coins_are_preview_not_transfer_authority',
            ],
            'identity' => $identity,
            'wallet' => [
                'tier' => 'premium-preview',
                'label' => 'Vault Wallet',
                'network_label' => 'Simple Layer 1',
                'custody_note' => 'Vault Wallet shows SL1, MCR, and MLP as coins bound to your identity.',
            ],
            'coins' => [
                [
                    'key' => 'simple-layer-one-preview',
                    'symbol' => 'SL1',
                    'name' => 'Simple Layer One',
                    'amount' => '0',
                    'display_amount' => '0 SL1',
                    'precision' => 9,
                    'transferable' => false,
                    'status' => 'preview_only',
                    'note' => 'Native Simple Layer One coin for future network balances.',
                ],
                [
                    'key' => 'meanly-credits-preview',
                    'symbol' => 'MCR',
                    'name' => 'Meanly Credits',
                    'amount' => '0',
                    'display_amount' => '0 MCR',
                    'precision' => 2,
                    'transferable' => false,
                    'status' => 'preview_only',
                    'note' => 'Primary Vault Wallet credit balance for resolved cashback, adjustments, and marketplace grants.',
                ],
                [
                    'key' => 'meanly-loyalty-points-preview',
                    'symbol' => 'MLP',
                    'name' => 'Meanly Loyalty Points',
                    'amount' => '0',
                    'display_amount' => '0 MLP',
                    'precision' => 0,
                    'transferable' => false,
                    'status' => 'preview_only',
                    'note' => 'Small loyalty unit collected from commerce activity and future rewards.',
                ],
            ],
            'capabilities' => [
                'can_view_coins' => true,
                'can_redeem_credits' => false,
                'can_transfer_coins' => false,
                'can_convert_points' => false,
                'next_action' => 'PREVIEW_COINS',
            ],
        ])->header('Cache-Control', 'private, no-store');
    }
}
