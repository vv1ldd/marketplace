<?php

namespace App\Filament\Treasury\Resources\LiquidityCorridors;

use App\Filament\Treasury\Resources\LiquidityCorridors\Pages\ManageLiquidityCorridors;
use App\Models\LiquidityCorridor;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LiquidityCorridorResource extends Resource
{
    protected static ?string $model = LiquidityCorridor::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';
    
    public static function getNavigationGroup(): ?string
    {
        return __('sovereign.navigation.groups.liquidity');
    }

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('sovereign.navigation.corridors');
    }
    
    public static function getModelLabel(): string
    {
        return __('sovereign.navigation.corridors');
    }

    protected static ?string $recordTitleAttribute = 'provider_node';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Section::make('Route Definition')
                    ->description('Define the entrance and exit points for the liquidity flow.')
                    ->schema([
                        Select::make('currency_code')
                            ->label('Target Currency')
                            ->options(\App\Models\Currency::pluck('code', 'code'))
                            ->searchable()
                            ->required(),
                        TextInput::make('provider_node')
                            ->label(__('sovereign.corridors.fields.node'))
                            ->hint(__('sovereign.corridors.fields.node_hint'))
                            ->required(),
                        Select::make('routing_asset')
                            ->label(__('sovereign.corridors.fields.bridge'))
                            ->hint(__('sovereign.corridors.fields.bridge_hint'))
                            ->options([
                                'USDT' => 'USDT (Tether)',
                                'USD_CASH' => 'USD Cash',
                                'EUR' => 'EUR Fiat',
                                'BTC' => 'Bitcoin',
                            ])
                            ->required()
                            ->default('USDT'),
                        Select::make('direction')
                            ->options([
                                'inbound' => 'Inbound (Fiat -> Asset)',
                                'outbound' => 'Outbound (Asset -> Fiat)',
                                'bidirectional' => 'Bidirectional',
                            ])
                            ->default('bidirectional')
                            ->required(),
                    ])->columns(2),

                \Filament\Forms\Components\Section::make('Financial Physics')
                    ->description('Set the gravity of this route: fees, volume limits, and time-to-settle.')
                    ->schema([
                        TextInput::make('base_fee_percent')
                            ->label('Route Fee %')
                            ->suffix('%')
                            ->required()
                            ->numeric()
                            ->default(0.0),
                        TextInput::make('fixed_fee_amount')
                            ->label('Fixed Fee')
                            ->required()
                            ->numeric()
                            ->default(0.0),
                        TextInput::make('min_volume')
                            ->label('Min Volume')
                            ->numeric()
                            ->placeholder('0.00'),
                        TextInput::make('max_volume')
                            ->label('Max Capacity')
                            ->numeric()
                            ->placeholder('No limit'),
                        TextInput::make('sla_minutes')
                            ->label(__('sovereign.corridors.fields.sla'))
                            ->hint(__('sovereign.corridors.fields.sla_hint'))
                            ->suffix('min')
                            ->required()
                            ->numeric()
                            ->default(60),
                    ])->columns(3),

                \Filament\Forms\Components\Section::make('Governance & Risk')
                    ->schema([
                        Select::make('trust_tier')
                            ->label(__('sovereign.corridors.fields.tier'))
                            ->hint(__('sovereign.corridors.fields.tier_hint'))
                            ->options([
                                1 => 'Tier 1: Institutional / Interbank',
                                2 => 'Tier 2: Global Spot / Exchange',
                                3 => 'Tier 3: Digital P2P Corridors',
                                4 => 'Tier 4: OTC / Deep Liquidity',
                                5 => 'Tier 5: Shadow / Unofficial / Hawala',
                            ])
                            ->required()
                            ->default(3),
                        Toggle::make('is_active')
                            ->label('Route is Operational')
                            ->onIcon('heroicon-m-bolt')
                            ->offIcon('heroicon-m-bolt-slash')
                            ->required()
                            ->default(true),
                        \Filament\Forms\Components\KeyValue::make('metadata')
                            ->label('Route Specific Rules')
                            ->addActionLabel('Add Rule')
                            ->keyLabel('Condition')
                            ->valueLabel('Value'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('provider_node')
            ->columns([
                TextColumn::make('currency_code')
                    ->label('Currency')
                    ->badge()
                    ->color('info')
                    ->searchable(),
                TextColumn::make('provider_node')
                    ->label('Node')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('direction')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'inbound' => 'success',
                        'outbound' => 'warning',
                        default => 'primary',
                    }),
                TextColumn::make('routing_asset')
                    ->label('Bridge')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('trust_tier')
                    ->label('Tier')
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        1, 2 => 'success',
                        3 => 'info',
                        4 => 'warning',
                        5 => 'danger',
                    }),
                TextColumn::make('base_fee_percent')
                    ->label('Fee')
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('sla_minutes')
                    ->label('SLA')
                    ->suffix(' min')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Up')
                    ->boolean(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('currency_code')
                    ->options(\App\Models\Currency::pluck('code', 'code')),
                \Filament\Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->recordActions([
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
            'index' => ManageLiquidityCorridors::route('/'),
        ];
    }
}
