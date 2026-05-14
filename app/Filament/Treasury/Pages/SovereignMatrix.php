<?php

namespace App\Filament\Treasury\Pages;

use Filament\Pages\Page;
use App\Models\Currency;
use App\Services\SovereignCrossRateService;

class SovereignMatrix extends Page
{
    protected string $view = 'filament.pages.sovereign-matrix';
    
    protected static ?int $navigationSort = 2;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-table-cells';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('sovereign.navigation.groups.network');
    }

    public static function getNavigationLabel(): string
    {
        return __('sovereign.navigation.matrix');
    }

    public array $currencies = [];
    public array $matrix = [];

    public function getTitle(): string
    {
        return __('sovereign.navigation.matrix');
    }

    public function mount(SovereignCrossRateService $service)
    {
        // Fetch active currencies
        $allCurrencies = Currency::where('is_auto_update', true)->pluck('code')->toArray();
        
        // Prioritize major currencies for the matrix so they always show first
        $priorities = ['USD', 'EUR', 'RUB', 'AED', 'TRY', 'GBP', 'CAD', 'SGD', 'KRW'];
        $sorted = [];
        
        foreach ($priorities as $p) {
            if (in_array($p, $allCurrencies)) {
                $sorted[] = $p;
            }
        }
        
        foreach ($allCurrencies as $c) {
            // Exclude dead or zero-value currencies like EZD
            if ($c !== 'EZD' && !in_array($c, $sorted)) {
                $sorted[] = $c;
            }
        }
        
        // Take top 15 to keep the matrix visually digestible without horizontal scrolling hell
        $this->currencies = array_slice($sorted, 0, 15);

        // Calculate the cross-rate grid
        foreach ($this->currencies as $rowCode) {
            foreach ($this->currencies as $colCode) {
                if ($rowCode === $colCode) {
                    $this->matrix[$rowCode][$colCode] = 1.0;
                } else {
                    $this->matrix[$rowCode][$colCode] = $service->getRate($rowCode, $colCode, 'sovereign');
                }
            }
        }
    }
}
