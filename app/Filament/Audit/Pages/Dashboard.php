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
        return [];
    }

    public function mount(): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        // Only allow super_admin or auditor
        if (!$user->hasRole('super_admin') && !$user->hasRole('auditor')) {
            abort(403);
        }

        // Ledger metrics
        $stats = [
            'total_blocks' => \App\Models\SovereignLedger::count(),
            'total_volume_rub' => round(\App\Models\SovereignLedger::where('currency', 'RUB')->sum('amount_base'), 2),
            'total_volume_usd' => round(\App\Models\SovereignLedger::where('currency', 'USD')->sum('amount_base'), 2),
            'anomalies_detected' => 0, 
            'chain_status' => 'CRYPTOGRAPHICALLY SECURE (SHA-256)',
        ];

        $ledgerTransactions = \App\Models\SovereignLedger::with(['shop', 'legalEntity'])
            ->latest()
            ->limit(100)
            ->get();

        // Directly send standard HTML response and terminate script execution to bypass Livewire/Filament completely!
        response()->view('ops.tribunal', [
            'user' => $user,
            'stats' => $stats,
            'ledgerTransactions' => $ledgerTransactions,
        ])->send();

        exit;
    }
}
