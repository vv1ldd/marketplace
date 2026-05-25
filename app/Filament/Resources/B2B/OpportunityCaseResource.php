<?php

namespace App\Filament\Resources\B2B;

use App\Filament\Resources\B2B\Pages\ListOpportunityCases;
use App\Models\OpportunityCase;
use App\Services\OpportunityLifecycleService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class OpportunityCaseResource extends Resource
{
    protected static ?string $model = OpportunityCase::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'Кейсы возможностей';

    protected static ?int $navigationSort = 19;

    public static function getLabel(): ?string
    {
        return 'Кейс возможности';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Кейсы возможностей';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Магазины и B2B';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                Section::make('Кейс')
                    ->schema([
                        TextInput::make('canonical_query')
                            ->label('Канонический запрос')
                            ->disabled(),
                        TextInput::make('status')
                            ->label('Статус')
                            ->disabled(),
                        TextInput::make('owner_team')
                            ->label('Ответственная команда')
                            ->disabled(),
                        TextInput::make('sla_due_at')
                            ->label('SLA до')
                            ->disabled(),
                        TextInput::make('action_type')
                            ->label('Действие')
                            ->disabled(),
                        Textarea::make('action_details')
                            ->label('Детали действия')
                            ->disabled(),
                    ]),

                Section::make('Эффективность похожих действий')
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('effectiveness')
                            ->label('Исторический эффект')
                            ->content(function (?OpportunityCase $record): HtmlString|string {
                                if (! $record || ! $record->action_type) {
                                    return 'Действие еще не выбрано.';
                                }

                                $summary = app(OpportunityLifecycleService::class)->actionEffectiveness($record->action_type);
                                if ((int) $summary['cases_count'] === 0) {
                                    return 'Пока нет закрытых кейсов по этому типу действия.';
                                }

                                return new HtmlString(sprintf(
                                    '<div style="padding: 12px; border: 2px solid #111827; background: #f8fafc;">
                                        <strong>%s</strong><br>
                                        Успешность: <strong>%s%%</strong><br>
                                        Среднее снижение score: <strong>%s</strong><br>
                                        Средний рост GMV: <strong>%s%%</strong><br>
                                        Средний рост конверсии: <strong>%s%%</strong>
                                    </div>',
                                    static::actionLabel($record->action_type),
                                    $summary['success_rate'],
                                    $summary['avg_score_delta'],
                                    $summary['avg_gmv_growth_percentage'],
                                    $summary['avg_conversion_growth_percentage'],
                                ));
                            }),
                    ]),

                Section::make('Baseline до действия')
                    ->columns(3)
                    ->schema([
                        TextInput::make('before_opportunity_score')->label('Score до')->disabled(),
                        TextInput::make('before_search_volume')->label('Поиски до')->disabled(),
                        TextInput::make('before_views_count')->label('Просмотры до')->disabled(),
                        TextInput::make('before_carts_count')->label('Корзины до')->disabled(),
                        TextInput::make('before_orders_count')->label('Заказы до')->disabled(),
                        TextInput::make('before_gmv')->label('GMV до')->disabled(),
                        TextInput::make('before_diagnosis')->label('Диагноз до')->disabled(),
                    ])
                    ->columnSpanFull(),

                Section::make('Outcome после действия')
                    ->columns(3)
                    ->schema([
                        TextInput::make('after_opportunity_score')->label('Score после')->disabled(),
                        TextInput::make('after_search_volume')->label('Поиски после')->disabled(),
                        TextInput::make('after_views_count')->label('Просмотры после')->disabled(),
                        TextInput::make('after_carts_count')->label('Корзины после')->disabled(),
                        TextInput::make('after_orders_count')->label('Заказы после')->disabled(),
                        TextInput::make('after_gmv')->label('GMV после')->disabled(),
                        TextInput::make('after_diagnosis')->label('Диагноз после')->disabled(),
                        TextInput::make('gmv_growth_percentage')->label('Рост GMV, %')->disabled(),
                        TextInput::make('conversion_growth_percentage')->label('Рост конверсии, %')->disabled(),
                    ])
                    ->columnSpanFull(),
            ]),
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

                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        OpportunityCase::STATUS_OPEN => 'warning',
                        OpportunityCase::STATUS_IN_PROGRESS => 'info',
                        OpportunityCase::STATUS_RESOLVED => 'success',
                        OpportunityCase::STATUS_ARCHIVED => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        OpportunityCase::STATUS_OPEN => 'Open',
                        OpportunityCase::STATUS_IN_PROGRESS => 'In progress',
                        OpportunityCase::STATUS_RESOLVED => 'Resolved',
                        OpportunityCase::STATUS_ARCHIVED => 'Archived',
                        default => $state,
                    })
                    ->sortable(),

                TextColumn::make('owner_team')
                    ->label('Owner')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? static::teamLabel($state) : '—')
                    ->color(fn (?string $state): string => match ($state ?? '') {
                        OpportunityCase::TEAM_CONTENT => 'warning',
                        OpportunityCase::TEAM_COMMERCIAL => 'info',
                        OpportunityCase::TEAM_PAYMENTS => 'danger',
                        OpportunityCase::TEAM_SUPPLIERS => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('sla_due_at')
                    ->label('SLA')
                    ->dateTime()
                    ->color(fn (OpportunityCase $record): string => $record->sla_due_at && $record->sla_due_at->isPast() && $record->status !== OpportunityCase::STATUS_RESOLVED ? 'danger' : 'gray')
                    ->sortable(),

                TextColumn::make('auto_created')
                    ->label('Auto')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Auto' : 'Manual')
                    ->color(fn ($state): string => $state ? 'success' : 'gray')
                    ->sortable(),

                TextColumn::make('action_type')
                    ->label('Действие')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => $state ? static::actionLabel($state) : '—')
                    ->sortable(),

                TextColumn::make('before_opportunity_score')
                    ->label('Score до')
                    ->numeric(1)
                    ->sortable(),

                TextColumn::make('after_opportunity_score')
                    ->label('Score после')
                    ->numeric(1)
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('gmv_growth_percentage')
                    ->label('GMV эффект')
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : "{$state}%")
                    ->sortable(),

                TextColumn::make('conversion_growth_percentage')
                    ->label('Конверсия эффект')
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : "{$state}%")
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Action::make('recordAction')
                    ->label('Зафиксировать действие')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->form([
                        Select::make('action_type')
                            ->label('Тип действия')
                            ->options(static::actionOptions())
                            ->required(),
                        Textarea::make('action_details')
                            ->label('Что сделал оператор')
                            ->rows(4)
                            ->required(),
                    ])
                    ->fillForm(fn (OpportunityCase $record): array => [
                        'action_type' => $record->action_type ?: app(OpportunityLifecycleService::class)->recommendedActionForDiagnosis($record->before_diagnosis),
                        'action_details' => $record->action_details,
                    ])
                    ->action(function (OpportunityCase $record, array $data): void {
                        app(OpportunityLifecycleService::class)->recordAction(
                            $record,
                            (string) $data['action_type'],
                            (string) $data['action_details'],
                        );

                        Notification::make()
                            ->title('Действие зафиксировано')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (OpportunityCase $record): bool => $record->status !== OpportunityCase::STATUS_RESOLVED),

                Action::make('resolve')
                    ->label('Завершить и оценить')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (OpportunityCase $record): void {
                        app(OpportunityLifecycleService::class)->resolveCase($record);

                        Notification::make()
                            ->title('Кейс закрыт и оценен')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (OpportunityCase $record): bool => $record->status === OpportunityCase::STATUS_IN_PROGRESS),

                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOpportunityCases::route('/'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function actionOptions(): array
    {
        return [
            OpportunityCase::ACTION_ADD_SUPPLY => 'Добавить ассортимент',
            OpportunityCase::ACTION_IMPROVE_PRICING => 'Улучшить цену / карточку',
            OpportunityCase::ACTION_FIX_CHECKOUT => 'Починить чекаут',
            OpportunityCase::ACTION_INVESTIGATE => 'Исследовать вручную',
        ];
    }

    public static function actionLabel(string $action): string
    {
        return static::actionOptions()[$action] ?? $action;
    }

    /**
     * @return array<string, string>
     */
    public static function teamOptions(): array
    {
        return [
            OpportunityCase::TEAM_CONTENT => 'Content Team',
            OpportunityCase::TEAM_COMMERCIAL => 'Commercial Team',
            OpportunityCase::TEAM_PAYMENTS => 'Payments Team',
            OpportunityCase::TEAM_SUPPLIERS => 'Supplier Team',
            OpportunityCase::TEAM_OPERATIONS => 'Operations Team',
        ];
    }

    public static function teamLabel(string $team): string
    {
        return static::teamOptions()[$team] ?? $team;
    }
}
