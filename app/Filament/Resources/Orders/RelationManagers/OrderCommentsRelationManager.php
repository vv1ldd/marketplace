<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrderCommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Комментарии по заказу';

    protected static ?string $relationshipTitle = 'Комментарии по заказу';

    protected static ?string $recordTitleAttribute = 'Комментарий';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('user_id')
                    ->default(auth()->id()),

                Textarea::make('comment')
                    ->label('Комментарий')
                    ->required()
                    ->minLength(3)
                    ->columnSpanFull()
                    ->maxLength(2500),
            ]);
    }

    public function table(Table $table): Table
    {
        $is_executor = auth()->user()->hasRole('executor');

        $is_admin = auth()->user()->hasRole('super_admin');

        return $table
            ->recordTitleAttribute('Комментарии по заказу')
            ->columns([
                TextColumn::make('comment')
                    ->label('Комментарий')
                    ->limit(200)
                    ->searchable(),
                TextColumn::make('user_role')
                    ->label('Роль')
                    ->getStateUsing(fn($record) => $record->user ? $record->user->getRoleNames()->first() : 'system'),
//                TextColumn::make('user.email')
//                    ->getStateUsing(fn($record) => $record->user->email)
//                    ->label('Пользователь')
//                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime()
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make(),
//                AssociateAction::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible($is_admin),
                DeleteAction::make()->visible($is_admin)
//                DissociateAction::make(),
            ])
            ->emptyStateDescription('Комментарии по заказу отсутствуют')
            ->emptyStateIcon('heroicon-s-user-group')
            ->emptyStateActions([
                CreateAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
//                    DissociateBulkAction::make(),

                ])->visible($is_admin),
            ]);
    }
}
