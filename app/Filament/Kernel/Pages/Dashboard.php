<?php

namespace App\Filament\Kernel\Pages;

use App\Filament\Kernel\Widgets\HealthOverviewWidget;
use App\Filament\Kernel\Widgets\KernelBannerWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'System Kernel: Core Substrate';

    public function mount(): void
    {
        $user = auth()->user();

        // 1. Disk
        $freeSpace = round(disk_free_space("/") / 1024 / 1024 / 1024, 2);
        $totalSpace = round(disk_total_space("/") / 1024 / 1024 / 1024, 2);
        $diskPercent = round(($totalSpace - $freeSpace) / $totalSpace * 100, 1);
 
        // 2. Queues
        $pendingJobs = \Illuminate\Support\Facades\DB::table('jobs')->count();
        $failedJobs = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
 
        // 3. Synclogs
        $lastCurrencyUpdate = \App\Models\Currency::latest('updated_at')->first()?->updated_at;
        $lastCatalogUpdate = \App\Models\WildflowCatalog::latest('updated_at')->first()?->updated_at;

        response()->view('ops.kernel', [
            'user' => $user,
            'freeSpace' => $freeSpace,
            'totalSpace' => $totalSpace,
            'diskPercent' => $diskPercent,
            'pendingJobs' => $pendingJobs,
            'failedJobs' => $failedJobs,
            'lastCurrencyUpdate' => $lastCurrencyUpdate,
            'lastCatalogUpdate' => $lastCatalogUpdate,
        ])->send();
        exit;
    }

    public function getWidgets(): array
    {
        return [
            KernelBannerWidget::class,
            HealthOverviewWidget::class,
        ];
    }
}
