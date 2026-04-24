<?php

namespace App\Filament\Resources\ShopResource\RelationManagers;

use App\Models\ApiApplication;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

class ApiApplicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'apiApplications';

    protected static ?string $title = 'API Ключи (Приложения)';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Название')
                ->required()
                ->maxLength(255),
            TextInput::make('domain')
                ->label('Домен')
                ->placeholder('example.com')
                ->maxLength(255),
            Select::make('type')
                ->label('Тип доступа')
                ->options([
                    ApiApplication::TYPE_SHOP => 'Магазин (Только этот шоп)',
                    ApiApplication::TYPE_PLATFORM => 'Платформа (Все данные)',
                ])
                ->default(ApiApplication::TYPE_SHOP)
                ->required(),
            TextInput::make('token')
                ->label('Токен')
                ->password()
                ->revealable()
                ->default(fn () => \Illuminate\Support\Str::random(60))
                ->required(),
            \Filament\Forms\Components\Toggle::make('is_active')
                ->label('Активен')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        ApiApplication::TYPE_SHOP => 'Магазин',
                        ApiApplication::TYPE_PLATFORM => 'Платформа',
                        default => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        ApiApplication::TYPE_SHOP => 'info',
                        ApiApplication::TYPE_PLATFORM => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('token')
                    ->label('Токен')
                    ->limit(10)
                    ->copyable(),
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
