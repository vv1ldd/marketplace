<?php

namespace App\Services;

use App\Models\SearchDemandRecommendation;
use App\Models\User;
use App\Support\GovernanceDecision;

class GovernanceEngine
{
    public function canTransition(User $user, SearchDemandRecommendation $recommendation, string $targetStatus): GovernanceDecision
    {
        if (! $this->transitionAllowed((string) $recommendation->status, $targetStatus)) {
            return GovernanceDecision::deny('INVALID_STATE_TRANSITION');
        }

        $action = $this->actionForTargetStatus($targetStatus);
        if ($action === null) {
            return GovernanceDecision::deny('UNSUPPORTED_TRANSITION');
        }

        $policy = $this->policyFor((string) $recommendation->type, $action);
        $roles = (array) ($policy['roles'] ?? []);

        if ($roles !== [] && ! $user->hasAnyRole($roles)) {
            return GovernanceDecision::deny('ROLE_REQUIRED: '.implode('|', $roles));
        }

        if ((bool) ($policy['requires_sovereign_identity'] ?? true) && ! $user->hasSovereignIdentity()) {
            return GovernanceDecision::deny('SOVEREIGN_IDENTITY_REQUIRED');
        }

        if ((bool) ($policy['dual_control'] ?? false)) {
            return GovernanceDecision::deny('DUAL_CONTROL_PENDING');
        }

        return GovernanceDecision::allow();
    }

    private function transitionAllowed(string $currentStatus, string $targetStatus): bool
    {
        $allowedTargets = (array) config("decision_authorities.transitions.{$currentStatus}", []);

        return in_array($targetStatus, $allowedTargets, true);
    }

    private function actionForTargetStatus(string $targetStatus): ?string
    {
        return match ($targetStatus) {
            SearchDemandRecommendation::STATUS_APPROVED => 'approve',
            SearchDemandRecommendation::STATUS_REJECTED => 'reject',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function policyFor(string $recommendationType, string $action): array
    {
        return (array) (
            config("decision_authorities.types.{$recommendationType}.{$action}")
            ?? config("decision_authorities.default.{$action}")
            ?? []
        );
    }
}
