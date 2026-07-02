<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Services\StorefrontVaultTransitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontVaultTransitController extends Controller
{
    public function reveal(
        Request $request,
        string $entitlementId,
        StorefrontVaultTransitService $transit,
    ): JsonResponse {
        $identity = (array) $request->attributes->get('storefront_identity', []);
        $payload = $transit->revealEntitlement($entitlementId, $identity, $request);

        return response()->json([
            'status' => 'success',
            'data' => [
                'secret' => $payload['secret'],
                'entitlement_id' => $payload['entitlement_id'],
                'first_reveal' => $payload['first_reveal'],
            ],
            'contract' => [
                'name' => 'storefront-vault-entitlement-reveal',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
                'dto_boundary' => 'transitions_not_conditions',
            ],
        ]);
    }
}
