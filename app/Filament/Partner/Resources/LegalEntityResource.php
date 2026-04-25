<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\LegalEntityResource\Pages\CreateLegalEntity;
use App\Filament\Partner\Resources\LegalEntityResource\Pages\EditLegalEntity;
use App\Filament\Partner\Resources\LegalEntityResource\Pages\ListLegalEntities;
use App\Models\LegalEntity;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LegalEntityResource extends Resource
{
    protected static ?string $model = LegalEntity::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $label = 'Юр. лицо';

    protected static ?string $pluralLabel = 'Мои организации';

    protected static ?string $navigationLabel = 'Организации';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Реквизиты организации')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Полное наименование')
                        ->required()
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

            Section::make('Адрес')
                ->schema([
                    Textarea::make('legal_address')
                        ->label('Юридический адрес')
                        ->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('inn')
                    ->label('ИНН')
                    ->copyable(),
                TextColumn::make('name')
                    ->label('Наименование')
                    ->searchable(),
                TextColumn::make('shops_count')
                    ->label('Магазинов')
                    ->counts('shops'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ]);
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
