<?php

namespace App\Filament\Treasury\Resources;

use App\Models\CurrencyPair;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class CurrencyPairResource extends Resource
{
    protected static ?string $model = CurrencyPair::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    public static function getNavigationGroup(): ?string
    {
        return __('sovereign.navigation.groups.intelligence');
    }

    public static function getNavigationLabel(): string
    {
        return __('sovereign.navigation.pairs');
    }

    protected static ?int $navigationSort = 1;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Pair Configuration')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('base_currency_id')
                                ->relationship('baseCurrency', 'code')
                                ->searchable()
                                ->preload()
                                ->required(),
                            Select::make('target_currency_id')
                                ->relationship('targetCurrency', 'code')
                                ->searchable()
                                ->preload()
                                ->required(),
                        ]),
                        Toggle::make('is_active')->default(true),
                    ]),

                Section::make('The 4 Layers of Truth (Current Rates)')
                    ->description('Rates for this specific corridor')
                    ->schema([
                        Grid::make(4)->schema([
                            TextInput::make('official_rate')->numeric()->placeholder('CB Rate'),
                            TextInput::make('tradfi_rate')->numeric()->placeholder('Forex Rate'),
                            TextInput::make('spot_rate')->numeric()->placeholder('Crypto Spot'),
                            TextInput::make('p2p_rate')->numeric()->placeholder('Shadow Rate'),
                        ]),
                    ]),

                Section::make('Market Depth (Shadow/P2P)')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('p2p_buy_rate')->numeric()->label('P2P Buy (Bid)'),
                            TextInput::make('p2p_sell_rate')->numeric()->label('P2P Sell (Ask)'),
                        ]),
                        TextInput::make('spread_percent')->numeric()->suffix('%'),
                        TextInput::make('liquidity_score')->numeric()->default(50)->minValue(0)->maxValue(100),
                    ])->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Pair')->sortable()->searchable(),
                TextColumn::make('official_rate')->label('ЦБ')->numeric(decimalPlaces: 4)->color('gray'),
                TextColumn::make('tradfi_rate')->label('Forex')->numeric(decimalPlaces: 4)->color('info'),
                TextColumn::make('spot_rate')->label('Spot')->numeric(decimalPlaces: 4)->color('primary'),
                TextColumn::make('p2p_buy_rate')->label('Buy')->numeric(decimalPlaces: 4)->color('success'),
                TextColumn::make('p2p_sell_rate')->label('Sell')->numeric(decimalPlaces: 4)->color('danger'),
                TextColumn::make('spread_percent')->label('Spread')->suffix('%')->sortable(),
                TextColumn::make('liquidity_score')
                    ->label('Score')
                    ->badge()
                    ->color(fn (int $state): string => $state > 70 ? 'success' : ($state > 40 ? 'warning' : 'danger')),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Treasury\Resources\CurrencyPairResource\Pages\ListCurrencyPairs::route('/'),
        ];
    }
}
