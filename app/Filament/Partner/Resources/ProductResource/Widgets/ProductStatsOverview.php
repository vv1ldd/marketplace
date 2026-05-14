<?php

namespace App\Filament\Partner\Resources\ProductResource\Widgets;

use App\Models\Product;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ProductStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $shopId = Filament::getTenant()?->id;
        if (!$shopId) {
            return [];
        }

        // Всего товаров
        $totalProducts = Product::where('shop_id', $shopId)->count();

        // Товары на внешних площадках (is_enabled = true)
        $onPlatforms = Product::where('shop_id', $shopId)
            ->whereHas('salesChannels', function ($query) use ($shopId) {
                $query->where('shop_id', $shopId)->where('is_enabled', true);
            })->count();

        // Ошибки Яндекса
        $withErrors = Product::where('shop_id', $shopId)
            ->whereNotNull('ym_errors')
            ->where('ym_errors', '!=', '[]')
            ->count();

        return [
            Stat::make('Всего товаров', $totalProducts)
                ->description('В вашем каталоге')
                ->descriptionIcon('heroicon-m-rectangle-stack')
                ->color('primary'),
                
            Stat::make('Дистрибуция', $onPlatforms)
                ->description('Выгружено на площадки')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('success'),
                
            Stat::make('Ошибки выгрузки', $withErrors)
                ->description('Проблемы с маркетом')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($withErrors > 0 ? 'danger' : 'gray'),
        ];
    }
}
