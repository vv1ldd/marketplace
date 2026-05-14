<?php

namespace App\Filament\Treasury\Resources\CurrencyResource\Pages;

use App\Filament\Treasury\Resources\CurrencyResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListCurrencies extends ListRecords
{
    protected static string $resource = CurrencyResource::class;
    


    protected function getHeaderActions(): array
    {
        return [
            Action::make('updateRates')
                ->label(__('admin.finance.actions.update_rates'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(__('admin.finance.actions.update_rates'))
                ->modalDescription(__('admin.finance.actions.update_rates_confirm'))
                ->action(function (): void {
                    \App\Jobs\SyncCurrencyRatesBackgroundJob::dispatch(auth()->id());

                    Notification::make()
                        ->title('🚀 Синхронизация запущена')
                        ->body('Процесс обновления валют перенесен в фон. Уведомление придет в колокольчик по завершению.')
                        ->info()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }

    public function updatedTableFilters(): void
    {
        $value = $this->tableFilters['base_selector'] ?? null;
        
        if (!empty($value['value'])) {
            session(['currency_base' => $value['value']]);
        } else {
            session(['currency_base' => 'RUB']);
        }
    }
}
