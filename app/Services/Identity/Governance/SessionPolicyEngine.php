<?php

namespace App\Services\Identity\Governance;

/**
 * Runtime policy evaluation (Invariant 4 — not persisted).
 * Verifier-independent (Invariant 6 — method-blind).
 */
final class SessionPolicyEngine
{
    public const DECISION_ALLOW = 'allow';

    public const DECISION_DENY = 'deny';

    /**
     * @param  list<GovernanceFactor>  $enrolledFactors
     * @param  list<GovernanceSessionEvidence>  $sessionEvidence
     */
    public static function evaluate(
        ?GovernancePolicy $policy,
        array $enrolledFactors,
        array $sessionEvidence,
    ): string {
        if ($policy === null || $sessionEvidence === []) {
            return self::DECISION_DENY;
        }

        $enrolledById = [];

        foreach ($enrolledFactors as $factor) {
            if ($factor->status !== GovernanceFactor::STATUS_ACTIVE) {
                continue;
            }

            $enrolledById[$factor->id] = $factor;
        }

        $provedFactors = [];

        foreach ($sessionEvidence as $evidence) {
            if (! isset($enrolledById[$evidence->factorId])) {
                return self::DECISION_DENY;
            }

            $provedFactors[$evidence->factorId] = $enrolledById[$evidence->factorId];
        }

        $proved = array_values($provedFactors);
        $fulfilledClasses = [];

        foreach ($proved as $factor) {
            $fulfilledClasses[$factor->class] = true;
        }

        $fulfilledClassList = array_keys($fulfilledClasses);

        if ($policy->rule === 'all') {
            foreach ($policy->requiredFactorClasses as $requiredClass) {
                if (! in_array($requiredClass, $fulfilledClassList, true)) {
                    return self::DECISION_DENY;
                }
            }
        }

        $independentDimensionsMet = GovernanceReducer::independentDimensionsMetForFactors($proved);

        if ($independentDimensionsMet < $policy->minimumIndependentDimensions) {
            return self::DECISION_DENY;
        }

        return self::DECISION_ALLOW;
    }
}
