<?php

namespace App\Filament\Resources\ProviderResource\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Table;

class ProvidersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'wildflow' => 'success',
                        'playstation' => 'info',
                        default => 'gray',
                    }),
                IconColumn::make('is_active')
                    ->label('Статус')
                    ->boolean(),
                TextColumn::make('last_sync_at')
                    ->label('Последняя синхронизация')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('sync')
                    ->label('Синхронизировать')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->action(function ($record) {
                        if ($record->type === 'playstation') {
                            \Illuminate\Support\Facades\Artisan::call('ps:sync-to-products');
                        } elseif ($record->type === 'playstation_us') {
                            \Illuminate\Support\Facades\Artisan::call('ps:sync-region');
                        } elseif ($record->type === 'wildflow') {
                            \Illuminate\Support\Facades\Artisan::call('app:wildflow-parser');
                        }
                        
                        $record->update(['last_sync_at' => now()]);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Синхронизация запущена для: ' . $record->name)
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
