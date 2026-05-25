<?php

namespace App\Filament\Treasury\Pages;

use App\Models\Currency;
use App\Models\LiquidityCorridor;
use App\Services\SovereignCrossRateService;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class SovereignPathfinder extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';
    public static function getNavigationGroup(): ?string
    {
        return __('sovereign.navigation.groups.network');
    }
    
    protected static ?int $navigationSort = 1;

    public function getTitle(): string
    {
        return __('sovereign.navigation.pathfinder');
    }

    protected string $view = 'filament.pages.sovereign-pathfinder';

    public ?array $data = [];
    public array $routes = [];

    public function mount(): void
    {
        $user = auth()->user();
        $currencies = \App\Models\Currency::all();
        $flags = [
            'USD' => '🇺🇸', 'EUR' => '🇪🇺', 'RUB' => '🇷🇺', 'TRY' => '🇹🇷',
            'GBP' => '🇬🇧', 'CNY' => '🇨🇳', 'KZT' => '🇰🇿', 'AED' => '🇦🇪',
            'THB' => '🇹🇭', 'GEL' => '🇬🇪', 'AMD' => '🇦🇲', 'BYN' => '🇧🇾',
        ];
        
        $currencyOptions = $currencies->map(function ($c) use ($flags) {
            $flag = $flags[$c->code] ?? '🏳️';
            return [
                'code' => $c->code,
                'name' => $c->name,
                'flag' => $flag
            ];
        })->toArray();

        // Calculate initial cross-rate matrix
        $sortedCurrencies = ['USD', 'EUR', 'RUB', 'AED', 'TRY', 'GBP', 'CAD', 'SGD', 'KRW'];
        $matrixCurrencies = \App\Models\Currency::where('is_auto_update', true)->pluck('code')->toArray();
        $sorted = [];
        foreach ($sortedCurrencies as $p) {
            if (in_array($p, $matrixCurrencies)) $sorted[] = $p;
        }
        foreach ($matrixCurrencies as $c) {
            if ($c !== 'EZD' && !in_array($c, $sorted)) $sorted[] = $c;
        }
        $matrixCurrencies = array_slice($sorted, 0, 15);
        
        $service = app(SovereignCrossRateService::class);
        $matrix = [];
        foreach ($matrixCurrencies as $rowCode) {
            foreach ($matrixCurrencies as $colCode) {
                if ($rowCode === $colCode) {
                    $matrix[$rowCode][$colCode] = 1.0;
                } else {
                    $matrix[$rowCode][$colCode] = $service->getRate($rowCode, $colCode, 'sovereign');
                }
            }
        }

        response()->view('ops.treasury', [
            'user' => $user,
            'currencyOptions' => $currencyOptions,
            'matrixCurrencies' => $matrixCurrencies,
            'matrix' => $matrix,
        ])->send();
        exit;
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema($this->getFormSchema())
            ->statePath('data');
    }

    protected function getFormSchema(): array
    {
        $flags = [
            'USD' => '🇺🇸', 'EUR' => '🇪🇺', 'RUB' => '🇷🇺', 'TRY' => '🇹🇷',
            'GBP' => '🇬🇧', 'CNY' => '🇨🇳', 'KZT' => '🇰🇿', 'AED' => '🇦🇪',
            'THB' => '🇹🇭', 'GEL' => '🇬🇪', 'AMD' => '🇦🇲', 'BYN' => '🇧🇾',
        ];

        $currencyOptions = Currency::all()->mapWithKeys(function ($c) use ($flags) {
            $flag = $flags[$c->code] ?? '🏳️';
            return [$c->code => "{$flag} {$c->code} — {$c->name}"];
        });

        return [
            Section::make()->schema([
                Grid::make(3)->schema([
                    Select::make('from_code')
                        ->label('From Currency')
                        ->options($currencyOptions)
                        ->required()
                        ->searchable()
                        ->reactive()
                        ->suffixAction(
                            \Filament\Actions\Action::make('swap')
                                ->icon('heroicon-m-arrows-right-left')
                                ->color('primary')
                                ->action(function (\Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) {
                                    $from = $get('from_code');
                                    $to = $get('to_code');
                                    $set('from_code', $to);
                                    $set('to_code', $from);
                                    $this->calculateRoutes();
                                })
                        )
                        ->afterStateUpdated(fn () => $this->calculateRoutes()),

                    Select::make('to_code')
                        ->label('To Currency')
                        ->options($currencyOptions)
                        ->required()
                        ->searchable()
                        ->reactive()
                        ->afterStateUpdated(fn () => $this->calculateRoutes()),

                    TextInput::make('amount')
                        ->label('Amount to Send')
                        ->numeric()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(fn () => $this->calculateRoutes())
                        ->prefixIcon('heroicon-m-banknotes'),
                ]),
            ]),
        ];
    }

    public function calculateRoutes(): void
    {
        $fromCode = $this->data['from_code'] ?? 'RUB';
        $toCode = $this->data['to_code'] ?? 'USD';
        $amount = (float) ($this->data['amount'] ?? 0);

        if (!$fromCode || !$toCode || $amount <= 0) {
            $this->routes = [];
            return;
        }

        $routes = collect();

        $fromCurrency = Currency::where('code', $fromCode)->first();
        $toCurrency = Currency::where('code', $toCode)->first();
        
        if (!$fromCurrency || !$toCurrency) return;

        // Route 1: Institutional Spot (USDT Bridge)
        // spot_rate_usdt is anchored as FIAT per USDT
        if ($fromCurrency->spot_rate_usdt > 0 && $toCurrency->spot_rate_usdt > 0) {
            $crossRate = $toCurrency->spot_rate_usdt / $fromCurrency->spot_rate_usdt; // TO per FROM
            $fee = 0.001; // 0.1% typical spot fee
            $finalAmount = ($amount * $crossRate) * (1 - $fee);
            $obs = min($fromCurrency->observability_score, $toCurrency->observability_score);
            $lsi = max($fromCurrency->liquidity_stress_index, $toCurrency->liquidity_stress_index);
            
            $displayRate = $crossRate < 1 ? "1 {$toCode} = " . number_format(1 / $crossRate, 4) . " {$fromCode}" : "1 {$fromCode} = " . number_format($crossRate, 4) . " {$toCode}";

            // Determine Exchange Sources
            $fromSpotSignal = collect($fromCurrency->telemetry_signals ?? [])->firstWhere('type', 'spot');
            $toSpotSignal = collect($toCurrency->telemetry_signals ?? [])->firstWhere('type', 'spot');
            $fromSource = str_replace('_spot', '', $fromSpotSignal['source'] ?? 'Exchange');
            $toSource = str_replace('_spot', '', $toSpotSignal['source'] ?? 'Exchange');
            $sourceStr = ucfirst($fromSource) . ' / ' . ucfirst($toSource);

            $capacityUsd = 500000; // Spot usually has deep liquidity
            $isOverLimit = ($amount / ($fromCurrency->spot_rate_usdt ?: 1)) > $capacityUsd;

            // Circuit Breaker for Delisted/Stale Pairs (e.g. RUB on Binance)
            $isStale = ($lsi > 50) || ($obs < 0.4);
            $name = $isStale ? 'Institutional Spot (STALE/DELISTED)' : 'Institutional Spot';
            $desc = $isStale 
                ? "WARNING: This pair is likely delisted or has fragmented liquidity on main exchanges. Data is unreliable." 
                : "Direct exchange via USDT spot markets ({$sourceStr}).";

            $routes->push([
                'name' => $name,
                'description' => $desc,
                'final_amount' => $finalAmount,
                'rate_display' => $displayRate,
                'spread' => 0.1,
                'observability' => $obs,
                'lsi' => $lsi * 100,
                'capacity_str' => $isStale ? "N/A" : "$10 — $" . number_format($capacityUsd),
                'is_over_limit' => $isOverLimit || $isStale,
                'methods' => $isStale ? "MARKET SUSPENDED" : "{$fromCode} (".ucfirst($fromSource).") ➔ USDT ➔ {$toCode} (".ucfirst($toSource).")",
                'color' => $isStale ? 'gray' : 'success',
                'trust' => $isStale ? 'BROKEN' : 'High',
                'inbound_rails' => $fromCurrency->inbound_methods ?? [],
                'outbound_rails' => $toCurrency->outbound_methods ?? [],
            ]);
        }

        // Route 2: P2P Shadow Corridor
        $p2pFrom = $fromCurrency->p2p_rate_usdt > 0 ? $fromCurrency->p2p_rate_usdt : ($fromCurrency->spot_rate_usdt > 0 ? $fromCurrency->spot_rate_usdt : $fromCurrency->tradfi_rate);
        $p2pTo = $toCurrency->p2p_rate_usdt > 0 ? $toCurrency->p2p_rate_usdt : ($toCurrency->spot_rate_usdt > 0 ? $toCurrency->spot_rate_usdt : $toCurrency->tradfi_rate);
        
        if ($p2pFrom > 0 && $p2pTo > 0) {
            $crossRate = $p2pTo / $p2pFrom; // TO per FROM
            $fee = 0.015; // 1.5% typical P2P markup
            $finalAmount = ($amount * $crossRate) * (1 - $fee);
            $obs = min($fromCurrency->observability_score, $toCurrency->observability_score) * 0.8;
            $lsi = max($fromCurrency->liquidity_stress_index, $toCurrency->liquidity_stress_index);
            
            $displayRate = $crossRate < 1 ? "1 {$toCode} = " . number_format(1 / $crossRate, 4) . " {$fromCode}" : "1 {$fromCode} = " . number_format($crossRate, 4) . " {$toCode}";

            // Determine P2P Sources
            $fromP2pSignal = collect($fromCurrency->telemetry_signals ?? [])->firstWhere('type', 'p2p');
            $toP2pSignal = collect($toCurrency->telemetry_signals ?? [])->firstWhere('type', 'p2p');
            $fromSource = $fromP2pSignal['source'] ?? 'P2P';
            $toSource = $toP2pSignal['source'] ?? 'P2P';
            
            // Clean up bug from UpdateCurrencyRates where spot source leaked into p2p signal
            if (str_contains(strtolower($fromSource), 'spot')) $fromSource = 'P2P Market';
            if (str_contains(strtolower($toSource), 'spot')) $toSource = 'P2P Market';
            
            $sourceStr = ucfirst($fromSource) . ' / ' . ucfirst($toSource);

            $capacityUsd = (float)($fromP2pSignal['capacity_usd'] ?? 5000);
            $maxFillUsd = (float)($fromP2pSignal['max_fill_usd'] ?? 1000);
            $isOverLimit = ($amount / ($fromCurrency->p2p_rate_usdt ?: 1)) > $maxFillUsd;

            $routes->push([
                'name' => 'Shadow P2P Corridor',
                'description' => "Decentralized peer-to-peer liquidity matching ({$sourceStr}).",
                'final_amount' => $finalAmount,
                'rate_display' => $displayRate,
                'spread' => 1.5,
                'observability' => $obs,
                'lsi' => $lsi * 100,
                'capacity_str' => "$10 — $" . number_format($maxFillUsd),
                'is_over_limit' => $isOverLimit,
                'methods' => "{$fromCode} (".ucfirst($fromSource)." Ask) ➔ USDT ➔ {$toCode} (".ucfirst($toSource)." Bid)",
                'color' => 'warning',
                'trust' => 'Medium',
                'inbound_rails' => ['P2P', 'Transfer', 'Cash'],
                'outbound_rails' => ['P2P', 'Transfer', 'Cash'],
            ]);
        }

        // Route 3: TradFi Forex Bridge (Interbank)
        // tradfi_rate is anchored as RUB per FIAT (e.g. 1 USD = 74.57 RUB)
        $tradfiFrom = $fromCode === 'RUB' ? 1.0 : $fromCurrency->tradfi_rate;
        $tradfiTo = $toCode === 'RUB' ? 1.0 : $toCurrency->tradfi_rate;

        if ($tradfiFrom > 0 && $tradfiTo > 0) {
            $crossRate = $tradfiFrom / $tradfiTo; // TO per FROM
            $fee = 0.03; // 3% typical SWIFT/TradFi spread + fees
            $finalAmount = ($amount * $crossRate) * (1 - $fee);
            
            $displayRate = $crossRate < 1 ? "1 {$toCode} = " . number_format(1 / $crossRate, 4) . " {$fromCode}" : "1 {$fromCode} = " . number_format($crossRate, 4) . " {$toCode}";

            $routes->push([
                'name' => 'TradFi Interbank (SWIFT)',
                'description' => 'Classical banking rails. Subject to compliance.',
                'final_amount' => $finalAmount,
                'rate_display' => $displayRate,
                'spread' => 3.0,
                'observability' => 1.0, // Official rates are 100% observable
                'lsi' => 0, // No liquidity stress on official rates
                'capacity_str' => "$1,000 — $10,000,000",
                'is_over_limit' => $amount < 1000,
                'methods' => "{$fromCode} (Nostro) ➔ Central Bank ➔ {$toCode} (Vostro)",
                'color' => 'gray',
                'trust' => 'Regulated',
                'inbound_rails' => $fromCurrency->inbound_methods ?? [],
                'outbound_rails' => $toCurrency->outbound_methods ?? [],
            ]);
        }

        // Route 4: Verified Operator Corridors (Marketplace Offers)
        // This pulls from the decentralized provider table
        $operators = \App\Models\LiquidityCorridor::where('is_active', true)
            ->where('currency_code', $fromCode)
            ->get();

            foreach ($operators as $op) {
                // Calculate final amount using operator's specific fees
                // leg 1: FROM -> USDT (Operator)
                // leg 2: USDT -> TO (Institutional/Spot Reference)
                $opRateUsdt = $fromCurrency->spot_rate_usdt; // Fallback to spot if op doesn't have custom rate
                $crossRate = ($toCurrency->spot_rate_usdt ?: 1) / ($opRateUsdt ?: 1);
                
                $finalAmount = ($amount * $crossRate) * (1 - ($op->base_fee_percent / 100));
                $finalAmount -= $op->fixed_fee_amount;

                $effectiveRate = $finalAmount > 0 ? $amount / $finalAmount : 0;
                $rateDisplay = $effectiveRate > 0 ? "1 {$toCode} = " . number_format($effectiveRate, 2) . " {$fromCode}" : "N/A";

                $isOverLimit = ($op->min_volume && $amount < $op->min_volume) || ($op->max_volume && $amount > $op->max_volume);
                $capStr = "$" . number_format($op->min_volume ?? 0) . " — $" . number_format($op->max_volume ?? 100000);

                $routes->push([
                    'name' => "Provider: " . ($op->provider_node),
                    'description' => "Verified liquidity corridor via " . ucfirst($op->provider_node) . ".",
                    'final_amount' => $finalAmount,
                    'rate_display' => $rateDisplay,
                    'spread' => (float)$op->base_fee_percent,
                'observability' => 1.0,
                'lsi' => 0,
                'capacity_str' => $capStr,
                'is_over_limit' => $isOverLimit,
                'methods' => "{$fromCode} (".ucfirst($op->provider_node).") ➔ USDT ➔ {$toCode} (Spot)",
                'color' => 'info',
                'trust' => 'Operator',
                'inbound_rails' => $fromCurrency->inbound_methods ?? [],
                'outbound_rails' => $toCurrency->outbound_methods ?? [],
            ]);
        }

        // Route 5: Regional Proxy Hub (Transit Rail)
        // For currencies with high stress (like RUB), we look for transit through CIS hubs
        if ($fromCode === 'RUB' && $toCode !== 'RUB') {
            $hubs = [
                'TJS' => ['name' => 'Tajikistan (SBP/NSPK)', 'fee' => 2.2],
                'KZT' => ['name' => 'Kazakhstan (Halyk/Kaspi)', 'fee' => 2.8],
                'AMD' => ['name' => 'Armenia (IDBank)', 'fee' => 2.5],
                'KGZ' => ['name' => 'Kyrgyzstan (Mbank)', 'fee' => 2.1],
                'UZS' => ['name' => 'Uzbekistan (Humo/Uzcard)', 'fee' => 2.9],
            ];

            foreach ($hubs as $hubCode => $hubInfo) {
                $hubCurrency = Currency::where('code', $hubCode)->first();
                if (!$hubCurrency) continue;

                // Path: RUB -> Hub -> USDT -> TO
                // Find the best "Truth Rate" for the FROM currency
                $fromRate = $fromCurrency->p2p_rate_usdt ?: $fromCurrency->spot_rate_usdt;
                $hubRate = $hubCurrency->p2p_rate_usdt ?: $hubCurrency->spot_rate_usdt;

                if (!$fromRate) continue;
                
                // Effective cross rate: (TO/USDT) / (FROM/USDT)
                $baseCrossRate = ($toCurrency->spot_rate_usdt ?: 1) / ($fromRate ?: 1);
                
                // Apply Hub-specific friction
                $friction = 1 + ($hubInfo['fee'] / 100);
                $finalAmount = ($amount / $friction) * $baseCrossRate;
                
                $effectiveRate = $finalAmount > 0 ? $amount / $finalAmount : 0;
                $rateDisplay = $effectiveRate > 0 ? "1 {$toCode} = " . number_format($effectiveRate, 2) . " {$fromCode}" : "N/A";

                $routes->push([
                    'name' => "Proxy Hub: " . $hubInfo['name'],
                    'description' => "Regional transit via " . $hubCode . " banking proxy. High resilience.",
                    'final_amount' => $finalAmount,
                    'rate_display' => $rateDisplay,
                    'spread' => $hubInfo['fee'],
                    'observability' => 0.9,
                    'lsi' => 5,
                    'capacity_str' => "$500 — $50,000",
                    'is_over_limit' => $amount < 500 || $amount > 50000,
                    'methods' => "{$fromCode} (SBP) ➔ {$hubCode} (Local) ➔ USDT ➔ {$toCode}",
                    'color' => 'violet',
                    'trust' => 'Sovereign',
                    'inbound_rails' => ['SBP', 'NSPK', 'MIR'],
                    'outbound_rails' => $toCurrency->outbound_methods ?? [],
                ]);
            }

            // Route 6: Shadow FX / Parallel Market (Cash/Hawala)
            $fromRate = $fromCurrency->p2p_rate_usdt ?: $fromCurrency->spot_rate_usdt;
            $baseCrossRate = ($toCurrency->spot_rate_usdt ?: 1) / ($fromRate ?: 1);
            $friction = 1.06; // 6% total friction for physical cash movement
            $finalAmount = ($amount / $friction) * $baseCrossRate;

            $effectiveRate = $finalAmount > 0 ? $amount / $finalAmount : 0;

            $routes->push([
                'name' => 'Shadow FX / Parallel Market',
                'description' => 'Over-the-counter physical cash exchange (Hawala style). Zero digital footprint.',
                'final_amount' => $finalAmount,
                'rate_display' => "1 {$toCode} = " . number_format($effectiveRate, 2) . " {$fromCode}",
                'spread' => 6.0,
                'observability' => 0.5,
                'lsi' => 15,
                'capacity_str' => "$5,000 — $1,000,000",
                'is_over_limit' => $amount < 5000,
                'methods' => "RUB (Cash) ➔ OTC Desk (Physical) ➔ {$toCode} (Settlement)",
                'color' => 'warning',
                'trust' => 'Shadow',
                'inbound_rails' => ['Cash', 'Physical'],
                'outbound_rails' => ['Cash', 'Physical'],
            ]);
        }

        $this->routes = $routes->sortByDesc('final_amount')->values()->toArray();
    }
}
