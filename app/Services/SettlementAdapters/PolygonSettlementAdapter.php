<?php

namespace App\Services\SettlementAdapters;

class PolygonSettlementAdapter extends AbstractEvmSettlementAdapter
{
    protected function networkKey(): string
    {
        return 'polygon';
    }
}
