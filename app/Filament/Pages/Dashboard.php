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
        return [];
    }

    public function mount(): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        // Global Platform Stats
        $stats = [
            'total_partners' => \App\Models\LegalEntity::count(),
            'total_shops' => \App\Models\Shop::count(),
            'total_orders' => \App\Models\Order\Order::count(),
            'total_products' => \App\Models\Product::count(),
            'total_volume' => round(\Illuminate\Support\Facades\DB::table('order_items')->sum('price_rub') / 100, 2),
            'active_integrations' => \App\Models\ApiApplication::count(),
            'low_stock_count' => \App\Models\WarehouseStock::where('count', '<', 5)->count(),
            'critical_errors' => \App\Models\Product::whereNotNull('ym_errors')->count(),
        ];

        // 📋 Dynamic lists for the SPA view
        $orders = \App\Models\Order\Order::with(['items', 'shop'])->latest()->limit(50)->get();
        $catalog = \App\Models\Product::with(['shop'])->latest()->limit(50)->get();
        $tickets = \App\Models\Ticket::with(['shop'])->latest()->limit(50)->get();
        $shops = \App\Models\Shop::with(['legalEntity'])->latest()->limit(50)->get();
        $partners = \App\Models\LegalEntity::latest()->limit(50)->get();
        
        $ledgerTransactions = \App\Models\SovereignLedger::with(['shop', 'legalEntity'])
            ->latest()
            ->limit(50)
            ->get();

        // Cleanly send the fully-formed, premium Operations SPA HTML and terminate immediately!
        response()->view('ops.dashboard', [
            'user' => $user,
            'stats' => $stats,
            'orders' => $orders,
            'catalog' => $catalog,
            'tickets' => $tickets,
            'shops' => $shops,
            'partners' => $partners,
            'ledgerTransactions' => $ledgerTransactions,
        ])->send();

        exit;
    }
}
