<?php

namespace App\Filament\Resources\B2B;

use App\Filament\Resources\B2B\Pages\EditCanonicalProductIdentity;
use App\Filament\Resources\B2B\Pages\ListCanonicalProductIdentities;
use App\Models\CanonicalProductIdentity;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;

class CanonicalProductIdentityResource extends Resource
{
    protected static ?string $model = CanonicalProductIdentity::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-fingerprint';

    protected static ?string $navigationLabel = 'Канонические товары';

    public static function getLabel(): ?string
    {
        return 'Канонический товар';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Канонические товары';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Магазины и B2B';
    }

    protected static ?int $navigationSort = 12;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                Section::make('Исходные (вычисленные) данные')
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('fingerprint')
                            ->label('Fingerprint')
                            ->disabled(),
                        TextInput::make('identity_slug')
                            ->label('Slug')
                            ->disabled(),
                        TextInput::make('brand')
                            ->label('Бренд')
                            ->disabled(),
                        TextInput::make('product_family')
                            ->label('Семейство продукта')
                            ->disabled(),
                        TextInput::make('canonical_category')
                            ->label('Каноническая категория')
                            ->disabled(),
                        TextInput::make('face_value')
                            ->label('Номинал')
                            ->disabled(),
                        TextInput::make('face_value_currency')
                            ->label('Валюта номинала')
                            ->disabled(),
                        TextInput::make('region')
                            ->label('Регион')
                            ->disabled(),
                        TextInput::make('platform')
                            ->label('Платформа')
                            ->disabled(),
                        TextInput::make('confidence')
                            ->label('Уверенность')
                            ->disabled(),
                    ]),

                Section::make('Переопределения куратора')
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('override_brand')
                            ->label('Бренд (переопределение)')
                            ->placeholder('PlayStation, Steam...'),
                        TextInput::make('override_product_family')
                            ->label('Семейство продукта (переопределение)'),
                        TextInput::make('override_canonical_category')
                            ->label('Каноническая категория (переопределение)'),
                        TextInput::make('override_face_value')
                            ->label('Номинал (переопределение)')
                            ->numeric(),
                        TextInput::make('override_face_value_currency')
                            ->label('Валюта номинала (переопределение)'),
                        TextInput::make('override_region')
                            ->label('Регион (переопределение)'),
                        TextInput::make('override_platform')
                            ->label('Платформа (переопределение)'),
                        Select::make('override_confidence')
                            ->label('Уверенность (переопределение)')
                            ->options([
                                'low' => 'Low',
                                'medium' => 'Medium',
                                'high' => 'High',
                            ]),
                        Select::make('override_review_status')
                            ->label('Статус ревью')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'ignored' => 'Ignored',
                            ])
                            ->required(),
                        Textarea::make('override_review_notes')
                            ->label('Заметки ревью')
                            ->rows(3),
                    ]),
            ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fingerprint')
                    ->label('Fingerprint')
                    ->fontFamily('mono')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('identity_slug')
                    ->label('Slug / Название')
                    ->description(fn (CanonicalProductIdentity $record) => $record->brand . ' (' . $record->product_family . ')')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('canonical_category')
                    ->label('Категория')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('face_value')
                    ->label('Номинал')
                    ->formatStateUsing(fn ($state, $record) => $state ? $state . ' ' . $record->face_value_currency : '—')
                    ->sortable(),

                TextColumn::make('confidence')
                    ->label('Уверенность')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'high' => 'success',
                        'medium' => 'warning',
                        'low' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('override.review_status')
                    ->label('Статус ревью')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        'ignored' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCanonicalProductIdentities::route('/'),
            'edit' => EditCanonicalProductIdentity::route('/{record}/edit'),
        ];
    }
}
