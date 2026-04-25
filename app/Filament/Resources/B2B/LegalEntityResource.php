<?php

namespace App\Filament\Resources\B2B;

use App\Filament\Resources\B2B\Pages\CreateLegalEntity;
use App\Filament\Resources\B2B\Pages\EditLegalEntity;
use App\Filament\Resources\B2B\Pages\ListLegalEntities;
use App\Models\LegalEntity;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;

class LegalEntityResource extends Resource
{
    protected static ?string $model = LegalEntity::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-office';

    protected static string | \UnitEnum | null $navigationGroup = 'Управление';

    protected static ?string $label = 'Юр. лицо';

    protected static ?string $pluralLabel = 'Юр. лица';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Основная информация')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Полное наименование')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('short_name')
                        ->label('Краткое название')
                        ->maxLength(255),
                    TextInput::make('inn')
                        ->label('ИНН')
                        ->required()
                        ->length(fn ($state) => strlen($state) === 12 ? 12 : 10)
                        ->maxLength(12),
                    TextInput::make('kpp')
                        ->label('КПП')
                        ->length(9),
                    TextInput::make('ogrn')
                        ->label('ОГРН/ОГРНИП')
                        ->maxLength(15),
                    Select::make('user_id')
                        ->label('Владелец/Партнер')
                        ->relationship('user', 'email', fn ($query) => $query->role(['b2b_partner', 'super_admin']))
                        ->searchable()
                        ->required(),
                ]),
            
            Section::make('Адреса и контакты')
                ->columns(2)
                ->schema([
                    Textarea::make('legal_address')
                        ->label('Юридический адрес')
                        ->columnSpanFull(),
                    Textarea::make('postal_address')
                        ->label('Почтовый адрес')
                        ->columnSpanFull(),
                    TextInput::make('phone')
                        ->label('Телефон')
                        ->tel(),
                    TextInput::make('email')
                        ->label('Email для док-тов')
                        ->email(),
                    TextInput::make('director_name')
                        ->label('ФИО Руководителя')
                        ->columnSpanFull(),
                ]),

            Section::make('Банковские реквизиты')
                ->columns(2)
                ->schema([
                    TextInput::make('bank_name')
                        ->label('Название банка'),
                    TextInput::make('bank_bic')
                        ->label('БИК')
                        ->length(9),
                    TextInput::make('bank_account')
                        ->label('Расчетный счет')
                        ->length(20),
                    TextInput::make('bank_correspondent_account')
                        ->label('Корр. счет')
                        ->length(20),
                ]),

            Section::make('Статус')
                ->schema([
                    Toggle::make('is_active')
                        ->label('Активно')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('inn')
                    ->label('ИНН')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Наименование')
                    ->searchable()
                    ->limit(30)
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Партнер')
                    ->searchable(),
                TextColumn::make('shops_count')
                    ->label('Магазинов')
                    ->counts('shops'),
                IconColumn::make('is_active')
                    ->label('Статус')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Добавлено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLegalEntities::route('/'),
            'create' => CreateLegalEntity::route('/create'),
            'edit' => EditLegalEntity::route('/{record}/edit'),
        ];
    }
}
