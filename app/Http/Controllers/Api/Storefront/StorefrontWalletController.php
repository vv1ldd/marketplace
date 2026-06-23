<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\IdentityBinding;
use App\Models\User;
use App\Contracts\AccountingConsumer;
use App\Services\Accounting\ValueEntryService;
use App\Services\BindingEventRecorder;
use App\Services\BindingProofVerificationService;
use App\Services\ManagedWallet\ManagedWalletProvisioner;
use App\Services\MarketplaceIdentityResolver;
use App\Services\StorefrontWalletService;
use App\Services\VaultIdentityService;
use App\Services\WalletBindingChallengeService;
use App\Services\WalletBindingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    public function bindingEvents(
        Request $request,
        StorefrontWalletService $wallet,
        BindingEventRecorder $bindingEvents,
    ): JsonResponse {
        ['vault' => $vault] = $wallet->resolveContext($request);

        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        $items = $bindingEvents
            ->listForVault((string) $vault->id, null, (int) ($validated['limit'] ?? 25))
            ->map(fn ($event) => $bindingEvents->formatEvent($event))
            ->values()
            ->all();

        return response()->json([
            'contract' => [
                'name' => 'binding-event-list',
                'version' => 'v1',
            ],
            'vault_id' => $vault->id,
            'items' => $items,
        ])->header('Cache-Control', 'private, no-store');
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
            'wallet_public_key' => 'nullable|string|max:256',
            'wallet_provider_id' => 'nullable|string|max:128',
            'wallet_brand' => 'nullable|string|max:128',
            'ton_sign_data' => 'nullable|array',
            'ton_sign_data.domain' => 'required_with:ton_sign_data|string|max:255',
            'ton_sign_data.timestamp' => 'required_with:ton_sign_data|integer',
            'ton_sign_data.address' => 'required_with:ton_sign_data|string|max:128',
            'ton_sign_data.payload' => 'required_with:ton_sign_data|array',
            'ton_sign_data.payload.type' => 'required_with:ton_sign_data|in:text,binary,cell',
            'ton_sign_data.payload.text' => 'required_if:ton_sign_data.payload.type,text|string|max:8192',
        ]);

        $binding = $challenges->verifyChallenge(
            vault: $vault,
            nonce: (string) $validated['nonce'],
            signature: (string) $validated['signature'],
            signedMessage: isset($validated['signed_message']) ? (string) $validated['signed_message'] : null,
            walletPublicKey: isset($validated['wallet_public_key']) ? (string) $validated['wallet_public_key'] : null,
            walletProviderId: isset($validated['wallet_provider_id']) ? (string) $validated['wallet_provider_id'] : null,
            walletBrand: isset($validated['wallet_brand']) ? (string) $validated['wallet_brand'] : null,
            tonSignData: isset($validated['ton_sign_data']) ? (array) $validated['ton_sign_data'] : null,
        );

        return response()->json([
            'success' => true,
            'binding' => $bindings->formatBinding($binding),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function provisionManagedBinding(
        Request $request,
        StorefrontWalletService $wallet,
        VaultIdentityService $vaultIdentities,
        ManagedWalletProvisioner $managedWallets,
        WalletBindingService $bindings,
        MarketplaceIdentityResolver $identityResolver,
    ): JsonResponse {
        ['user' => $user, 'vault' => $vault, 'identity' => $identity] = $wallet->resolveContext($request);
        $user = $identityResolver->ensureUserFromIdentity($identity) ?? $user;
        abort_unless($user instanceof User, 403, 'Marketplace account is required to create a managed wallet.');
        $vaultIdentities->assertOwnedByUser($vault, $user);

        $validated = $request->validate([
            'binding_key' => [
                'required',
                'string',
                'max:64',
                Rule::in($managedWallets->enabledNetworkKeys()),
            ],
        ]);

        $binding = $managedWallets->provision($vault, (string) $validated['binding_key']);

        return response()->json([
            'success' => true,
            'binding' => $bindings->formatBinding($binding),
        ], 201)->header('Cache-Control', 'private, no-store');
    }

    public function importManagedBinding(
        Request $request,
        StorefrontWalletService $wallet,
        VaultIdentityService $vaultIdentities,
        ManagedWalletProvisioner $managedWallets,
        WalletBindingService $bindings,
        MarketplaceIdentityResolver $identityResolver,
    ): JsonResponse {
        ['user' => $user, 'vault' => $vault, 'identity' => $identity] = $wallet->resolveContext($request);
        $user = $identityResolver->ensureUserFromIdentity($identity) ?? $user;
        abort_unless($user instanceof User, 403, 'Marketplace account is required to import a managed wallet.');
        $vaultIdentities->assertOwnedByUser($vault, $user);

        $validated = $request->validate([
            'binding_key' => [
                'required',
                'string',
                'max:64',
                Rule::in($managedWallets->enabledNetworkKeys()),
            ],
            'address' => 'required|string|max:256',
            'secret' => 'required|string|max:4096',
            'secret_format' => 'required|string|max:64',
        ]);

        $binding = $managedWallets->importFromSecret(
            $vault,
            (string) $validated['binding_key'],
            (string) $validated['address'],
            (string) $validated['secret'],
            (string) $validated['secret_format'],
        );

        return response()->json([
            'success' => true,
            'binding' => $bindings->formatBinding($binding),
        ], 201)->header('Cache-Control', 'private, no-store');
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
        WalletBindingService $bindings,
        AccountingConsumer $accounting,
        ValueEntryService $valueEntries,
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

        $creditDecision = null;
        $binding = $proof->identity_binding_id
            ? IdentityBinding::query()->find($proof->identity_binding_id)
            : $bindings->findActiveWalletBinding($vault, (string) $validated['binding_key']);

        if ($binding instanceof IdentityBinding) {
            $creditDecision = $accounting->consume($proof->refresh(), $binding);
        }

        $payload = [
            'success' => true,
            'settlement_proof' => $formatted,
            'proof' => $formatted,
        ];

        if ($creditDecision !== null) {
            $payload['credit_decision'] = $valueEntries->formatCreditDecision($creditDecision);
            $payload['value_entry'] = $valueEntries->activityItem($proof->refresh(), $creditDecision);
        }

        return response()->json($payload)->header('Cache-Control', 'private, no-store');
    }

    public function listValueEntries(
        Request $request,
        StorefrontWalletService $wallet,
        VaultIdentityService $vaultIdentities,
        ValueEntryService $valueEntries,
    ): JsonResponse {
        ['user' => $user, 'vault' => $vault] = $wallet->resolveContext($request);
        abort_unless($user instanceof User, 403);
        $vaultIdentities->assertOwnedByUser($vault, $user);

        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        return response()->json($valueEntries->listForVault($vault, (int) ($validated['limit'] ?? 25)))
            ->header('Cache-Control', 'private, no-store');
    }
}
