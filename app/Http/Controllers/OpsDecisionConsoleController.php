<?php

namespace App\Http\Controllers;

use App\Models\SearchDemandRecommendation;
use App\Models\User;
use App\Services\GovernanceEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OpsDecisionConsoleController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $this->ensureOpsAccess();

        return redirect()->route('ops.dashboard', ['tab' => 'decision-console']);
    }

    public function approve(SearchDemandRecommendation $recommendation): RedirectResponse
    {
        $this->ensureOpsAccess();
        $this->markDecision($recommendation, SearchDemandRecommendation::STATUS_APPROVED);

        return back()->with('status', 'Recommendation approved. No system mutation was applied.');
    }

    public function reject(SearchDemandRecommendation $recommendation): RedirectResponse
    {
        $this->ensureOpsAccess();
        $this->markDecision($recommendation, SearchDemandRecommendation::STATUS_REJECTED);

        return back()->with('status', 'Recommendation rejected. No system mutation was applied.');
    }

    private function markDecision(SearchDemandRecommendation $recommendation, string $status): void
    {
        $decision = app(GovernanceEngine::class)->canTransition(Auth::user(), $recommendation, $status);
        abort_unless($decision->allowed, 403, $decision->reason ?? 'Decision transition denied.');

        $recommendation->forceFill([
            'status' => $status,
            'decided_at' => now(),
        ])->save();
    }

    private function ensureOpsAccess(): void
    {
        abort_unless($this->canAccessOps(Auth::user()), 403, 'Доступ в Decision Console ограничен.');
    }

    private function canAccessOps(?User $user): bool
    {
        return $user?->hasOpsSovereignAccess() === true;
    }
}
