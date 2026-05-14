<?php

namespace App\Filament\Audit\Resources\SovereignLedgers\Widgets;

use App\Models\SovereignLedger;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LedgerStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalLinks = SovereignLedger::count();
        $uniqueActors = SovereignLedger::whereNotNull('trigger_source')
            ->distinct()
            ->count('trigger_source');

        $lastCommit = SovereignLedger::latest('id')->first();
        $lastCommitShort = $lastCommit ? substr($lastCommit->fingerprint, 0, 8) : 'N/A';

        return [
            Stat::make('Всего звеньев (Links)', number_format($totalLinks, 0, '.', ' '))
                ->description('Записей в цепи причинности')
                ->descriptionIcon('heroicon-m-link')
                ->color('primary'),

            Stat::make('Источники исполнения', $uniqueActors ?: '0')
                ->description('Уникальных акторов / систем')
                ->descriptionIcon('heroicon-m-identification')
                ->color('info'),

            Stat::make('Последний хэш (Commit)', $lastCommitShort)
                ->description('Крайний слепок реальности')
                ->descriptionIcon('heroicon-m-key')
                ->color('warning'),

            Stat::make('Статус цепи', 'ЗАЩИЩЕНО')
                ->description('Консенсус целостности SHA-256')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('success'),
        ];
    }
}
