<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public static function canAccess(array $parameters = []): bool
    {
        $is_executor = auth()->user()->hasRole('executor');

        if($is_executor) {
            return $parameters['record']->assigned_user_id === auth()->user()->id;
        }

        return true;
    }
}
