<?php

namespace App\Filament\Treasury\Resources;

use App\Models\MappingCountry;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Slider;

class MappingCountryResource extends Resource
{
    protected static ?string $model = MappingCountry::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    public static function getNavigationGroup(): ?string
    {
        return __('sovereign.navigation.groups.intelligence');
    }

    public static function getNavigationLabel(): string
    {
        return __('sovereign.navigation.countries');
    }

    protected static ?int $navigationSort = 3;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('General Info')
                    ->schema([
                        TextInput::make('code')->required()->maxLength(5),
                        TextInput::make('name_en')->required(),
                        TextInput::make('name_ru')->required(),
                        Select::make('primary_currency_id')
                            ->relationship('primaryCurrency', 'code')
                            ->searchable()
                            ->preload(),
                    ])->columns(2),

                Section::make('Sovereign Financial Profile')
                    ->description('Local accessibility and regulatory context')
                    ->schema([
                        Grid::make(3)->schema([
                            Slider::make('accessibility_score')
                                ->label('Accessibility (Ease of Entry/Exit)')
                                ->minValue(0)->maxValue(100)->step(5)->default(100),
                            Select::make('regulatory_status')
                                ->options([
                                    'friendly' => '🟢 Friendly',
                                    'grey' => '🟡 Grey Market / High Friction',
                                    'restricted' => '🔴 Restricted / Sanctioned',
                                ])->required(),
                            Toggle::make('has_capital_controls')
                                ->label('Capital Controls Present')
                                ->default(false),
                        ]),
                        RichEditor::make('local_notes')
                            ->label('Local Peculiarities (The "Nuance")')
                            ->placeholder('e.g. Only new USD bills accepted, bank crypto bans, local payment hubs...')
                            ->columnSpanFull(),
                    ])->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('ISO')->sortable()->searchable(),
                TextColumn::make('name_en')
                    ->label('Country')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn ($state, $record) => $state ?: $record->name_ru),
                TextColumn::make('regulatory_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'friendly' => 'success',
                        'grey' => 'warning',
                        'restricted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('accessibility_score')
                    ->label('Ease')
                    ->numeric()
                    ->suffix('%'),
                TextColumn::make('demand_index')
                    ->label('Demand')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('x')
                    ->color(fn (float $state): string => $state > 1.05 ? 'danger' : ($state > 1.02 ? 'warning' : 'success'))
                    ->tooltip('Market Temperature (P2P / Spot Spread)'),
                TextColumn::make('market_sentiment')
                    ->label('Sentiment')
                    ->badge()
                    ->color(fn (string|null $state): string => match ($state) {
                        'HOT 🔥' => 'danger',
                        'Stable 🟢' => 'success',
                        'Cooling ❄️' => 'info',
                        default => 'gray',
                    }),
                IconColumn::make('has_capital_controls')
                    ->label('Controls')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Treasury\Resources\MappingCountryResource\Pages\ListMappingCountries::route('/'),
        ];
    }
}
