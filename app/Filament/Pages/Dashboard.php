<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        return __('admin.widgets.welcome_title');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.dashboard');
    }
    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\SalesChartWidget::class,
            \App\Filament\Widgets\TopSellingProductsWidget::class,
            \App\Filament\Widgets\TopDeficitProductsWidget::class,
        ];
    }
}
