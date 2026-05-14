<?php

namespace App\Filament\Treasury\Resources;

use App\Filament\Treasury\Resources\CurrencyResource\Pages;
use App\Filament\Treasury\Resources\CurrencyResource\RelationManagers;
use App\Models\Currency;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TagsInput;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';
    public static function getNavigationLabel(): string
    {
        return __('sovereign.navigation.currencies');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('sovereign.navigation.groups.intelligence');
    }

    protected static ?int $navigationSort = 2;

    public static function getLabel(): ?string
    {
        return __('admin.finance.currency');
    }

    public static function getPluralLabel(): ?string
    {
        return __('admin.finance.currencies');
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make(__('admin.finance.sections.settings'))
                    ->schema([
                        TextInput::make('code')
                            ->label(__('admin.finance.fields.code'))
                            ->required()
                            ->maxLength(3)
                            ->unique(ignoreRecord: true)
                            ->disabled(fn ($record) => $record !== null)
                            ->dehydrated(),
                        TextInput::make('name')
                            ->label(__('admin.finance.fields.name'))
                            ->maxLength(255),
                        TextInput::make('rate_to_rub')
                            ->label(__('admin.finance.fields.auto_rate'))
                            ->numeric()
                            ->disabled()
                            ->helperText(__('admin.finance.helpers.auto_rate')),
                        TextInput::make('manual_rate')
                            ->label(__('admin.finance.fields.manual_rate'))
                            ->numeric()
                            ->disabled()
                            ->helperText(__('admin.finance.helpers.manual_rate')),
                        \Filament\Forms\Components\Toggle::make('is_auto_update')
                            ->label('Автообновление')
                            ->default(true),
                        \Filament\Forms\Components\Toggle::make('is_shadow')
                            ->label('Shadow FX (Parallel Market)')
                            ->helperText('Использовать альтернативные источники для закрытых экономик')
                            ->default(false),
                        \Filament\Forms\Components\TextInput::make('shadow_source')
                            ->label('Источник телеметрии')
                            ->placeholder('Telegram, Local OTC, etc.'),
                    ])->collapsible(),

                Section::make('Health & Evidence')
                    ->description('Real-time liquidity metrics and telemetry signals')
                    ->schema([
                        Grid::make(3)->schema([
                            Placeholder::make('lsi_display')
                                ->label('Liquidity Stress (LSI)')
                                ->content(fn ($record) => number_format(($record->liquidity_stress_index ?? 0) * 100, 0) . '%'),
                            Placeholder::make('obs_display')
                                ->label('Observability')
                                ->content(fn ($record) => number_format(($record->observability_score ?? 0) * 100, 0) . '%'),
                            Placeholder::make('spot_support')
                                ->label('Gateway Type')
                                ->content(fn ($record) => $record->has_spot_liquidity ? '🏛️ Institutional Spot' : '🤝 P2P/Shadow'),
                        ]),
                    ])->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $base = session('currency_base', 'RUB');

        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('admin.finance.fields.code'))
                    ->formatStateUsing(fn (string $state): string => 
                        match($state) {
                            'EUR' => '🇪🇺 ' . $state,
                            'EZD' => '🤖 ' . $state,
                            default => (function($code) {
                                $countryCode = substr($code, 0, 2);
                                $offset = 127397;
                                if (strlen($countryCode) === 2) {
                                    return mb_chr(ord($countryCode[0]) + $offset) . mb_chr(ord($countryCode[1]) + $offset) . ' ' . $code;
                                }
                                return $code;
                            })($state)
                        }
                    )
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\ViewColumn::make('trend')
                    ->label('Тренд (10д)')
                    ->view('filament.tables.columns.currency-sparkline'),
                
                TextColumn::make('change_24h')
                    ->label('24ч %')
                    ->state(function (Currency $record) {
                        $lastHistory = $record->histories()
                            ->where('record_date', '<', now()->startOfDay())
                            ->latest('record_date')
                            ->first();
                        
                        if (!$lastHistory || $lastHistory->official_rate <= 0 || $record->official_rate <= 0) {
                            return null;
                        }
                        
                        return (($record->official_rate - $lastHistory->official_rate) / $lastHistory->official_rate) * 100;
                    })
                    ->formatStateUsing(fn ($state) => $state !== null ? ($state > 0 ? '+' : '') . number_format($state, 2) . '%' : '-')
                    ->color(fn ($state) => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray'))
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('admin.finance.fields.name')),
                
                TextColumn::make('official_rate')
                    ->label('ЦБ')
                    ->sortable()
                    ->state(function (Currency $record) {
                        $baseCode = session('currency_base', 'RUB');
                        $rate = ($baseCode === 'RUB') ? $record->official_rate : ($record->official_rate / (Currency::where('code', $baseCode)->first()?->official_rate ?: 1.0));
                        return session('currency_view_mode') === 'inverse' ? ($rate > 0 ? 1 / $rate : 0) : $rate;
                    })
                    ->formatStateUsing(fn ($state) => $state > 0 ? number_format($state, 4) : '-')
                    ->color('gray'),

                TextColumn::make('tradfi_rate')
                    ->label('Forex')
                    ->sortable()
                    ->state(function (Currency $record) {
                        $baseCode = session('currency_base', 'RUB');
                        $rate = ($baseCode === 'RUB') ? $record->tradfi_rate : ($record->tradfi_rate / (Currency::where('code', $baseCode)->first()?->tradfi_rate ?: 1.0));
                        return session('currency_view_mode') === 'inverse' ? ($rate > 0 ? 1 / $rate : 0) : $rate;
                    })
                    ->formatStateUsing(fn ($state) => $state > 0 ? number_format($state, 4) : '-')
                    ->color('info')
                    ->tooltip('Classical Interbank / Forex Spot'),

                TextColumn::make('spot_rate_usdt')
                    ->label('Spot')
                    ->sortable()
                    ->state(function (Currency $record) {
                        if ($record->spot_rate_usdt <= 0) return 0;
                        
                        $usd = Currency::where('code', 'USD')->first();
                        $rubPerUsdt = $usd ? $usd->tradfi_rate : 92.5;
                        
                        $spotInRub = $rubPerUsdt / $record->spot_rate_usdt;
                        
                        $baseCode = session('currency_base', 'RUB');
                        if ($baseCode === 'RUB') {
                            $rate = $spotInRub;
                        } else {
                            $baseCur = Currency::where('code', $baseCode)->first();
                            $baseSpotInRub = ($baseCur && $baseCur->spot_rate_usdt > 0) ? ($rubPerUsdt / $baseCur->spot_rate_usdt) : ($baseCur ? $baseCur->tradfi_rate : 1.0);
                            $rate = $spotInRub / ($baseSpotInRub ?: 1);
                        }
                        
                        return session('currency_view_mode') === 'inverse' ? ($rate > 0 ? 1 / $rate : 0) : $rate;
                    })
                    ->formatStateUsing(fn ($state) => $state > 0 ? number_format($state, 4) : '-')
                    ->color('primary')
                    ->tooltip('Crypto Exchange Spot (Stablecoins)'),

                TextColumn::make('shadow_buy_rate')
                    ->label('Покупка')
                    ->state(function (Currency $record) {
                        $baseCode = session('currency_base', 'RUB');
                        $rate = ($baseCode === 'RUB') ? $record->shadow_buy_rate : ($record->shadow_buy_rate / (Currency::where('code', $baseCode)->first()?->rate_to_rub ?: 1.0));
                        return session('currency_view_mode') === 'inverse' ? ($rate > 0 ? 1 / $rate : 0) : $rate;
                    })
                    ->formatStateUsing(fn ($state) => $state > 0 ? number_format($state, 4) : '-')
                    ->color('success')
                    ->tooltip('P2P Buy Rate (Bid)'),

                TextColumn::make('shadow_sell_rate')
                    ->label('Продажа')
                    ->state(function (Currency $record) {
                        $baseCode = session('currency_base', 'RUB');
                        $rate = ($baseCode === 'RUB') ? $record->shadow_sell_rate : ($record->shadow_sell_rate / (Currency::where('code', $baseCode)->first()?->rate_to_rub ?: 1.0));
                        return session('currency_view_mode') === 'inverse' ? ($rate > 0 ? 1 / $rate : 0) : $rate;
                    })
                    ->formatStateUsing(fn ($state) => $state > 0 ? number_format($state, 4) : '-')
                    ->color('danger')
                    ->weight('bold')
                    ->tooltip('P2P Sell Rate (Ask)'),


                IconColumn::make('has_spot_liquidity')
                    ->label('Шлюз')
                    ->icon(fn ($record): string => 
                        $record->spot_rate_usdt > 0 ? 'heroicon-o-bolt' : ($record->tradfi_rate > 0 ? 'heroicon-o-banknotes' : 'heroicon-o-user-group')
                    )
                    ->color(fn ($record): string => 
                        $record->spot_rate_usdt > 0 ? 'success' : ($record->tradfi_rate > 0 ? 'info' : 'gray')
                    )
                    ->tooltip(fn ($record) => 
                        $record->spot_rate_usdt > 0 ? 'Institutional Spot (USDT/Crypto)' : ($record->tradfi_rate > 0 ? 'Interbank TradFi (Forex)' : 'Shadow Market Only (P2P)')
                    ),

                TextColumn::make('liquidity_stress_index')
                    ->label('LSI')
                    ->formatStateUsing(fn ($state) => number_format($state * 100, 0) . '%')
                    ->badge()
                    ->color(fn (float $state): string => 
                        $state > 0.6 ? 'danger' : ($state > 0.3 ? 'warning' : 'success')
                    )
                    ->tooltip('Liquidity Stress (Blackout Nodes Percentage)')
                    ->sortable(),

                TextColumn::make('observability_score')
                    ->label('Observability')
                    ->formatStateUsing(fn ($state) => number_format($state * 100, 0) . '%')
                    ->badge()
                    ->color(fn (float $state): string => 
                        $state < 0.3 ? 'danger' : ($state < 0.7 ? 'warning' : 'success')
                    )
                    ->tooltip('Fraction of Expected Tiers Visible (Topology Health)')
                    ->sortable(),
                
                IconColumn::make('is_auto_update')
                    ->label(__('admin.finance.fields.auto'))
                    ->boolean(),
            ])
            ->headerActions([
                Action::make('toggle_view_mode')
                    ->label(fn() => session('currency_view_mode') === 'inverse' ? 'Режим: 1 ед. / База' : 'Режим: База / 1 ед.')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('secondary')
                    ->action(function () {
                        $current = session('currency_view_mode', 'standard');
                        session(['currency_view_mode' => $current === 'standard' ? 'inverse' : 'standard']);
                    }),
                Action::make('market_selector')
                    ->label("Рынок: " . session('currency_base', 'RUB'))
                    ->icon('heroicon-o-chart-bar-square')
                    ->color('warning')
                    ->modalHeading('Base Currency Switcher')
                    ->modalWidth('xl')
                    ->form([
                        \Filament\Forms\Components\ToggleButtons::make('base_currency')
                            ->label('Выберите базовую валюту терминала')
                            ->options(function() {
                                $codes = ['RUB', 'USD', 'EUR', 'TRY', 'GEL', 'AED', 'KZT', 'THB'];
                                $res = [];
                                foreach ($codes as $code) {
                                    $res[$code] = $code;
                                }
                                return $res;
                            })
                            ->default(session('currency_base', 'USD'))
                            ->columns(4)
                            ->gridDirection('row'),
                    ])
                    ->action(function (array $data): void {
                        $base = $data['base_currency'] ?? 'USD';
                        session(['currency_base' => $base]);
                    }),
            ])
            ->actions([
                Action::make('refresh_node')
                    ->label('Обновить')
                    ->tooltip('Синхронизировать котировки для этого узла в фоне')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->action(function (Currency $record) {
                        \App\Jobs\SyncCurrencyRatesBackgroundJob::dispatch(
                            auth()->id(), 
                            $record->code
                        );

                        Notification::make()
                            ->title('⚡️ Запрос отправлен')
                            ->body('Обновление для ' . $record->code . ' запущено в фоне.')
                            ->info()
                            ->send();
                    }),
                Action::make('add_to_pairs')
                    ->label('В активные')
                    ->icon('heroicon-o-link')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Активировать торговую пару?')
                    ->modalDescription(fn (Currency $record) => "Опубликовать прямой коридор " . session('currency_base', 'USD') . " / " . $record->code . " с текущими котировками?")
                    ->action(function (Currency $record) {
                        $baseCode = session('currency_base', 'USD');
                        $base = Currency::where('code', $baseCode)->first();
                        
                        if ($base && $base->id !== $record->id) {
                            $bRate = $base->rate_to_rub ?: 1.0;
                            $bOff  = $base->official_rate ?: 1.0;
                            $bTf   = $base->tradfi_rate ?: 1.0;
                            $bSpot = $base->spot_rate_usdt ?: 1.0;
                            $bP2p  = $base->p2p_rate_usdt ?: 1.0;

                            \App\Models\CurrencyPair::updateOrCreate(
                                [
                                    'base_currency_id' => $base->id,
                                    'target_currency_id' => $record->id,
                                ],
                                [
                                    'is_active' => true,
                                    'official_rate' => $record->official_rate / $bOff,
                                    'tradfi_rate'   => $record->tradfi_rate / $bTf,
                                    'spot_rate'     => $record->spot_rate_usdt / $bSpot,
                                    'p2p_rate'      => $record->p2p_rate_usdt / $bP2p,
                                    'current_rate'  => $record->rate_to_rub / $bRate,
                                ]
                            );
                            Notification::make()->title('Торговая пара активирована')->success()->send();
                        }
                    }),
                Action::make('analytics')
                    ->label('Аналитика')
                    ->icon('heroicon-o-presentation-chart-line')
                    ->color('success')
                    ->modalHeading(fn (Currency $record) => "Sovereign Analytics: {$record->code}")
                    ->modalWidth('6xl')
                    ->modalFooterActions([])
                    ->action(fn () => null)
                    ->modalContent(fn (Currency $record) => view('filament.pages.currency-analytics-modal', ['record' => $record])),
                Action::make('ai_insight')
                    ->label('AI Анализ')
                    ->icon('heroicon-o-cpu-chip')
                    ->color('primary')
                    ->modalHeading(fn (Currency $record) => "AI Strategic Insight: {$record->code}")
                    ->modalWidth('xl')
                    ->modalFooterActions([])
                    ->modalContent(fn (Currency $record) => view('filament.pages.currency-ai-modal', ['record' => $record])),
                \Filament\Actions\ViewAction::make(),
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LiquidityMethodsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $baseCode = session('currency_base', 'RUB');
        return parent::getEloquentQuery()
            ->orderByRaw("CASE WHEN code = ? THEN 0 ELSE 1 END", [$baseCode])
            ->orderBy('code');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCurrencies::route('/'),
            'view' => Pages\ViewCurrency::route('/{record}'),
        ];
    }
}
