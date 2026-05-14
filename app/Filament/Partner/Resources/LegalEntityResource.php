<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\LegalEntityResource\Pages\CreateLegalEntity;
use App\Filament\Partner\Resources\LegalEntityResource\Pages\EditLegalEntity;
use App\Filament\Partner\Resources\LegalEntityResource\Pages\ListLegalEntities;
use App\Filament\Resources\B2B\RelationManagers\ShopsRelationManager;
use App\Models\LegalEntity;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use App\Services\DaDataService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LegalEntityResource extends Resource
{
    protected static ?string $model = LegalEntity::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $label = 'admin.b2b.legal_entity';

    protected static ?string $tenantOwnershipRelationshipName = 'shops';

    protected static ?string $pluralLabel = 'admin.b2b.my_entities';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'admin.b2b.legal_entities';

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('seller_id', auth()->id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.b2b.sections.main_info'))
                ->columns(2)
                ->schema([
                    Select::make('country_code')
                        ->label('Юрисдикция (Страна)')
                        ->options([
                            'RU' => '🇷🇺 Россия (RU)',
                            'KZ' => '🇰🇿 Казахстан (KZ)',
                            'BY' => '🇧🇾 Беларусь (BY)',
                            'UZ' => '🇺🇿 Узбекистан (UZ)',
                            'OTHER' => '🌍 Другая страна',
                        ])
                        ->default(fn() => session('pending_country', 'RU'))
                        ->searchable()
                        ->live()
                        ->required()
                        ->columnSpanFull(),

                    TextInput::make('inn')
                        ->label(fn ($get) => $get('country_code') === 'RU' ? __('admin.b2b.fields.inn') : 'Рег. номер / ИНН')
                        ->default(fn() => session('pending_inn'))
                        ->required()
                        ->length(fn ($state, $get) => $get('country_code') === 'RU' ? (strlen($state) === 12 ? 12 : 10) : null)
                        ->maxLength(fn ($get) => $get('country_code') === 'RU' ? 12 : 50)
                        ->suffixAction(
                            Action::make('lookupByInn')
                                ->icon('heroicon-m-magnifying-glass')
                                ->visible(fn ($get) => $get('country_code') === 'RU')
                                ->action(function ($state, Set $set) {
                                    if (empty($state)) {
                                        Notification::make()->title('Введите ИНН')->warning()->send();
                                        return;
                                    }
                                    
                                    $service = new DaDataService;
                                    $data = $service->findByInn($state);

                                    if (! $data) {
                                        Notification::make()->title('Организация не найдена')->danger()->send();
                                        return;
                                    }

                                    $set('name', $data['name']['full_with_opf'] ?? '');
                                    $set('kpp', $data['kpp'] ?? '');
                                    $set('ogrn', $data['ogrn'] ?? '');
                                    $set('legal_address', $data['address']['value'] ?? '');
                                    $set('director_name', $data['management']['name'] ?? '');

                                    Notification::make()->title('Данные подтянуты! ✨')->success()->send();
                                })
                        ),
                    TextInput::make('name')
                        ->label(__('admin.b2b.fields.full_name'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('kpp')
                        ->label(__('admin.b2b.fields.kpp'))
                        ->visible(fn ($get) => $get('country_code') === 'RU')
                        ->length(9),
                    TextInput::make('ogrn')
                        ->label(fn ($get) => $get('country_code') === 'RU' ? __('admin.b2b.fields.ogrn') : 'ОГРН / Рег. номер')
                        ->maxLength(15),
                ]),
            
            Section::make(__('admin.b2b.sections.bank_details'))
                ->columns(2)
                ->schema([
                    TextInput::make('bank_name')
                        ->label(__('admin.b2b.fields.bank_name')),
                    TextInput::make('bank_bic')
                        ->label(__('admin.b2b.fields.bic'))
                        ->length(9),
                    TextInput::make('bank_account')
                        ->label(__('admin.b2b.fields.account'))
                        ->length(20),
                    TextInput::make('bank_correspondent_account')
                        ->label(__('admin.b2b.fields.corr_account'))
                        ->length(20),
                ]),

            Section::make('Финансы и Налоги')
                ->columns(2)
                ->schema([
                    Select::make('tax_system')
                        ->label('Система налогообложения')
                        ->options(\App\Enums\TaxSystemEnum::options())
                        ->searchable()
                        ->live(),

                    TextInput::make('tax_rate')
                        ->label('Ставка налога (%)')
                        ->numeric()
                        ->suffix('%'),

                    Toggle::make('is_vat_payer')
                        ->label('Являюсь плательщиком НДС')
                        ->live(),

                    TextInput::make('vat_rate')
                        ->label('Ставка НДС (%)')
                        ->numeric()
                        ->visible(fn ($get) => (bool) $get('is_vat_payer'))
                        ->suffix('%'),
                ]),

            Section::make(__('admin.b2b.sections.address_contact'))
                ->schema([
                    TextInput::make('director_name')
                        ->label(__('admin.b2b.fields.director')),
                    Textarea::make('legal_address')
                        ->label(__('admin.b2b.fields.legal_address'))
                        ->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('inn')
                    ->label(__('admin.b2b.fields.inn'))
                    ->copyable(),
                TextColumn::make('name')
                    ->label(__('admin.b2b.fields.full_name'))
                    ->searchable(),
                TextColumn::make('shops_count')
                    ->label(__('admin.b2b.fields.shops_count'))
                    ->counts('shops'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ShopsRelationManager::class,
        ];
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
