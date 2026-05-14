<?php

namespace App\Filament\Audit\Widgets;

use App\Models\Shop;
use App\Services\LedgerService;
use Illuminate\Support\Facades\Cache;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LedgerIntegrityWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $ledgerService = app(LedgerService::class);
        $shops = Shop::where('is_active', true)->get();
        
        $totalEvents = \App\Models\SovereignLedger::count();
        // 🚀 SMART OPTIMIZATION: Verify tail (last 250 entries) and CACHE the result for 10 min!
        $brokenShops = Cache::remember('ledger_integrity_broken_shops_count', 600, function () use ($ledgerService, $shops) {
            $brokenCount = 0;
            foreach ($shops as $shop) {
                $report = $ledgerService->verifyIntegrity($shop, 250); 
                if (!$report['valid']) {
                    $brokenCount++;
                }
            }
            return $brokenCount;
        });

        $integrityStatus = $brokenShops === 0 ? '✅ В норме' : "❌ Нарушено ({$brokenShops})";
        $integrityColor = $brokenShops === 0 ? 'success' : 'danger';

        return [
            Stat::make('Целостность Ledger (MDK)', $integrityStatus)
                ->description($brokenShops === 0 ? 'Все хеш-цепочки подтверждены' : 'Обнаружено вмешательство в данные!')
                ->descriptionIcon($brokenShops === 0 ? 'heroicon-m-check-badge' : 'heroicon-m-exclamation-triangle')
                ->color($integrityColor),
            
            Stat::make('Всего событий в Ledger', $totalEvents)
                ->description('Глобальный объем транзакций')
                ->icon('heroicon-m-queue-list'),

            Stat::make('Активных нод (Магазинов)', $shops->count())
                ->description('Подключенные партнеры')
                ->icon('heroicon-m-building-storefront'),
        ];
    }
}
