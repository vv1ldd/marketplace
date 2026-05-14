<?php

namespace App\Console\Commands;

use App\Models\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Http\Services\SovereignExchangeService;

class UpdateCurrencyRates extends Command
{
    protected $signature = 'app:update-currency-rates {--code= : Specific currency code to update}';
    protected $description = 'Sovereign Liquidity Terminal: Universal synthesis engine (Supports single-node sync)';

    public function handle()
    {
        // Prevent timeouts when called synchronously from web context (e.g. Filament UI)
        set_time_limit(300);
        
        $specificCode = $this->option('code');
        $this->info("Initializing Sovereign Liquidity Sync" . ($specificCode ? " for {$specificCode}..." : " (Universal Mode)..."));

        try {
            // 1. Establish the Universal Anchor (USDT/USD)
            $fiatResponse = Http::timeout(10)->get("https://open.er-api.com/v6/latest/USD");
            $forexRates = $fiatResponse->json('rates') ?? [];
            
            $anchorCode = 'RUB'; 
            $usdtAnchorPrice = (float)($forexRates[$anchorCode] ?? 92.5);

            // 2. Load Secondary Market Fallbacks
            $aggregatorResponse = Http::timeout(10)->get("https://api.exchangerate-api.com/v4/latest/USD");
            $aggregatorRates = $aggregatorResponse->json('rates') ?? [];

            // 3. Prepare Context
            $context = [
                'forexRates' => $forexRates,
                'aggregatorRates' => $aggregatorRates,
                'usdtAnchorPrice' => $usdtAnchorPrice,
                'exchange' => app(SovereignExchangeService::class),
            ];

            if ($specificCode) {
                $currency = Currency::where('code', strtoupper($specificCode))->first();
                if (!$currency) {
                    $this->error("Currency {$specificCode} not found.");
                    return 1;
                }
                $this->syncCurrency($currency, $context);
            } else {
                $currencies = Currency::where('is_auto_update', true)->get();
                $total = count($currencies);
                $current = 0;

                foreach ($currencies as $currency) {
                    $current++;
                    $this->syncCurrency($currency, $context, $current, $total);
                }
            }

            if (!$specificCode) {
                // 🛡️ Sovereign Ledger: Record a summary of the global sync
                app(\App\Services\LedgerService::class)->recordGlobal('CURRENCY_BATCH_SYNC', null, [
                    'total_nodes' => count($currencies),
                    'timestamp' => now()->toIso8601String(),
                    'anchor_price' => $usdtAnchorPrice,
                ]);
            }

            $this->info("Sovereign Sync Completed successfully!");

        } catch (\Exception $e) {
            $this->error("Fatal Sync Error: " . $e->getMessage());
            Log::error("Fatal Universal Sync Error: " . $e->getMessage());
        }
    }

