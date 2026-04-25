<?php

namespace App\Filament\Partner\Resources\OrderResource\Pages;

use App\Filament\Partner\Resources\OrderResource;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;
}
