<?php

namespace App\Filament\Resources\B2B;

use App\Models\DemandGap;
use App\Filament\Resources\B2B\Pages\ListDemandGaps;
use App\Services\OpportunityLifecycleService;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\HtmlString;

class DemandGapResource extends Resource
{
    protected static ?string $model = DemandGap::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationLabel = 'Пульс спроса (Inbox)';

    public static function getLabel(): ?string
    {
        return 'Дефицит спроса';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Пульс спроса (Inbox)';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Магазины и B2B';
    }

    protected static ?int $navigationSort = 18;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(1)->schema([
                Section::make('🚨 Диагностика & Действие (Root Cause Engine)')
                    ->schema([
                        Placeholder::make('purchase_recommendation')
                            ->label('Результаты автоматического анализа воронки')
                            ->content(function ($record) {
                                if (! $record) {
                                    return '—';
                                }

                                // Diagnosis Graph List representing all calculated causes
                                $graph = $record->opportunity_diagnosis_graph ?? [];
                                $diagnostic = "<div style='margin-bottom: 20px; padding: 16px; border: 2px solid #000; background-color: #f8fafc; border-radius: 4px;'>";
                                $diagnostic .= "<div style='font-size: 1.1rem; font-weight: bold; margin-bottom: 12px;'>📊 Карта диагнозов воронки (Diagnosis Graph)</div>";
                                if (empty($graph)) {
                                    $diagnostic .= "<div style='color: #6b7280;'>Диагнозы отсутствуют.</div>";
                                } else {
                                    foreach ($graph as $item) {
                                        $causeCode = $item['cause'];
                                        $scoreVal = $item['score'];
                                        $label = match ($causeCode) {
                                            'checkout_dropoff' => '⚠️ Сбой чекаута (Checkout Dropoff)',
                                            'catalog_gap' => '🔍 Дефицит каталога (Catalog Gap)',
                                            'pricing_issue' => '💸 Цена / Контент (Pricing / Card)',
                                            'healthy' => '✅ В норме (Healthy)',
                                            'insufficient_data' => '📉 Мало данных (Insufficient Data)',
                                            default => $causeCode,
                                        };

                                        $color = match ($causeCode) {
                                            'checkout_dropoff' => '#ef4444',
                                            'catalog_gap' => '#f97316',
                                            'pricing_issue' => '#0ea5e9',
                                            'healthy' => '#22c55e',
                                            default => '#6b7280',
                                        };

                                        $diagnostic .= "<div style='margin-bottom: 8px;'>";
                                        $diagnostic .= "<div style='display: flex; justify-content: space-between; margin-bottom: 4px; font-family: ui-monospace, monospace; font-size: 0.9rem;'>";
                                        $diagnostic .= "<span>{$label}</span>";
                                        $diagnostic .= "<span style='font-weight: bold; color: {$color};'>{$scoreVal}%</span>";
                                        $diagnostic .= "</div>";
                                        $diagnostic .= "<div style='width: 100%; background-color: #e2e8f0; height: 8px; border-radius: 4px;'>";
                                        $diagnostic .= "<div style='background-color: {$color}; width: {$scoreVal}%; height: 8px; border-radius: 4px;'></div>";
                                        $diagnostic .= "</div>";
                                        $diagnostic .= "</div>";
                                    }
                                }
                                $diagnostic .= "</div>";

                                // Action Checklist mapping for EACH cause with score >= 30%
                                $checklist = "";
                                $playbooksRendered = 0;
                                if (!empty($graph)) {
                                    foreach ($graph as $item) {
                                        $causeCode = $item['cause'];
                                        $scoreVal = $item['score'];

                                        if ($scoreVal < 30.0 && $causeCode !== 'insufficient_data') {
                                            continue;
                                        }

                                        if ($causeCode === 'checkout_dropoff') {
                                            $checklist .= "<div style='margin-bottom: 20px; padding: 16px; border: 2px solid #e11d48; background-color: #fff1f2; color: #9f1239; border-radius: 4px;'>";
                                            $checklist .= "<div style='font-size: 1.1rem; font-weight: bold; margin-bottom: 10px;'>⚡️ ПЛЕЙБУК: Починить чекаут (Fix checkout) [Влияние: {$scoreVal}%]</div>";
                                            $checklist .= "<div style='margin-bottom: 8px;'>Покупатели проявляют максимальный интерес (активно кладут в корзину), но оплаты не завершаются. Рекомендуется выполнить чек-лист:</div>";
                                            $checklist .= "<ul style='margin-top: 8px; margin-left: 20px; list-style-type: decimal;'>";
                                            $checklist .= "<li style='margin-bottom: 4px;'>Проверить работоспособность платежных шлюзов для региона/валюты товара.</li>";
                                            $checklist .= "<li style='margin-bottom: 4px;'>Проверить правильность начисления комиссий и сборов на этапе оформления.</li>";
                                            $checklist .= "<li style='margin-bottom: 4px;'>Убедиться, что баланс склада или лимиты внешних API поставщика не исчерпаны.</li>";
                                            $checklist .= "</ul></div>";
                                            $playbooksRendered++;
                                        } elseif ($causeCode === 'catalog_gap') {
                                            $checklist .= "<div style='margin-bottom: 20px; padding: 16px; border: 2px solid #ea580c; background-color: #fff7ed; color: #9a3412; border-radius: 4px;'>";
                                            $checklist .= "<div style='font-size: 1.1rem; font-weight: bold; margin-bottom: 10px;'>⚡️ ПЛЕЙБУК: Добавить товары (Add supply) [Влияние: {$scoreVal}%]</div>";
                                            $checklist .= "<div style='margin-bottom: 8px;'>Пользователи часто вводят этот поисковый запрос, но не находят товаров в каталоге или не переходят к просмотру. Рекомендуется выполнить чек-лист:</div>";
                                            $checklist .= "<ul style='margin-top: 8px; margin-left: 20px; list-style-type: decimal;'>";
                                            $checklist .= "<li style='margin-bottom: 4px;'>Связаться с дистрибьюторами и поставщиками цифровых товаров.</li>";
                                            $checklist .= "<li style='margin-bottom: 4px;'>Закупить и импортировать ваучеры/карты пополнения указанного бренда и номинала.</li>";
                                            $checklist .= "<li style='margin-bottom: 4px;'>Включить видимость товаров на канале storefront.</li>";
                                            $checklist .= "</ul></div>";
                                            $playbooksRendered++;
                                        } elseif ($causeCode === 'pricing_issue') {
                                            $checklist .= "<div style='margin-bottom: 20px; padding: 16px; border: 2px solid #0284c7; background-color: #f0f9ff; color: #0369a1; border-radius: 4px;'>";
                                            $checklist .= "<div style='font-size: 1.1rem; font-weight: bold; margin-bottom: 10px;'>⚡️ ПЛЕЙБУК: Проверить цену / Карточку (Improve pricing) [Влияние: {$scoreVal}%]</div>";
                                            $checklist .= "<div style='margin-bottom: 8px;'>Пользователи переходят на карточку товара, но крайне редко добавляют его в корзину. Рекомендуется выполнить чек-лист:</div>";
                                            $checklist .= "<ul style='margin-top: 8px; margin-left: 20px; list-style-type: decimal;'>";
                                            $checklist .= "<li style='margin-bottom: 4px;'>Провести мониторинг розничных цен у основных конкурентов.</li>";
                                            $checklist .= "<li style='margin-bottom: 4px;'>Снизить текущую цену на 5-10% или предложить скидку.</li>";
                                            $checklist .= "<li style='margin-bottom: 4px;'>Улучшить описание, добавить локализованные инструкции по активации и обновить визуальные медиа.</li>";
                                            $checklist .= "</ul></div>";
                                            $playbooksRendered++;
                                        }
                                    }
                                }

                                if ($playbooksRendered === 0) {
                                    $checklist = "<div style='margin-bottom: 20px; padding: 16px; border: 2px solid #6b7280; background-color: #f9fafb; color: #4b5563; border-radius: 4px;'>";
                                    $checklist .= "<div style='font-size: 1.1rem; font-weight: bold; margin-bottom: 6px;'>⚡️ РЕКОМЕНДУЕМОЕ ДЕЙСТВИЕ: Накопление данных</div>";
                                    $checklist .= "<div>Пока не зафиксировано критических отклонений или недостаточно поисковой статистики для вынесения рекомендаций.</div>";
                                    $checklist .= "</div>";
                                }

                                // Fetch the latest storefront search log to read exact request filters
                                $log = \App\Models\CatalogSearchLog::where('normalized_query', $record->canonical_query)
                                    ->whereNotNull('filters')
                                    ->orderByDesc('id')
                                    ->first();

                                $baseRecommendation = "";
                                if ($log && ! empty($log->filters)) {
                                    $brand = $log->filters['brand'] ?? null;
                                    $region = $log->filters['region'] ?? null;
                                    $faceValue = $log->filters['face_value'] ?? null;
                                    $currency = $log->filters['currency'] ?? null;
                                    $category = $log->filters['category'] ?? null;

                                    if ($brand) {
                                        $recommendation = "Закупите и добавьте в каталог цифровые карты пополнения **{$brand}**";
                                        if ($region) {
                                            $recommendation .= " для региона **" . strtoupper($region) . "**";
                                        }
                                        if ($faceValue) {
                                            $recommendation .= " номиналом **" . $faceValue . " " . ($currency ?: 'RUB') . "**";
                                        }
                                        $baseRecommendation = $recommendation . '. Это покроет неудовлетворенный спрос на сумму **' . number_format($record->estimated_lost_gmv, 2, '.', ' ') . ' ₽**.';
                                    } elseif ($category) {
                                        $baseRecommendation = "Закупите и подключите новые канонические товары в категории **" . ucwords($category) . "**. Это поможет вернуть упущенную выручку в размере **" . number_format($record->estimated_lost_gmv, 2, '.', ' ') . " ₽**.";
                                    }
                                }

                                if (empty($baseRecommendation)) {
                                    $baseRecommendation = "Закупите канонические товары, релевантные запросу **\"" . $record->canonical_query . "\"**. Это восстановит упущенные продажи на сумму **" . number_format($record->estimated_lost_gmv, 2, '.', ' ') . " ₽**.";
                                }

                                return new HtmlString($diagnostic . $checklist . "<div>" . $baseRecommendation . "</div>");
                            })
                    ]),

                Section::make('Аналитика спроса')
                    ->columns(2)
                    ->schema([
                        TextInput::make('canonical_query')
                            ->label('Канонический запрос')
                            ->disabled(),
                        TextInput::make('search_volume')
                            ->label('Поисков (Searches)')
                            ->disabled(),
                        TextInput::make('views_count')
                            ->label('Просмотров (Views)')
                            ->disabled(),
                        TextInput::make('carts_count')
                            ->label('В корзину (Carts)')
                            ->disabled(),
                        TextInput::make('attributed_orders_count')
                            ->label('Продаж (Orders)')
                            ->disabled(),
                        TextInput::make('zero_results_count')
                            ->label('Поисков без результатов')
                            ->disabled(),
                        TextInput::make('average_results_count')
                            ->label('Среднее кол-во результатов')
                            ->disabled(),
                        TextInput::make('estimated_lost_gmv')
                            ->label('Оценка упущенной выручки (Model)')
                            ->disabled(),
                        TextInput::make('opportunity_score')
                            ->label('Индекс возможностей (Opportunity Score)')
                            ->disabled(),
                        TextInput::make('opportunity_diagnosis')
                            ->label('Вынесенный диагноз')
                            ->disabled(),
                        TextInput::make('diagnosis_confidence')
                            ->label('Уверенность алгоритма (%)')
                            ->disabled(),
                    ])
            ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('canonical_query')
                    ->label('Канонический запрос')
                    ->searchable()
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('search_volume')
                    ->label('Поиски')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('views_count')
                    ->label('Просмотры')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('carts_count')
                    ->label('В корзину')
                    ->badge()
                    ->color('warning')
                    ->sortable(),

                TextColumn::make('attributed_orders_count')
                    ->label('Продажи')
                    ->numeric(1)
                    ->sortable(),

                TextColumn::make('opportunity_diagnosis')
                    ->label('Диагноз (Diagnosis)')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state ?? '') {
                        'checkout_dropoff' => 'danger',
                        'catalog_gap' => 'warning',
                        'pricing_issue' => 'info',
                        'healthy' => 'success',
                        'insufficient_data' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state ?? '') {
                        'checkout_dropoff' => '⚠️ Сбой чекаута',
                        'catalog_gap' => '🔍 Дефицит каталога',
                        'pricing_issue' => '💸 Цена / Контент',
                        'healthy' => '✅ В норме',
                        'insufficient_data' => '📉 Мало данных',
                        default => '—',
                    })
                    ->sortable(),

