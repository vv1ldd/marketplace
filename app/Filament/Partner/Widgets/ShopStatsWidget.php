<?php

namespace App\Filament\Partner\Widgets;

use App\Models\Order\Order;
use App\Models\Product;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ShopStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return [];
        }

        // 💰 Revenue last 30 days (sum of order items across all shops in this legal entity)
        $revenue = DB::table('orders')
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('shops', 'orders.shop_id', '=', 'shops.id')
            ->where('shops.legal_entity_id', $tenant->id)
            ->where('orders.created_at', '>=', now()->subDays(30))
            ->where('orders.progress_id', 4) // Completed
            ->sum('order_items.price_rub');

        // 📦 Active Orders
        $activeOrders = Order::whereHas('shop', fn($q) => $q->where('legal_entity_id', $tenant->id))
            ->where('progress_id', '<>', 4)
            ->count();

        // 🩺 Yandex Market Health
        $productsWithErrors = Product::whereHas('shop', fn($q) => $q->where('legal_entity_id', $tenant->id))
            ->whereNotNull('ym_errors')
            ->count();

        // 💳 Current Balance
        $balance = $tenant->available_balance ?? 0;

        return [
            Stat::make('Ваш баланс', number_format($balance, 2, '.', ' ').' ₽')
                ->description('Доступно для закупа и стока')
                ->descriptionIcon('heroicon-m-wallet')
                ->color($balance > 0 ? 'success' : 'danger'),

            Stat::make('Выручка (30 дней)', number_format($revenue / 100, 2, '.', ' ').' ₽')
                ->description('Сумма завершенных заказов')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Заказы в работе', $activeOrders)
                ->description('Требуют обработки')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color($activeOrders > 0 ? 'warning' : 'gray'),

            Stat::make('Ошибки на Маркете', $productsWithErrors)
                ->description('Товары с замечаниями Яндекса')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($productsWithErrors > 0 ? 'danger' : 'success'),
        ];
    }
}
