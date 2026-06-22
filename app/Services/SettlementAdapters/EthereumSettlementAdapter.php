<?php

namespace App\Services\SettlementAdapters;

class EthereumSettlementAdapter extends AbstractEvmSettlementAdapter
{
    protected function networkKey(): string
    {
        return 'ethereum';
    }
}
