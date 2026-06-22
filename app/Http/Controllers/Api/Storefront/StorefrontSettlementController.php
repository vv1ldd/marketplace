<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Settlement\IdentityPaymentService;
use App\Services\Settlement\PaymentDisputeService;
use App\Services\Settlement\PaymentEventTimelineService;
use App\Services\Settlement\IdentityStatementService;
use App\Services\Settlement\RecipientResolverService;
use App\Services\StorefrontWalletService;
use App\Services\VaultIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontSettlementController extends Controller
{
    /**
     * Identity resolution boundary (v3a). Returns capability graph — not a wallet address lookup.
     */
    public function resolveRecipient(Request $request, RecipientResolverService $resolver): JsonResponse
    {
        $validated = $request->validate([
            'alias' => 'required|string|max:64',
        ]);

        $payload = $resolver->resolve((string) $validated['alias']);

        return response()->json($payload)
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * Identity payment boundary (v3b). Intent → routing → optional execution → accounting.
     */
    public function createPaymentIntent(
        Request $request,
        StorefrontWalletService $wallet,
        VaultIdentityService $vaultIdentities,
        IdentityPaymentService $payments,
    ): JsonResponse {
        ['user' => $user, 'vault' => $vault, 'identity' => $identity] = $wallet->resolveContext($request);
        abort_unless($user instanceof \App\Models\User, 403);
        $vaultIdentities->assertOwnedByUser($vault, $user);

        $validated = $request->validate([
            'to_alias' => 'required_without:reversal_of_intent_id|nullable|string|max:64',
            'asset' => 'required_without:reversal_of_intent_id|nullable|string|max:16',
            'amount' => 'required_without:reversal_of_intent_id|nullable|string|max:48',
            'execute' => 'sometimes|boolean',
            'idempotency_key' => 'nullable|string|max:128',
            'reversal_of_intent_id' => 'nullable|uuid',
            'reversal_reason' => 'nullable|string|max:128',
        ]);

        $payload = $payments->create($identity, $vault, $user, $validated);

        return response()->json($payload, 201)
            ->header('Cache-Control', 'private, no-store');
    }

    public function listPaymentIntents(
        Request $request,
        StorefrontWalletService $wallet,
        VaultIdentityService $vaultIdentities,
        IdentityPaymentService $payments,
    ): JsonResponse {
        ['user' => $user, 'vault' => $vault] = $wallet->resolveContext($request);
        abort_unless($user instanceof \App\Models\User, 403);
        $vaultIdentities->assertOwnedByUser($vault, $user);

        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        $payload = $payments->listForVault($vault, (int) ($validated['limit'] ?? 25));

        return response()->json($payload)
            ->header('Cache-Control', 'private, no-store');
    }

    public function statement(
        Request $request,
        StorefrontWalletService $wallet,
        VaultIdentityService $vaultIdentities,
        IdentityStatementService $statement,
    ): JsonResponse {
        ['user' => $user, 'vault' => $vault] = $wallet->resolveContext($request);
        abort_unless($user instanceof \App\Models\User, 403);
        $vaultIdentities->assertOwnedByUser($vault, $user);

        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'asset' => 'sometimes|string|max:16',
        ]);

        $payload = $statement->forVaultParticipant(
            $vault,
            \Illuminate\Support\Carbon::parse((string) $validated['from'])->startOfDay(),
            \Illuminate\Support\Carbon::parse((string) $validated['to'])->endOfDay(),
            isset($validated['asset']) ? (string) $validated['asset'] : null,
        );

        return response()->json($payload)
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * Execute a previously routed payment intent against its frozen snapshot (T1).
     */
    public function executePaymentIntent(
        Request $request,
        string $intentUuid,
        StorefrontWalletService $wallet,
        VaultIdentityService $vaultIdentities,
        IdentityPaymentService $payments,
    ): JsonResponse {
        ['user' => $user, 'vault' => $vault] = $wallet->resolveContext($request);
        abort_unless($user instanceof \App\Models\User, 403);
        $vaultIdentities->assertOwnedByUser($vault, $user);

        $intent = \App\Models\IdentityPaymentIntent::query()
            ->where('intent_uuid', $intentUuid)
            ->firstOrFail();

        $payload = $payments->execute($vault, $intent);

        return response()->json($payload)
            ->header('Cache-Control', 'private, no-store');
    }

    public function openDispute(
        Request $request,
        string $intentUuid,
        StorefrontWalletService $wallet,
        VaultIdentityService $vaultIdentities,
        PaymentDisputeService $disputes,
    ): JsonResponse {
        ['user' => $user, 'vault' => $vault] = $wallet->resolveContext($request);
        abort_unless($user instanceof \App\Models\User, 403);
        $vaultIdentities->assertOwnedByUser($vault, $user);

        $validated = $request->validate([
            'reason' => 'required|string|max:128',
            'evidence_required' => 'sometimes|boolean',
        ]);

        $payload = $disputes->open(
            $vault,
            $user,
            $intentUuid,
            (string) $validated['reason'],
            (bool) ($validated['evidence_required'] ?? true),
        );

        return response()->json($payload, 201)
            ->header('Cache-Control', 'private, no-store');
    }

    public function requestDisputeEvidence(
        Request $request,
        string $disputeUuid,
        StorefrontWalletService $wallet,
        VaultIdentityService $vaultIdentities,
        PaymentDisputeService $disputes,
    ): JsonResponse {
        ['user' => $user, 'vault' => $vault] = $wallet->resolveContext($request);
        abort_unless($user instanceof \App\Models\User, 403);
        $vaultIdentities->assertOwnedByUser($vault, $user);

        $payload = $disputes->requestEvidence($vault, $user, $disputeUuid);

        return response()->json($payload)
            ->header('Cache-Control', 'private, no-store');
    }

    public function collectDisputeEvidence(
        Request $request,
        string $disputeUuid,
        StorefrontWalletService $wallet,
        VaultIdentityService $vaultIdentities,
        PaymentDisputeService $disputes,
    ): JsonResponse {
        ['user' => $user, 'vault' => $vault] = $wallet->resolveContext($request);
        abort_unless($user instanceof \App\Models\User, 403);
        $vaultIdentities->assertOwnedByUser($vault, $user);

        $payload = $disputes->collectEvidence($vault, $user, $disputeUuid);

        return response()->json($payload)
            ->header('Cache-Control', 'private, no-store');
    }

    public function reviewDispute(
        Request $request,
        string $disputeUuid,
        StorefrontWalletService $wallet,
        VaultIdentityService $vaultIdentities,
        PaymentDisputeService $disputes,
    ): JsonResponse {
        ['user' => $user, 'vault' => $vault] = $wallet->resolveContext($request);
        abort_unless($user instanceof \App\Models\User, 403);
        $vaultIdentities->assertOwnedByUser($vault, $user);

        $payload = $disputes->review($vault, $user, $disputeUuid);

        return response()->json($payload)
            ->header('Cache-Control', 'private, no-store');
    }

    public function resolveDispute(
        Request $request,
        string $disputeUuid,
        StorefrontWalletService $wallet,
        VaultIdentityService $vaultIdentities,
        PaymentDisputeService $disputes,
    ): JsonResponse {
        ['user' => $user, 'vault' => $vault] = $wallet->resolveContext($request);
        abort_unless($user instanceof \App\Models\User, 403);
        $vaultIdentities->assertOwnedByUser($vault, $user);

        $validated = $request->validate([
            'decision' => 'required_without:outcome|string|in:approved,rejected,no_action',
            'creates_compensation_intent' => 'sometimes|boolean',
            'outcome' => 'required_without:decision|string|in:refund_approved,refund_denied,no_action',
            'reason' => 'required|string|max:128',
            'resolved_by' => 'nullable|string|max:64',
            'execute_compensation' => 'sometimes|boolean',
        ]);

        $payload = $disputes->resolve($vault, $user, $disputeUuid, $validated);

        return response()->json($payload)
            ->header('Cache-Control', 'private, no-store');
    }

    public function showDispute(
        Request $request,
        string $disputeUuid,
        StorefrontWalletService $wallet,
        VaultIdentityService $vaultIdentities,
        PaymentDisputeService $disputes,
    ): JsonResponse {
        ['user' => $user, 'vault' => $vault] = $wallet->resolveContext($request);
        abort_unless($user instanceof \App\Models\User, 403);
        $vaultIdentities->assertOwnedByUser($vault, $user);

        $payload = $disputes->show($vault, $disputeUuid);

        return response()->json($payload)
            ->header('Cache-Control', 'private, no-store');
    }

    public function paymentIntentTimeline(
        Request $request,
        string $intentUuid,
        StorefrontWalletService $wallet,
        VaultIdentityService $vaultIdentities,
        PaymentEventTimelineService $timeline,
    ): JsonResponse {
        ['user' => $user, 'vault' => $vault] = $wallet->resolveContext($request);
        abort_unless($user instanceof \App\Models\User, 403);
        $vaultIdentities->assertOwnedByUser($vault, $user);

        $payload = $timeline->forVaultParticipant($vault, $intentUuid);

        return response()->json($payload)
            ->header('Cache-Control', 'private, no-store');
    }

    public function paymentIntentDispute(
        Request $request,
        string $intentUuid,
        StorefrontWalletService $wallet,
        VaultIdentityService $vaultIdentities,
        PaymentDisputeService $disputes,
    ): JsonResponse {
        ['user' => $user, 'vault' => $vault] = $wallet->resolveContext($request);
        abort_unless($user instanceof \App\Models\User, 403);
        $vaultIdentities->assertOwnedByUser($vault, $user);

        $payload = $disputes->disputeForPaymentIntent($vault, $intentUuid);

        return response()->json([
            'contract' => [
                'name' => 'payment-intent-dispute',
                'version' => PaymentDisputeService::CONTRACT_VERSION,
            ],
            'dispute' => $payload,
        ])->header('Cache-Control', 'private, no-store');
    }
}
