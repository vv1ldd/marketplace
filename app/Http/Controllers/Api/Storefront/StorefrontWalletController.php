<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\IdentityBinding;
use App\Models\User;
use App\Services\BindingProofVerificationService;
use App\Services\MarketplaceIdentityResolver;
use App\Services\StorefrontWalletService;
use App\Services\VaultIdentityService;
use App\Services\WalletBindingChallengeService;
use App\Services\WalletBindingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontWalletController extends Controller
{
    public function show(Request $request, StorefrontWalletService $wallet): JsonResponse
    {
        ['identity' => $identity, 'user' => $user, 'vault' => $vault] = $wallet->resolveContext($request);

        return response()->json($wallet->walletSummary($identity, $vault, $user))
            ->header('Cache-Control', 'private, no-store');
    }

    public function bindings(Request $request, StorefrontWalletService $wallet): JsonResponse
    {
        ['vault' => $vault] = $wallet->resolveContext($request);

        return response()->json($wallet->bindingsPayload($vault))
            ->header('Cache-Control', 'private, no-store');
    }

    public function issueBindingChallenge(
        Request $request,
        StorefrontWalletService $wallet,
        VaultIdentityService $vaultIdentities,
        WalletBindingChallengeService $challenges,
        MarketplaceIdentityResolver $identityResolver,
    ): JsonResponse {
        ['user' => $user, 'vault' => $vault, 'identity' => $identity] = $wallet->resolveContext($request);
        $user = $identityResolver->ensureUserFromIdentity($identity) ?? $user;
        abort_unless($user instanceof User, 403, 'Marketplace account is required to link wallets.');
        $vaultIdentities->assertOwnedByUser($vault, $user);

        $validated = $request->validate([
            'binding_type' => 'required|string|in:wallet',
            'binding_key' => 'required|string|max:64',
            'binding_value' => 'required|string|max:256',
            'verification_method' => 'nullable|string|in:signature',
        ]);

        $challenge = $challenges->issueChallenge(
            vault: $vault,
            bindingType: (string) $validated['binding_type'],
            bindingKey: (string) $validated['binding_key'],
            bindingValue: (string) $validated['binding_value'],
            verificationMethod: (string) ($validated['verification_method'] ?? IdentityBinding::METHOD_SIGNATURE),
        );

        return response()->json([
            'success' => true,
            'challenge' => $challenge,
        ], 201)->header('Cache-Control', 'private, no-store');
    }

    public function verifyBindingChallenge(
        Request $request,
        StorefrontWalletService $wallet,
        VaultIdentityService $vaultIdentities,
        WalletBindingChallengeService $challenges,
        WalletBindingService $bindings,
        MarketplaceIdentityResolver $identityResolver,
    ): JsonResponse {
        ['user' => $user, 'vault' => $vault, 'identity' => $identity] = $wallet->resolveContext($request);
        $user = $identityResolver->ensureUserFromIdentity($identity) ?? $user;
        abort_unless($user instanceof User, 403, 'Marketplace account is required to link wallets.');
        $vaultIdentities->assertOwnedByUser($vault, $user);

        $validated = $request->validate([
            'nonce' => 'required|string|max:64',
            'signature' => 'required|string|max:4096',
            'signed_message' => 'nullable|string|max:8192',
        ]);

        $binding = $challenges->verifyChallenge(
            vault: $vault,
            nonce: (string) $validated['nonce'],
            signature: (string) $validated['signature'],
            signedMessage: isset($validated['signed_message']) ? (string) $validated['signed_message'] : null,
        );

        return response()->json([
            'success' => true,
            'binding' => $bindings->formatBinding($binding),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function storeBinding(
        Request $request,
        StorefrontWalletService $wallet,
        VaultIdentityService $vaultIdentities,
        WalletBindingService $bindings,
    ): JsonResponse {
        ['user' => $user, 'vault' => $vault] = $wallet->resolveContext($request);
        abort_unless($user instanceof User, 403);
        $vaultIdentities->assertOwnedByUser($vault, $user);

        $validated = $request->validate([
            'binding_type' => 'required|string|in:wallet',
            'binding_key' => 'required|string|max:64',
            'binding_value' => 'required|string|max:256',
            'verification_method' => 'nullable|string|in:manual,imported',
            'metadata' => 'nullable|array',
        ]);

        $binding = $bindings->createWalletBinding(
            vault: $vault,
            networkKey: (string) $validated['binding_key'],
            address: (string) $validated['binding_value'],
            verificationMethod: (string) ($validated['verification_method'] ?? IdentityBinding::METHOD_MANUAL),
            metadata: (array) ($validated['metadata'] ?? []),
        );

        return response()->json([
            'success' => true,
            'binding' => $bindings->formatBinding($binding),
        ], 201)->header('Cache-Control', 'private, no-store');
    }

    public function destroyBinding(
        Request $request,
        IdentityBinding $identityBinding,
        StorefrontWalletService $wallet,
        VaultIdentityService $vaultIdentities,
        WalletBindingService $bindings,
    ): JsonResponse {
        ['user' => $user, 'vault' => $vault] = $wallet->resolveContext($request);
        abort_unless($user instanceof User, 403);
        $vaultIdentities->assertOwnedByUser($vault, $user);

        if ($identityBinding->binding_type !== IdentityBinding::TYPE_WALLET) {
            return response()->json(['error' => 'Only wallet bindings can be removed through this endpoint.'], 422);
        }

        $identityBinding = $bindings->revokeBinding($vault, $identityBinding);

        return response()->json([
            'success' => true,
            'binding' => $bindings->formatBinding($identityBinding),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function assets(Request $request, StorefrontWalletService $wallet): JsonResponse
    {
        ['identity' => $identity, 'user' => $user, 'vault' => $vault] = $wallet->resolveContext($request);

        return response()->json($wallet->assetsPayload($identity, $vault, $user))
            ->header('Cache-Control', 'private, no-store');
    }

    public function verifyUsdcTransferProof(
        Request $request,
        StorefrontWalletService $wallet,
        VaultIdentityService $vaultIdentities,
        BindingProofVerificationService $proofs,
    ): JsonResponse {
        ['user' => $user, 'vault' => $vault] = $wallet->resolveContext($request);
        abort_unless($user instanceof User, 403);
        $vaultIdentities->assertOwnedByUser($vault, $user);

        $validated = $request->validate([
            'binding_key' => 'required|string|max:64',
            'transaction_hash' => 'required|string|max:80',
            'recipient' => 'required|string|max:66',
            'minimum_amount' => 'required|string|max:48',
            'sender' => 'nullable|string|max:66',
        ]);

        $proof = $proofs->verifyUsdcTransfer($vault, $validated);
        $formatted = $proofs->formatProof($proof);

        return response()->json([
            'success' => true,
            'settlement_proof' => $formatted,
            'proof' => $formatted,
        ])->header('Cache-Control', 'private, no-store');
    }
}
