<?php

namespace App\Support\Concerns;

use App\Services\SettlementNetworkRegistry;

trait ResolvesSettlementNetworkLabels
{
    protected function settlementNetworkTraceLabel(string $networkKey = 'simple-layer-1'): string
    {
        return app(SettlementNetworkRegistry::class)->traceLabel($networkKey);
    }
}
