<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function afterCreate(): void
    {
        $order = $this->record;
        
        // ⛓️ Sovereign Ledger: Record manual order receipt
        app(\App\Services\LedgerService::class)->record($order->shop, 'ORDER_RECEIVE', $order, [
            'channel' => 'manual_admin',
            'user_id' => auth()->id(),
        ]);
    }
}
