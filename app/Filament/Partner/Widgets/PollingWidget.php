<?php

namespace App\Filament\Partner\Widgets;

use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class PollingWidget extends Widget
{
    protected string $view = 'filament.partner.widgets.polling-widget';

    protected int | string | array $columnSpan = 'full';

    public int $lastProgress = -1;

    public function checkProgress(): void
    {
        $tenant = Filament::getTenant();
        if (! $tenant) return;

        // Aggregate progress: show the max progress if any shop is currently importing
        $current = (int) ($tenant->shops()->where('import_status', 'running')->max('import_progress') ?? 100);

        if ($current !== $this->lastProgress) {
            $this->lastProgress = $current;
            $this->dispatch('catalog-sync-updated');
        }
    }
}
