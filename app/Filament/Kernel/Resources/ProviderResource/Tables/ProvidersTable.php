<?php

namespace App\Filament\Kernel\Resources\ProviderResource\Tables;

use App\Jobs\RunWildflowCatalogSyncJob;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Bus;

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
                        'fazer' => 'warning',
                        default => 'gray',
                    }),
                IconColumn::make('is_active')
                    ->label('Статус')
                    ->boolean(),
                TextColumn::make('sync_status')
                    ->label('Синхронизация')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'syncing' => 'info',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'syncing' => 'heroicon-o-arrow-path',
                        default => 'heroicon-o-check-circle',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'syncing' => 'В процессе...',
                        default => 'Готов',
                    }),
                TextColumn::make('last_sync_at')
                    ->label('Последний синк')
                    ->dateTime()
                    ->sortable(),
            ])
            ->poll('2s')
            ->filters([
                //
            ])
            ->actions([
                Action::make('sync')
                    ->label(fn ($record) => $record->sync_status === 'syncing' ? 'Синхронизация...' : 'Синхронизировать')
                    ->icon(fn ($record) => $record->sync_status === 'syncing' ? 'heroicon-o-arrow-path' : 'heroicon-o-arrow-path')
                    ->extraAttributes(fn ($record) => $record->sync_status === 'syncing' ? ['class' => 'animate-spin'] : [])
                    ->color('info')
                    ->disabled(fn ($record) => $record->sync_status === 'syncing')
                    ->action(function ($record) {
                        $record->update(['sync_status' => 'syncing']);
                        \App\Jobs\SyncProviderCatalogJob::dispatch($record->id);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Синхронизация запущена')
                            ->body('Процесс идет в фоновом режиме. Статус обновится автоматически.')
                            ->info()
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