    /**
     * Core Synthesis Logic for a single Currency Node
     */
    public function syncCurrency(Currency $currency, array $context, int $current = 1, int $total = 1)
    {
        $code = $currency->code;
        $forexRates = $context['forexRates'];
        $aggregatorRates = $context['aggregatorRates'];
        $usdtAnchorPrice = $context['usdtAnchorPrice'];
        $exchange = $context['exchange'];

        try {
            $signals = [];
            
            // --- TIER 1: Sovereign & Institutional ---
            $institutionalRate = (float)($forexRates[$code] ?? $aggregatorRates[$code] ?? 0);
            if ($institutionalRate > 0) {
                $signals[] = [
                    'type' => 'reference', 'tier' => 1, 'rate' => $institutionalRate, 
                    'source' => 'forex_interbank', 'weight' => 0.6, 'timestamp' => now()->toIso8601String()
                ];
            }

            $pegs = [
                'AED' => 3.6725, 'SAR' => 3.75, 'OMR' => 0.3845, 
                'BHD' => 0.376, 'KWD' => 0.306, 'QAR' => 3.64, 
                'JOD' => 0.709, 'HKD' => 7.82, 'IQD' => 1310.0,
            ];
            if (isset($pegs[$code])) {
                $signals[] = [
                    'type' => 'sovereign_peg', 'tier' => 1, 'rate' => $pegs[$code], 
                    'source' => 'official_peg', 'weight' => 1.2, 'timestamp' => now()->toIso8601String()
                ];
                if ($institutionalRate <= 0) $institutionalRate = $pegs[$code];
            }

            // --- TIER 2: Active Market Audit ---
            $audit = $exchange->getBestP2PRate($code);
            $spotPrice = (float)($audit['spot_rate'] ?? 0);
            $p2pPrice = (float)($audit['p2p_rate'] ?? 0);
            $p2pAds = (int)($audit['p2p_ads'] ?? 0);
            $capacityUsd = (float)($audit['capacity_usd'] ?? 0);
            $maxFillUsd = (float)($audit['max_fill_usd'] ?? 0);
            $latencyMs = (int)($audit['latency_ms'] ?? 0);
            $finalSource = $audit['source'] ?? 'synthetic_fallback';

            if ($spotPrice > 0) {
                $signals[] = [
                    'type' => 'spot', 'tier' => 2, 'rate' => $spotPrice, 
                    'source' => $finalSource, 'weight' => 1.0, 
                    'latency_ms' => $latencyMs, 'timestamp' => time()
                ];
            }
            if ($p2pPrice > 0) {
                $signals[] = [
                    'type' => 'p2p', 'tier' => 3, 'rate' => $p2pPrice, 
                    'source' => $audit['source'] ?? 'p2p_market', 'weight' => 0.8,
                    'capacity_usd' => $capacityUsd, 'max_fill_usd' => $maxFillUsd,
                    'latency_ms' => $latencyMs, 'timestamp' => time()
                ];
            }

            // --- 4. ANALYTICS & EXECUTION REALITY ---
            $visibleCount = count($signals);
            $hasLiveMarket = ($spotPrice > 0 || $p2pPrice > 0);
            $targetSignals = in_array($code, ['USD', 'EUR', 'GBP', 'RUB', 'TRY']) ? 3 : 2;
            
            // 4.1. Observability Decomposition
            $obsAgreement = 1.0;
            if ($visibleCount > 1) {
                $rates = array_column($signals, 'rate');
                $avg = array_sum($rates) / count($rates);
                $maxDev = 0;
                foreach ($rates as $r) { $maxDev = max($maxDev, abs($r - $avg) / $avg); }
                $obsAgreement = max(0, 1 - ($maxDev * 5)); // Penalty for divergence
            }

            $obsFreshness = 1.0;
            if ($latencyMs > 2000) {
                $obsFreshness = max(0.5, 1 - (($latencyMs - 2000) / 10000));
            }

            $observability = ($visibleCount >= $targetSignals) ? 1.0 : round($visibleCount / $targetSignals, 2);
            $observability = $observability * $obsAgreement * $obsFreshness;

            // --- 5. CONSENSUS & ANCHOR FIX ---
            $totalWeight = array_sum(array_column($signals, 'weight'));
            $weightedSum = 0;
            foreach ($signals as $s) { $weightedSum += ($s['rate'] * $s['weight']); }
            
            $consensusRate = ($totalWeight > 0) ? ($weightedSum / $totalWeight) : $institutionalRate;

            // 4.2. Volatility Snapshot
            $prevPrice = (float)$currency->price_last;
            $volatility1h = ($prevPrice > 0) ? (abs($consensusRate - $prevPrice) / $prevPrice) : 0;

            // 4.3. Market Health & Anomaly Detection
            $divergence = ($institutionalRate > 0) ? abs($consensusRate - $institutionalRate) / $institutionalRate : 0;
            $anomalyScore = min(1.0, ($divergence * 3) + ($volatility1h * 10)); 
            
            $marketHealth = 'HEALTHY';
            if ($anomalyScore > 0.4) $marketHealth = 'DEGRADED';
            if ($anomalyScore > 0.7) $marketHealth = 'STRESSED';
            if ($visibleCount === 0) $marketHealth = 'DISCONNECTED';

            // 4.4. Market Regime Identification
            $regime = 'FLOATING';
            $pegs = ['AED', 'SAR', 'QAR', 'HKD'];
            if (in_array($code, $pegs) && $divergence < 0.02) $regime = 'STABLE_PEG';
            elseif ($divergence > 0.05) $regime = 'DIVERGENT';
            elseif ($volatility1h > 0.01) $regime = 'VOLATILE';
            elseif ($capacityUsd < 5000 && $hasLiveMarket) $regime = 'THIN';
            elseif (!$hasLiveMarket) $regime = 'DARK';

            // Calculate Continuous LSI
            $lsi = min(1.0, ($divergence * 0.5) + (1 - $obsAgreement) + (1 - $obsFreshness));

            // Confidence Index (Probabilistic)
            $capacityFactor = min(1.0, $capacityUsd / 50000); // Max confidence requires $50k depth
            $confidence = max(0, $obsAgreement * $obsFreshness * (0.5 + ($capacityFactor * 0.5)) * (1 - $lsi));

            // Calculate Slippage Estimation (BPS)
            $slippageBps = $hasLiveMarket ? 20 : 150; 
            if ($p2pPrice > 0 && $p2pAds < 10) $slippageBps += (10 - $p2pAds) * 15;
            if ($capacityUsd < 10000 && $capacityUsd > 0) $slippageBps += 50; 

            // Execution Intelligence & Routing Metrics
            $isSynthetic = ($p2pPrice <= 0);
            $topMakerShare = ($capacityUsd > 0) ? round($maxFillUsd / $capacityUsd, 2) : 0;
            $syntheticDependency = $isSynthetic ? 1.0 : round(min(1.0, $divergence * 10), 2);
            
            // Route Quality Score (0-100)
            $baseRouteScore = ($confidence * 40) + ((1 - $lsi) * 40) + ($capacityFactor * 20);
            $routeScore = max(0, min(100, round($baseRouteScore - ($slippageBps / 10), 2)));

            // 4.5. Route Diagnostics & Explainability
            $failureModes = [];
            if ($isSynthetic) $failureModes[] = 'SYNTHETIC_ONLY';
            if ($capacityUsd > 0 && $capacityUsd < 5000) $failureModes[] = 'LOW_DEPTH';
            if ($slippageBps > 50) $failureModes[] = 'HIGH_SPREAD';
            if ($topMakerShare > 0.5) $failureModes[] = 'CONCENTRATION_RISK';
            if ($p2pPrice > 0 && $p2pAds < 3) $failureModes[] = 'NO_REAL_MAKERS';
            if ($divergence > 0.05) $failureModes[] = 'REFERENCE_DIVERGENCE';

            $executionGrade = 'UNSAFE';
            if ($routeScore >= 90 && empty($failureModes)) $executionGrade = 'A+';
            elseif ($routeScore >= 80 && count($failureModes) <= 1) $executionGrade = 'A';
            elseif ($routeScore >= 60 && count($failureModes) <= 2) $executionGrade = 'B';
            elseif ($routeScore >= 40) $executionGrade = 'C';

            $routeType = 'P2P';
            if ($isSynthetic) $routeType = 'SYNTHETIC';
            elseif ($regime === 'STABLE_PEG') $routeType = 'PEGGED';
            elseif ($spotPrice > 0 && $p2pPrice <= 0) $routeType = 'SPOT_DIRECT';

            // 4.6. Liquidity Topology (Corridors)
            $corridors = [
                'USDT/' . $code => [
                    'source' => $finalSource,
                    'route_type' => $routeType,
                    'regime' => $regime,
                    'execution_grade' => $executionGrade,
                    'route_score' => $routeScore,
                    'capacity' => $capacityUsd,
                    'maker_count' => $p2pAds,
                    'top_maker_share' => $topMakerShare,
                    'slippage_bps' => $slippageBps,
                    'synthetic_dependency' => $syntheticDependency,
                    'failure_modes' => $failureModes,
                    'health_half_life_seconds' => $hasLiveMarket ? 180 : 3600,
                    'confidence' => round($confidence, 2)
                ]
            ];

            $this->info("[{$current}/{$total}] {$code}: Grade={$executionGrade} | Score={$routeScore} | " . implode(', ', $failureModes));

            // --- 6. PERSISTENCE & TELEMETRY ---

            // RUB as Numéraire Logic (Fixed Anchor)
            $rateToRub = ($consensusRate > 0) ? ($usdtAnchorPrice / $consensusRate) : 0;
            $officialInRub = ($institutionalRate > 0) ? ($usdtAnchorPrice / $institutionalRate) : 0;

            $buyRate = $isSynthetic ? ($rateToRub * 1.015) : ($rateToRub * (1 + ($slippageBps/10000) + 0.002));
            $sellRate = $rateToRub;

            // Record the high-fidelity audit event (Telemetry Bus)
            $event = \App\Models\CurrencyTelemetryEvent::create([
                'currency_code' => $code,
                'executable_rate' => $consensusRate,
                'confidence_score' => $confidence,
                'trust_tier' => empty($signals) ? 5 : min(array_column($signals, 'tier')),
                'evidence_graph' => [
                    'signals' => $signals,
                    'is_synthetic' => $isSynthetic,
                    'latency_ms' => $latencyMs,
                    'pair' => "USDT/{$code}"
                ],
                'execution_reality' => [
                    'market_regime' => $regime,
                    'market_health' => $marketHealth,
                    'anomaly_score' => $anomalyScore,
                    'lsi' => $lsi,
                    'obs_decomposition' => [
                        'agreement' => $obsAgreement,
                        'freshness' => $obsFreshness,
                    ],
                    'capacity_usd' => $capacityUsd,
                    'estimated_slippage_bps' => $slippageBps,
                    'corridors' => $corridors
                ],
                'trigger_source' => $total === 1 ? 'manual' : 'cron',
            ]);

            $currency->update([
                'base_asset'      => 'USDT',
                'quote_asset'     => $code,
                'price_last'      => $consensusRate,
                'official_rate'   => $officialInRub,
                'tradfi_rate'     => $officialInRub,
                'rate_to_rub'     => $rateToRub,
                'shadow_buy_rate' => $buyRate,
                'shadow_sell_rate'=> $sellRate,
                'p2p_rate_usdt'   => $p2pPrice,
                'spot_rate_usdt'  => $spotPrice,
                'p2p_source'      => $isSynthetic ? 'synthetic_fallback' : 'market_consensus',
                'liquidity_stress_index' => $lsi,
                'observability_score'    => $observability,
                'obs_agreement'          => $obsAgreement,
                'obs_freshness'          => $obsFreshness,
                'volatility_1h'          => $volatility1h,
                'market_regime'          => $regime,
                'execution_ready'        => ($confidence > 0.4 && $hasLiveMarket),
                'corridors'              => $corridors,
                'confidence_score'       => $confidence,
                'max_executable_size'    => $maxFillUsd,
                'estimated_slippage'     => $slippageBps / 10000,
                'has_spot_liquidity'     => ($spotPrice > 0),
                'telemetry_signals'      => $signals,
                'telemetry_updated_at'   => now(),
            ]);

            // --- 7. SOVEREIGN DETERMINISTIC LEDGER (MDK) ---
            // 🛡️ Logic: Only ledgerize individual nodes if they are CORE or have significant stress/movement
            $coreCurrencies = ['USD', 'EUR', 'RUB', 'TRY', 'KZT', 'AED', 'USDT', 'GBP'];
            $isCore = in_array($code, $coreCurrencies);
            $isStressed = ($lsi > 0.4 || $volatility1h > 0.02);

            if ($isCore || $isStressed || $total === 1) {
                app(\App\Services\LedgerService::class)->recordGlobal(
                    'currency.synchronized',
                    $currency,
                    [
                        'pair' => "USDT/{$code}",
                        'price' => $consensusRate,
                        'lsi' => $lsi,
                        'obs' => $observability,
                        'conf' => $confidence,
                        'vol' => $volatility1h,
                        'is_core' => $isCore,
                        'is_stressed' => $isStressed,
                        'event_id' => $event->id
                    ]
                );
            }

            return true;
        } catch (\Exception $e) {
            $this->error("  Failed {$code}: " . $e->getMessage());
            return false;
        }
    }
}
