<?php

namespace App\Filament\Support\Resources;

use App\Filament\Support\Resources\TicketResource\Pages;
use App\Models\Ticket;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
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
use Filament\Notifications\Notification;

class TicketResource extends Resource
{
    public static function getNavigationGroup(): ?string
    {
        return 'Поддержка';
    }

    protected static ?int $navigationSort = 40;

    protected static ?string $model = Ticket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'subject';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('subject')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->schema([
                        // Левая колонка: Переписка (2/3 ширины)
                        Group::make([
                            View::make('messages')
                                ->view('filament.resources.tickets.components.messages-list'),
                        ])
                        ->key('messages')
                        ->columnSpan(['default' => 1, 'lg' => 2]),

                        // Правая колонка: Сайдбар с деталями (1/3 ширины)
                        Group::make([
                            Section::make('Свойства тикета')
                                ->key('details')
                                ->schema([
                                    Grid::make(1)
                                        ->schema([
                                            TextEntry::make('shop.name')
                                                ->label('Магазин')
                                                ->icon('heroicon-m-shopping-bag')
                                                ->weight('bold'),
                                            TextEntry::make('order.order_id')
                                                ->label('Заказ')
                                                ->placeholder('Не привязан'),
                                            TextEntry::make('status')
                                                ->label('Статус')
                                                ->badge()
                                                ->color(fn (string $state): string => match ($state) {
                                                    'open' => 'danger',
                                                    'in_progress' => 'warning',
                                                    'closed' => 'success',
                                                    default => 'gray',
                                                }),
                                            TextEntry::make('priority')
                                                ->label('Приоритет')
                                                ->badge(),
                                            TextEntry::make('created_at')
                                                ->label('Создан')
                                                ->dateTime()
                                                ->size('sm'),
                                            TextEntry::make('updated_at')
                                                ->label('Обновлен')
                                                ->dateTime()
                                                ->size('sm'),
                                        ]),
                                ])
                                ->compact(),

                            Section::make('Клиент и заказы')
                                ->hidden(fn ($record) => ! $record->user_id)
                                ->schema([
                                    TextEntry::make('user.email')
                                        ->label('Email')
                                        ->icon('heroicon-m-envelope')
                                        ->visible(fn ($record) => !empty($record->user->email)),
                                    TextEntry::make('user.phone')
                                        ->label('Телефон')
                                        ->icon('heroicon-m-phone')
                                        ->visible(fn ($record) => !empty($record->user->phone)),
                                    View::make('customer_orders')
                                        ->view('filament.resources.tickets.components.customer-orders'),
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
                TextColumn::make('shop.name')
                    ->label('Магазин')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subject')
                    ->label('Тема')
                    ->searchable(),

                TextColumn::make('order.order_id')
                    ->label('Заказ')
                    ->placeholder('—')
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
                        'open' => 'Новый',
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
                    ->color('primary')
                    ->form([
                        \Filament\Forms\Components\Textarea::make('message')
                            ->label('Ваш ответ')
                            ->required()
                            ->rows(5),
                        \Filament\Forms\Components\Select::make('status')
                            ->label('Новый статус')
                            ->options([
                                'open' => 'Новый',
                                'in_progress' => 'В работе',
                                'closed' => 'Закрыт',
                            ])
                            ->default('in_progress'),
                    ])
                    ->action(function (Ticket $record, array $data): void {
                        $record->messages()->create([
                            'user_id' => auth()->id(),
                            'message' => $data['message'],
                            'is_admin_reply' => true,
                        ]);

                        $record->update([
                            'status' => $data['status'],
                            'last_reply_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Ответ отправлен')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
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
            'index' => Pages\ListTickets::route('/'),
            'view' => Pages\ViewTicket::route('/{record}'),
        ];
    }
}
