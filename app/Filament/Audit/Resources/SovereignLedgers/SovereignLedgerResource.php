<?php

namespace App\Filament\Audit\Resources\SovereignLedgers;

use App\Filament\Audit\Resources\SovereignLedgers\Pages;
use App\Filament\Audit\Resources\SovereignLedgers\Widgets;
use App\Models\SovereignLedger;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SovereignLedgerResource extends Resource
{
    protected static ?string $model = SovereignLedger::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    public static function getNavigationGroup(): ?string
    {
        return __('sovereign.navigation.groups.network');
    }

    protected static ?int $navigationSort = 3;

    public static function getLabel(): ?string
    {
        return __('sovereign.navigation.ledger');
    }

    public static function getPluralLabel(): ?string
    {
        return __('sovereign.navigation.ledger');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Section::make('Core Metadata')
                    ->columns(3)
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('created_at')
                            ->label('Timestamp')
                            ->disabled(),
                        \Filament\Forms\Components\TextInput::make('event_type')
                            ->label('Event Type')
                            ->disabled(),
                        \Filament\Forms\Components\TextInput::make('trigger_source')
                            ->label(__('sovereign.ledger.fields.source'))
                            ->disabled(),
                    ]),
                
                \Filament\Forms\Components\Section::make('Execution Vectors')
                    ->columns(2)
                    ->schema([
                        \Filament\Forms\Components\Textarea::make('input_data')
                            ->label(__('sovereign.ledger.fields.input'))
                            ->disabled()
                            ->rows(6)
                            ->fontFamily('mono')
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'N/A'),
                        \Filament\Forms\Components\Textarea::make('output_state')
                            ->label(__('sovereign.ledger.fields.output'))
                            ->disabled()
                            ->rows(6)
                            ->fontFamily('mono')
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'N/A'),
                    ]),

                \Filament\Forms\Components\Section::make('Commitment & Integrity')
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('fingerprint')
                            ->label(__('sovereign.ledger.fields.fingerprint'))
                            ->fontFamily('mono')
                            ->disabled(),
                        \Filament\Forms\Components\TextInput::make('previous_fingerprint')
                            ->label(__('sovereign.ledger.fields.previous'))
                            ->fontFamily('mono')
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('shop.name')
                    ->label('Магазин')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Тип события')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ORDER_RECEIVE' => 'info',
                        'FINANCE_HOLD' => 'warning',
                        'STOCK_REPLENISH' => 'success',
                        'STOCK_RESERVE' => 'primary',
                        'FINANCE_CAPTURE' => 'success',
                        'FINANCE_RELEASE' => 'danger',
                        'FINANCE_TOPUP' => 'success',
                        'STOCK_LIQUIDATE' => 'danger',
                        'currency.synchronized' => 'info',
                        default => 'gray',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('trigger_source')
                    ->label(__('sovereign.ledger.fields.source'))
                    ->limit(25)
                    ->fontFamily('mono')
                    ->toggleable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('fingerprint')
                    ->label(__('sovereign.ledger.fields.fingerprint'))
                    ->fontFamily('mono')
                    ->limit(10)
                    ->copyable()
                    ->tooltip(fn ($record) => $record->fingerprint),
                Tables\Columns\IconColumn::make('integrity')
                    ->label('Status')
                    ->getStateUsing(fn () => true) // This is a placeholder, we could do live check but it's expensive
                    ->icon('heroicon-m-check-badge')
                    ->color('success'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('shop_id')
                    ->label('Магазин')
                    ->relationship('shop', 'name'),
                Tables\Filters\SelectFilter::make('event_type')
                    ->label('Тип события')
                    ->options([
                        'ORDER_RECEIVE' => 'Order Receive',
                        'FINANCE_HOLD' => 'Finance Hold',
                        'STOCK_REPLENISH' => 'Stock Replenish',
                        'STOCK_RESERVE' => 'Stock Reserve',
                        'FINANCE_CAPTURE' => 'Finance Capture',
                        'FINANCE_RELEASE' => 'Finance Release',
                        'FINANCE_TOPUP' => 'Finance Top-up',
                        'STOCK_LIQUIDATE' => 'Stock Liquidate',
                        'currency.synchronized' => 'Currency Sync',
                    ]),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // No bulk actions for security reasons
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function getWidgets(): array
    {
        return [
            Widgets\LedgerStatsOverview::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSovereignLedgers::route('/'),
        ];
    }
}
