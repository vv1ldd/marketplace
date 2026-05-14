<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Currency;

class SimulateRoute extends Command
{
    protected $signature = 'route:simulate {--from=USDT} {--to=} {--amount=}';
    protected $description = 'Simulate capital execution across the liquidity topology to measure market impact and survival probability';

    public function handle()
    {
        $from = $this->option('from');
        $to = $this->option('to');
        $targetAmount = (float)$this->option('amount');

        if (!$to || $targetAmount <= 0) {
            $this->error('Please specify target asset (--to) and execution amount (--amount).');
            return;
        }

        $currency = Currency::where('code', $to)->first();
        if (!$currency) {
            $this->error("Asset [{$to}] not found in Liquidity OS.");
            return;
        }

        $corridorKey = "{$from}/{$to}";
        $corridor = $currency->corridors[$corridorKey] ?? null;

        if (!$corridor) {
            $this->error("Corridor [{$corridorKey}] does not exist.");
            return;
        }

        // --- 1. Load Initial State ---
        $initialCapacity = $corridor['capacity'] ?? 0;
        $makerCount = $corridor['maker_count'] ?? 0;
        $topMakerShare = $corridor['top_maker_share'] ?? 0;
        $baseSlippageBps = $corridor['slippage_bps'] ?? 0;
        $syntheticDependency = $corridor['synthetic_dependency'] ?? 1.0;
        $currentRegime = $corridor['regime'] ?? 'UNKNOWN';
        $failureModes = $corridor['failure_modes'] ?? [];
        $initialRouteScore = $corridor['route_score'] ?? 0;
        $baseRate = (float)$currency->rate_to_usdt; // USDT price in local asset

        // --- 2. Simulation Execution Constraints ---
        $isSyntheticOnly = in_array('SYNTHETIC_ONLY', $failureModes);
        if ($isSyntheticOnly || $initialCapacity <= 0) {
            $this->printSimulationResult(
                'FAILED', 0, 0, 0, 0, 0, 0.99, 0.0, true, 
                "{$currentRegime} -> DEAD", ['NON_EXECUTABLE_CORRIDOR']
            );
            return;
        }

        // --- 3. Run the Execution Physics Engine ---
        $filledAmount = 0;
        $remainingAmount = $targetAmount;
        $consumedMakers = 0;
        $dynamicSlippageBps = $baseSlippageBps;
        $survivalProbability = $corridor['confidence'] ?? 0.5;
        $newFailureModes = [];

        // Determine Top Maker Size
        $topMakerSize = $initialCapacity * $topMakerShare;
        
        // Loop simulation steps (each step represents hitting one liquidity bucket)
        // A naive but realistic simulation: 
        // 1st bucket: Top Maker (if we consume him, we break the back of the market)
        // 2nd+ buckets: Rest of the makers
        
        // Step 1: Hit Top Maker
        if ($remainingAmount > 0) {
            $fill = min($remainingAmount, $topMakerSize);
            $filledAmount += $fill;
            $remainingAmount -= $fill;
            $consumedMakers += 1;
            
            // If we ate the top maker entirely and there's concentration risk, survival drops
            if ($fill >= $topMakerSize && $topMakerShare > 0.4) {
                $survivalProbability *= 0.6; // Heavy hit to confidence
                $dynamicSlippageBps += 50;   // Spread widens immediately
                $newFailureModes[] = 'TOP_MAKER_DEPLETED';
            }
        }

        // Step 2: Hit remaining liquidity
        if ($remainingAmount > 0 && $makerCount > 1) {
            $remainingCapacity = $initialCapacity - $topMakerSize;
            $avgMakerSize = $remainingCapacity / max(1, $makerCount - 1);
            
            while ($remainingAmount > 0 && $remainingCapacity > 0) {
                $fill = min($remainingAmount, $avgMakerSize);
                $filledAmount += $fill;
                $remainingAmount -= $fill;
                $remainingCapacity -= $fill;
                $consumedMakers += 1;
                
                // Exponential slippage as we go deeper into the book
                $dynamicSlippageBps += 15 * ($consumedMakers * 0.5);
                $survivalProbability *= 0.85; // Entropy decay
            }
        }

        // --- 4. Evaluate Final State & Impact ---
        $status = ($filledAmount >= $targetAmount) ? 'FILLED' : 'PARTIAL_FILL';
        if ($filledAmount === 0) $status = 'FAILED';

        $liquidityRemainingRatio = max(0, ($initialCapacity - $filledAmount) / $initialCapacity);
        
        // Fragility = (1 - Survival Probability) * (1 - Remaining Liquidity) + Concentration
        $fragilityAfter = min(1.0, ((1 - $survivalProbability) * (1 - $liquidityRemainingRatio)) + ($topMakerShare * 0.5));

        // State Transition Logic
        $nextRegime = $currentRegime;
        if ($liquidityRemainingRatio < 0.1) $nextRegime = 'DEAD';
        elseif ($fragilityAfter > 0.8 || $dynamicSlippageBps > 300) $nextRegime = 'FRAGMENTED';
        elseif ($fragilityAfter > 0.5) $nextRegime = 'STRESSED';
        
        $stateTransition = "{$currentRegime} -> {$nextRegime}";

        if ($status === 'PARTIAL_FILL') {
            $newFailureModes[] = 'INSUFFICIENT_DEPTH';
        }
        if ($fragilityAfter > 0.8) {
            $newFailureModes[] = 'MARKET_IMPACT_SEVERE';
        }

        $avgPrice = $baseRate * (1 + ($dynamicSlippageBps / 10000));
        $rollbackRecommended = ($survivalProbability < 0.3 || $status === 'PARTIAL_FILL');

        // --- 4.1 Toxicity & Healing Physics ---
        // Toxicity is a function of consumed makers, depth destruction, and resulting fragility.
        $toxicityScore = min(1.0, ($consumedMakers / max(1, $makerCount)) * (1 - $liquidityRemainingRatio) * $fragilityAfter * 3);

        // Exponential Healing Curve: recovery(t) = 1 - e^(-kt)
        // Highly toxic orders cause non-linear recovery delays (Makers retreat to protect inventory).
        $baseRecoverySeconds = ($currentRegime === 'STABLE_PEG') ? 15 : 300;
        $recoveryEtaSeconds = (int)($baseRecoverySeconds * (1 + ($toxicityScore * 15)));
        
        $recoveryRegime = 'INSTANT';
        if ($recoveryEtaSeconds > 60) $recoveryRegime = 'ELASTIC';
        if ($recoveryEtaSeconds > 600) $recoveryRegime = 'SLOW';
        if ($recoveryEtaSeconds > 3600) $recoveryRegime = 'FROZEN';
        if ($status === 'FAILED') $recoveryRegime = 'DEAD';

        $postExecutionState = [
            'remaining_depth_ratio' => round($liquidityRemainingRatio, 4),
            'fragility' => round($fragilityAfter, 4),
            'toxicity_score' => round($toxicityScore, 4),
            'recovery_regime' => $recoveryRegime,
            'recovery_eta_seconds' => $recoveryEtaSeconds,
        ];

        // --- 5. Return JSON Result ---
        $this->printSimulationResult(
            $status, 
            round($filledAmount, 2), 
            round($avgPrice, 4), 
            $dynamicSlippageBps, 
            $consumedMakers, 
            round($survivalProbability, 4), 
            $rollbackRecommended, 
            $stateTransition, 
            array_unique(array_merge($failureModes, $newFailureModes)),
            $postExecutionState
        );
    }

    private function printSimulationResult($status, $filled, $price, $slippage, $makers, $survival, $rollback, $transition, $failures, $postState)
    {
        $result = [
            'execution_request' => [
                'status' => $status,
                'filled_amount_usd' => $filled,
                'avg_execution_price' => $price,
                'simulated_slippage_bps' => $slippage,
                'makers_consumed' => $makers,
                'survival_probability' => $survival,
                'rollback_recommended' => $rollback,
                'state_transition' => $transition,
                'execution_failures' => array_values($failures)
            ],
            'post_execution_state' => $postState
        ];

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
