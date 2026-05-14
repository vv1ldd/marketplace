<?php

namespace App\Filament\Partner\Resources\Tickets;

use App\Filament\Partner\Resources\Tickets\Pages\ListTickets;
use App\Filament\Partner\Resources\Tickets\Pages\ViewTicket;
use App\Models\Ticket;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\View;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    public static function getNavigationLabel(): string
    {
        return 'Поддержка';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('subject')
                    ->label('Тема обращения')
                    ->required()
                    ->maxLength(255),
                
                Select::make('priority')
                    ->label('Приоритет')
                    ->options([
                        'low' => 'Низкий',
                        'medium' => 'Средний',
                        'high' => 'Высокий',
                    ])
                    ->default('medium')
                    ->required(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->schema([
                        // Левая колонка: Переписка
                        Group::make([
                            View::make('messages')
                                ->view('filament.resources.tickets.components.messages-list'),
                        ])
                        ->key('messages')
                        ->columnSpan(['default' => 1, 'lg' => 2]),

                        // Правая колонка: Детали
                        Group::make([
                            Section::make('Детали обращения')
                                ->key('details')
                                ->schema([
                                    Grid::make(1)
                                        ->schema([
                                            TextEntry::make('subject')
                                                ->label('Тема')
                                                ->weight('bold'),
                                            TextEntry::make('status')
                                                ->label('Статус')
                                                ->badge()
                                                ->color(fn (string $state): string => match ($state) {
                                                    'open' => 'danger',
                                                    'in_progress' => 'warning',
                                                    'closed' => 'success',
                                                    default => 'gray',
                                                })
                                                ->formatStateUsing(fn (string $state): string => match ($state) {
                                                    'open' => 'Новый',
                                                    'in_progress' => 'В работе',
                                                    'closed' => 'Решен',
                                                    default => $state,
                                                }),
                                            TextEntry::make('priority')
                                                ->label('Приоритет')
                                                ->badge()
                                                ->formatStateUsing(fn (string $state): string => match ($state) {
                                                    'low' => 'Низкий',
                                                    'medium' => 'Средний',
                                                    'high' => 'Высокий',
                                                    default => $state,
                                                }),
                                            TextEntry::make('created_at')
                                                ->label('Создан')
                                                ->dateTime()
                                                ->size('sm'),
                                        ]),
                                ])
                                ->compact(),
                        ])
                        ->columnSpan(['default' => 1, 'lg' => 1]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject')
            ->columns([
                TextColumn::make('subject')
                    ->label('Тема')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'danger',
                        'in_progress' => 'warning',
                        'closed' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open' => 'Отправлен',
                        'in_progress' => 'В работе',
                        'closed' => 'Закрыт',
                        default => $state,
                    }),

                TextColumn::make('priority')
                    ->label('Приоритет')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'low' => 'gray',
                        'medium' => 'info',
                        'high' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'low' => 'Низкий',
                        'medium' => 'Средний',
                        'high' => 'Высокий',
                        default => $state,
                    }),

                TextColumn::make('updated_at')
                    ->label('Обновлен')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                ViewAction::make()
                    ->label('Просмотр'),
                Action::make('reply')
                    ->label('Ответить')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->form([
                        \Filament\Forms\Components\Textarea::make('message')
                            ->label('Ваше сообщение')
                            ->required()
                            ->rows(5),
                    ])
                    ->action(function (Ticket $record, array $data): void {
                        $record->messages()->create([
                            'seller_id' => auth()->id(),
                            'message' => $data['message'],
                            'is_admin_reply' => false,
                        ]);

                        $record->update([
                            'last_reply_at' => now(),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Сообщение отправлено')
                            ->success()
                            ->send();
                    })
                    ->visible(fn ($record) => $record->status !== 'closed'),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
            'view' => ViewTicket::route('/{record}'),
        ];
    }
}
