<?php

namespace App\Http\Controllers;

use App\Services\Settlement\PaymentDisputeService;
use App\Services\Settlement\PaymentEventTimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OpsPaymentDisputeController extends Controller
{
    private function authorizeOps(): void
    {
        $user = Auth::user();
        abort_unless($user?->hasOpsSovereignAccess() === true, 403);
    }

    public function index(Request $request, PaymentDisputeService $disputes): JsonResponse
    {
        $this->authorizeOps();

        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:50',
            'status' => 'nullable|string|max:32',
        ]);

        return response()->json($disputes->listForOps(
            (int) ($validated['limit'] ?? 25),
            $validated['status'] ?? null,
        ));
    }

    public function show(string $disputeUuid, PaymentDisputeService $disputes): JsonResponse
    {
        $this->authorizeOps();

        return response()->json($disputes->showForOps($disputeUuid));
    }

    public function requestEvidence(string $disputeUuid, PaymentDisputeService $disputes): JsonResponse
    {
        $this->authorizeOps();

        return response()->json($disputes->transitionForOps(
            Auth::user(),
            $disputeUuid,
            \App\Models\IdentityPaymentDispute::STATUS_EVIDENCE_REQUESTED,
        ));
    }

    public function collectEvidence(string $disputeUuid, PaymentDisputeService $disputes): JsonResponse
    {
        $this->authorizeOps();

        return response()->json($disputes->transitionForOps(
            Auth::user(),
            $disputeUuid,
            \App\Models\IdentityPaymentDispute::STATUS_EVIDENCE_COLLECTED,
        ));
    }

    public function review(string $disputeUuid, PaymentDisputeService $disputes): JsonResponse
    {
        $this->authorizeOps();

        return response()->json($disputes->transitionForOps(
            Auth::user(),
            $disputeUuid,
            \App\Models\IdentityPaymentDispute::STATUS_REVIEWED,
        ));
    }

    public function resolve(Request $request, string $disputeUuid, PaymentDisputeService $disputes): JsonResponse
    {
        $this->authorizeOps();

        $validated = $request->validate([
            'decision' => 'required_without:outcome|string|in:approved,rejected,no_action',
            'creates_compensation_intent' => 'sometimes|boolean',
            'outcome' => 'required_without:decision|string|in:refund_approved,refund_denied,no_action',
            'reason' => 'required|string|max:128',
            'resolved_by' => 'nullable|string|max:64',
            'execute_compensation' => 'sometimes|boolean',
        ]);

        return response()->json($disputes->resolveForOps(Auth::user(), $disputeUuid, $validated));
    }

    public function paymentIntentTimeline(string $intentUuid, PaymentEventTimelineService $timeline): JsonResponse
    {
        $this->authorizeOps();

        $intent = \App\Models\IdentityPaymentIntent::query()
            ->where('intent_uuid', $intentUuid)
            ->firstOrFail();

        return response()->json($timeline->build($intent));
    }
}
