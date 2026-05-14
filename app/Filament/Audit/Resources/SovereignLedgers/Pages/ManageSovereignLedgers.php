<?php

namespace App\Filament\Audit\Resources\SovereignLedgers\Pages;

use App\Filament\Audit\Resources\SovereignLedgers\SovereignLedgerResource;
use App\Filament\Audit\Resources\SovereignLedgers\Widgets;
use Filament\Resources\Pages\ManageRecords;

class ManageSovereignLedgers extends ManageRecords
{
    protected static string $resource = SovereignLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            Widgets\LedgerStatsOverview::class,
        ];
    }
}
