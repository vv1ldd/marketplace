<?php
 
namespace App\Filament\Kernel\Widgets;
 
use App\Models\Currency;
use App\Models\WildflowCatalog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
 
class HealthOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // 1. Диск
        $freeSpace = round(disk_free_space("/") / 1024 / 1024 / 1024, 2);
        $totalSpace = round(disk_total_space("/") / 1024 / 1024 / 1024, 2);
        $diskPercent = round(($totalSpace - $freeSpace) / $totalSpace * 100, 1);
 
        // 2. Очереди
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();
 
        // 3. Последние синхроны
        $lastCurrencyUpdate = Currency::latest('updated_at')->first()?->updated_at;
        $lastCatalogUpdate = WildflowCatalog::latest('updated_at')->first()?->updated_at;
 
        return [
            Stat::make('Место на диске', "$freeSpace GB свободно")
                ->description("Занято: $diskPercent%")
                ->descriptionIcon($diskPercent > 90 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-circle-stack')
                ->color($diskPercent > 90 ? 'danger' : 'success'),
 
            Stat::make('Очереди (Jobs)', $pendingJobs)
                ->description($failedJobs > 0 ? "Упало с ошибкой: $failedJobs" : "Всё чисто")
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color($failedJobs > 0 ? 'danger' : 'success'),
 
            Stat::make('Синхронизация цен', $lastCurrencyUpdate ? $lastCurrencyUpdate->diffForHumans() : 'Никогда')
                ->description('Последнее обновление курсов')
                ->icon('heroicon-m-banknotes'),
 
            Stat::make('Парсер каталога', $lastCatalogUpdate ? $lastCatalogUpdate->diffForHumans() : 'Никогда')
                ->description('Последний запуск SyncCatalogsCommand')
                ->icon('heroicon-m-magnifying-glass'),
        ];
    }
}
