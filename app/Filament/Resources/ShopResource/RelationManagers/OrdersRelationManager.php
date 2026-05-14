<?php

namespace App\Filament\Resources\ShopResource\RelationManagers;

use App\Filament\Resources\Orders\Tables\OrdersTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = null;

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('admin.shops.relations.orders');
    }

    public function table(Table $table): Table
    {
        return OrdersTable::configure($table)
            ->headerActions([])
            ->actions([
                Action::make('retry_fulfillment')
                    ->label(__('admin.orders.actions.retry_purchase'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->items->contains('purchase_status', 'failed'))
                    ->action(function ($record) {
                        $orderService = new \App\Services\OrderService();
                        $results = [];

                        foreach ($record->items as $item) {
                            if ($item->purchase_status === 'failed') {
                                $results[] = $orderService->retryAutozakup($item);
                            }
                        }

                        $successCount = count(array_filter($results, fn($r) => $r['success']));
                        $failCount = count($results) - $successCount;

                        if ($successCount > 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('Закупка успешно выполнена')
                                ->body("Успешно закуплено товаров: $successCount" . ($failCount > 0 ? ". Ошибок: $failCount" : ""))
                                ->success()
                                ->send();
                        } else {
                            $lastError = data_get(end($results), 'error', 'Неизвестная ошибка');
                            \Filament\Notifications\Notification::make()
                                ->title('Ошибка закупки')
                                ->body($lastError)
                                ->danger()
                                ->send();
                        }
                    }),
                \Filament\Actions\ViewAction::make()
                    ->url(fn ($record) => \App\Filament\Resources\Orders\OrderResource::getUrl('edit', ['record' => $record])),
                \Filament\Actions\EditAction::make()
                    ->url(fn ($record) => \App\Filament\Resources\Orders\OrderResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
