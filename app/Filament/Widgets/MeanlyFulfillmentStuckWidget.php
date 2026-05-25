<?php

namespace App\Filament\Widgets;

use App\Services\MeanlyOperationalAlertService;
use Filament\Widgets\Widget;

class MeanlyFulfillmentStuckWidget extends Widget
{
    protected string $view = 'filament.widgets.meanly-fulfillment-stuck-widget';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $rows = app(MeanlyOperationalAlertService::class)->stuckFulfillmentRows();

        return [
            'rows' => $rows->take(20),
            'total' => $rows->count(),
            'failedCount' => $rows->where('severity', 'danger')->count(),
            'warningCount' => $rows->where('severity', 'warning')->count(),
            'oldestMinutes' => (int) ($rows->max('age_minutes') ?? 0),
        ];
    }
}
