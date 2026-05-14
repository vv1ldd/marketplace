<?php

namespace App\Services\Ai;

use App\Models\LegalEntity;
use App\Models\Order\Order;
use App\Models\Provider;
use App\Models\WarehouseStock;
use App\Models\Currency;
use Carbon\Carbon;

class SovereignStateScanner
{
    /**
     * Собирает глобальный срез состояния системы для ИИ
     */
    public function getSystemSnapshot(): string
    {
        $sections = [
            $this->getFinancialHealth(),
            $this->getInventoryAlerts(),
            $this->getOperationalPulse(),
            $this->getProviderHealth(),
        ];

        return implode("\n\n", array_filter($sections));
    }

    /**
     * Срез финансового здоровья (балансы)
     */
    protected function getFinancialHealth(): string
    {
        $totalBalance = LegalEntity::sum('available_balance');
        $totalReserved = LegalEntity::sum('reserved_balance');
        $entitiesCount = LegalEntity::count();
        
        $topEntities = LegalEntity::orderBy('available_balance', 'desc')
            ->limit(3)
            ->get()
            ->map(fn($e) => "{$e->short_name}: {$e->available_balance} {$e->currency}")
            ->implode(', ');

        return "--- ФИНАНСОВОЕ СОСТОЯНИЕ ---\n" .
               "Общий баланс сети: " . number_format($totalBalance, 2) . " RUB\n" .
               "Зарезервировано: " . number_format($totalReserved, 2) . " RUB\n" .
               "Активных юрлиц: {$entitiesCount}\n" .
               "Топ балансов: {$topEntities}";
    }

    /**
     * Срез инвентаря и остатков
     */
    protected function getInventoryAlerts(): string
    {
        $lowStock = WarehouseStock::where('count', '<', 5)
            ->with('product')
            ->limit(5)
            ->get()
            ->map(fn($s) => "{$s->product?->sku}: {$s->count} шт.")
            ->implode(', ');

        $totalVouchers = \App\Models\ProductInventory::where('is_used', false)->where('status', 'available')->count();

        return "--- СКЛАД И ИНВЕНТАРЬ ---\n" .
               "Всего доступных ваучеров в стоке: {$totalVouchers}\n" .
               "Критические остатки (<5 шт): " . ($lowStock ?: "Нет");
    }

    /**
     * Операционный пульс (заказы)
     */
    protected function getOperationalPulse(): string
    {
        $today = Carbon::today();
        $ordersToday = Order::whereDate('created_at', $today)->count();
        $pendingOrders = Order::whereIn('progress_id', [1, 2, 3])->count();
        $failedOrders = Order::whereDate('created_at', $today)->where('status', 'CANCELLED')->count();

        $successRate = $ordersToday > 0 ? round((($ordersToday - $failedOrders) / $ordersToday) * 100, 1) : 100;

        return "--- ОПЕРАЦИОННЫЙ ПУЛЬС ---\n" .
               "Заказов сегодня: {$ordersToday} (Успешность: {$successRate}%)\n" .
               "Ожидают обработки: {$pendingOrders}\n" .
               "Отмен сегодня: {$failedOrders}";
    }

    /**
     * Состояние провайдеров
     */
    protected function getProviderHealth(): string
    {
        $providers = Provider::all();
        $active = $providers->where('is_active', true)->count();
        $inactive = $providers->where('is_active', false)->map(fn($p) => $p->name)->implode(', ');

        return "--- ПРОВАЙДЕРЫ ---\n" .
               "Активно узлов: {$active} / " . $providers->count() . "\n" .
               "Отключены: " . ($inactive ?: "Все в строю");
    }
}
