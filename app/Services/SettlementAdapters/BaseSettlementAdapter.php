<?php

namespace App\Services\SettlementAdapters;

class BaseSettlementAdapter extends AbstractEvmSettlementAdapter
{
    protected function networkKey(): string
    {
        return 'base';
    }
}
