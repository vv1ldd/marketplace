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
        redirect('/treasury')->send();
        exit;
    }
}
