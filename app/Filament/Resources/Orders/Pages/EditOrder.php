<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $is_executor = auth()->user()->hasRole('executor');
        $is_support = auth()->user()->hasRole('support');

        if ($is_executor || $is_support) {
            $old_state = $this->getRecord();

            if ($old_state->is_problem !== $data['is_problem']) {
                $data['assigned_user_id'] = null;
                $data['assigned_at'] = null;
            }
        }

        if ($data['progress_id'] === 4) {
            $data['assigned_user_id'] = null;
            $data['assigned_at'] = null;
            $data['is_problem'] = false;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        
        // Пересчитываем статус активации заказа: должны быть активированы абсолютно все товары
        $items = $record->items;
        $activated_all = $items->isNotEmpty() && $items->every(fn($item) => (bool)$item->is_activated === true);

        if ($record->code_activated !== $activated_all) {
            $record->update(['code_activated' => $activated_all]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
//            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public static function canAccess(array $parameters = []): bool
    {
        $is_executor = auth()->user()->hasRole('executor');
        $is_support = auth()->user()->hasRole('support');

        if ($is_executor || $is_support) {
            return $parameters['record']->assigned_user_id === auth()->user()->id;
        }

        return true;
    }


}
