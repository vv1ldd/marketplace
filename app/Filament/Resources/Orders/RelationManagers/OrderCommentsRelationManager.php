<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Models\Customer;
use App\Models\Seller;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrderCommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    public static function canCreateForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    protected static ?string $title = 'Комментарии';

    protected static ?string $relationshipTitle = 'Комментарии';

    protected static ?string $recordTitleAttribute = 'comment';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('user_id')
                    ->default(auth()->id()),

                Hidden::make('user_type')
                    ->default(fn () => get_class(auth()->user())),

                Textarea::make('comment')
                    ->label(__('admin.orders.comment'))
                    ->required()
                    ->minLength(3)
                    ->columnSpanFull()
                    ->maxLength(2500),
            ]);
    }

    public function table(Table $table): Table
    {
        $is_admin = auth()->user()->hasRole('super_admin');

        return $table
            ->recordTitleAttribute('Комментарии')
            ->columns([
                TextColumn::make('comment')
                    ->label(__('admin.orders.comment'))
                    ->limit(200)
                    ->wrap()
                    ->formatStateUsing(function ($state) {
                        if (\Filament\Facades\Filament::getCurrentPanel()?->getId() !== 'partner') {
                            return $state;
                        }

                        // Маскируем Email
                        $state = preg_replace('/[a-z0-9\._%+-]+@[a-z0-9\.-]+\.[a-z]{2,}/i', '[email скрыт]', (string) $state);

                        // Маскируем Телефон (напр. +7 (999) 999-99-99 или 89991234567)
                        $state = preg_replace('/(\+?\d[\d\(\)\s-]{8,}\d)/', '[телефон скрыт]', (string) $state);

                        return $state;
                    })
                    ->searchable(),

                TextColumn::make('author')
                    ->label('Автор')
                    ->getStateUsing(function ($record) {
                        $isPartner = \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'partner';

                        if ($record->user_id === null) {
                            return 'Система';
                        }

                        // Старые строки без user_type: morphTo ломает SQL — разрешаем вручную
                        if (empty($record->user_type)) {
                            if ($c = Customer::find($record->user_id)) {
                                return $isPartner ? 'Клиент' : ($c->getFullName() ?: ($c->first_name ?: $c->email ?: 'Клиент'));
                            }
                            if ($s = Seller::find($record->user_id)) {
                                return $s->getFullName() ?: ($s->first_name ?: 'Партнер');
                            }
                            if ($u = User::find($record->user_id)) {
                                if ($u->hasAnyRole(['super_admin', 'manager', 'executor', 'support'])) {
                                    return 'Система';
                                }

                                return $isPartner ? 'Клиент' : ($u->getFullName() ?: ($u->first_name ?: $u->email));
                            }

                            return 'Система';
                        }

                        $user = $record->user;

                        if ($user instanceof Seller) {
                            return $user->getFullName() ?: ($user->first_name ?: 'Партнер');
                        }

                        if (! $user || ($user instanceof User && $user->hasAnyRole(['super_admin', 'manager', 'executor', 'support']))) {
                            return 'Система';
                        }

                        return $isPartner ? 'Клиент' : ($user->getFullName() ?: ($user->first_name ?: $user->email));
                    })
                    ->badge()
                    ->color(fn ($state) => in_array($state, ['Система', 'Клиент']) ? 'gray' : 'info'),

                TextColumn::make('created_at')
                    ->label(__('admin.orders.created'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                // Используем обычный Action вместо CreateAction, чтобы обойти ограничения v4
                Action::make('add_comment')
                    ->label('Добавить комментарий')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Textarea::make('comment')
                            ->label('Ваш комментарий')
                            ->required()
                            ->minLength(3)
                            ->maxLength(2500),
                    ])
                    ->action(function (array $data) {
                        $this->getOwnerRecord()->comments()->create([
                            'comment' => $data['comment'],
                            'user_id' => auth()->id(),
                            'user_type' => get_class(auth()->user()),
                        ]);
                    })
                    ->visible(true),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateDescription(__('admin.orders.empty_comments'))
            ->emptyStateIcon('heroicon-s-chat-bubble-left-right')
            ->emptyStateActions([
                // Здесь тоже заменим на обычный Action, если понадобится
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ])->visible($is_admin),
            ]);
    }
}
