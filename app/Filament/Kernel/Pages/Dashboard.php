<?php

namespace App\Filament\Kernel\Pages;

use App\Filament\Kernel\Widgets\HealthOverviewWidget;
use App\Filament\Kernel\Widgets\KernelBannerWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'System Kernel: Core Substrate';

    public function getWidgets(): array
    {
        return [
            KernelBannerWidget::class,
            HealthOverviewWidget::class,
        ];
    }
}
