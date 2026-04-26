<?php

namespace App\Filament\Resources\ShopResource\Pages;

use App\Filament\Resources\ShopResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShop extends EditRecord
{
    protected static string $resource = ShopResource::class;

    protected function afterSave(): void
    {
        $this->record->syncLegalEntityManager();
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('update_ym_prices')
                ->label('Обновить цены YM')
                ->color('info')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function () {
                    $controller = app(\App\Http\Controllers\Ym\MainController::class);
                    $request = new \Illuminate\Http\Request(['business_id' => $this->record->business_id]);
                    
                    $response = $controller->sendItemsWildflow($request);
                    
                    if ($response->isSuccessful()) {
                        \Filament\Notifications\Notification::make()
                            ->title('Цены успешно обновлены')
                            ->success()
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title('Ошибка при обновлении цен')
                            ->danger()
                            ->send();
                    }
                }),
            \Filament\Actions\Action::make('update_ym_stocks')
                ->label('Обновить остатки YM')
                ->color('warning')
                ->icon('heroicon-o-archive-box')
                ->requiresConfirmation()
                ->action(function () {
                    $controller = app(\App\Http\Controllers\Ym\MainController::class);
                    $request = new \Illuminate\Http\Request(['business_id' => $this->record->business_id]);
                    
                    $response = $controller->prepareSendStockItemsWildflow($request);
                    
                    if ($response->isSuccessful()) {
                        \Filament\Notifications\Notification::make()
                            ->title('Остатки успешно обновлены')
                            ->success()
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title('Ошибка при обновлении остатков')
                            ->danger()
                            ->send();
                    }
                }),
            DeleteAction::make(),
        ];
    }
}
