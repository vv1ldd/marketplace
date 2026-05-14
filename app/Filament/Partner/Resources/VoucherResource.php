<?php

namespace App\Filament\Partner\Resources;

use App\Models\ProductInventory;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Facades\Filament;
use Filament\Actions\ViewAction;

class VoucherResource extends Resource
{
    protected static ?string $model = ProductInventory::class;

    protected static bool $isScopedToTenant = true;
    
    public static function observeTenancyModelCreation(\Filament\Panel $panel): void
    {
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|\UnitEnum|null $navigationGroup = 'Склады';

    protected static ?string $navigationLabel = 'Реестр кодов';

    protected static ?string $label = 'Ваучер';

    protected static ?string $pluralLabel = 'Реестр кодов';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата поступления')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku')
                    ->label('Арт.')
                    ->getStateUsing(fn ($record) => 'ID-' . strtoupper(substr(md5($record->getAttributes()['sku_bidx'] ?? $record->sku), 0, 8)))
                    ->copyable()
                    ->tooltip('Артикул товара'),
                Tables\Columns\TextColumn::make('code')
                    ->label('Код (Voucher)')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success',
                        'reserved' => 'warning',
                        'sold' => 'info',
                        'liquidated' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'available' => 'Свободен',
                        'reserved' => 'В холде',
                        'sold' => 'Активирован',
                        'liquidated' => 'Ликвидирован',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('orderItem.order.order_id')
                    ->label('Заказ')
                    ->url(fn ($record) => $record->orderItem?->order ? \App\Filament\Partner\Resources\OrderResource::getUrl('edit', ['record' => $record->orderItem->order->id]) : null)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('liquidated_at')
                    ->label('Дата ликв.')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('liquidation_reason')
                    ->label('Причина ликв.')
                    ->limit(20)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('fingerprint_badge')
                    ->label('Доказательство (MDK)')
                    ->getStateUsing(fn () => 'Verified')
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-m-check-badge')
                    ->tooltip(fn ($record) => "Fingerprint: " . ($record->ledgerEntries()->latest('id')->first()?->fingerprint ?? 'No ledger entry')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'available' => 'Свободен',
                        'reserved' => 'В холде',
                        'sold' => 'Активирован',
                        'liquidated' => 'Ликвидирован',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([
                // No bulk actions for now
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => VoucherResource\Pages\ListVouchers::route('/'),
        ];
    }
}
