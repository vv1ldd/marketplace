<?php

namespace App\Filament\Audit\Pages;

use App\Filament\Audit\Widgets\LedgerBreakdownWidget;
use App\Filament\Audit\Widgets\LedgerIntegrityWidget;
use App\Filament\Audit\Widgets\TribunalBannerWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        return 'Tribunal Control Substrate';
    }

    public static function getNavigationLabel(): string
    {
        return 'The Epistemic Matrix';
    }

    public function getWidgets(): array
    {
        return [
            TribunalBannerWidget::class,
            LedgerIntegrityWidget::class,
            LedgerBreakdownWidget::class,
        ];
    }
}