                TextColumn::make('diagnosis_confidence')
                    ->label('Уверенность')
                    ->formatStateUsing(fn ($state) => $state > 0 ? "{$state}%" : '—')
                    ->sortable(),

                TextColumn::make('opportunity_diagnosis_graph')
                    ->label('Вторичные причины')
                    ->formatStateUsing(function ($state) {
                        if (!is_array($state) || count($state) <= 1) {
                            return '—';
                        }
                        $secondary = [];
                        foreach (array_slice($state, 1) as $item) {
                            $score = $item['score'] ?? 0;
                            if ($score < 30.0) {
                                continue;
                            }
                            $cause = $item['cause'] ?? '';
                            $name = match ($cause) {
                                'checkout_dropoff' => 'Сбой чекаута',
                                'catalog_gap' => 'Дефицит каталога',
                                'pricing_issue' => 'Цена/Контент',
                                'healthy' => 'В норме',
                                default => $cause,
                            };
                            $secondary[] = "{$name} ({$score}%)";
                        }
                        return empty($secondary) ? '—' : implode(', ', $secondary);
                    }),

                TextColumn::make('estimated_lost_gmv')
                    ->label('Упущенный GMV (Model)')
                    ->money('RUB')
                    ->sortable(),

                TextColumn::make('opportunity_score')
                    ->label('Индекс возможностей (Score)')
                    ->numeric(1)
                    ->badge()
                    ->color(fn ($state) => $state >= 70.0 ? 'success' : ($state >= 40.0 ? 'warning' : 'gray'))
                    ->sortable(),

                TextColumn::make('opportunity_cases_count')
                    ->label('Кейсы')
                    ->counts('opportunityCases')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('openCase')
                    ->label('Открыть кейс')
                    ->icon('heroicon-o-briefcase')
                    ->color('primary')
                    ->action(function (DemandGap $record): void {
                        $case = app(OpportunityLifecycleService::class)->openCase($record);

                        Notification::make()
                            ->title($case->wasRecentlyCreated ? 'Кейс создан' : 'Активный кейс уже есть')
                            ->body("Кейс #{$case->id} для запроса \"{$case->canonical_query}\".")
                            ->success()
                            ->send();
                    }),
                \Filament\Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                //
            ])
            ->defaultSort('opportunity_score', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDemandGaps::route('/'),
        ];
    }
}
