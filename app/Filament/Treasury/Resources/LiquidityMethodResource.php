<?php

namespace App\Filament\Treasury\Resources;

use App\Models\LiquidityMethod;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Str;

class LiquidityMethodResource extends Resource
{
    protected static ?string $model = LiquidityMethod::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    public static function getNavigationGroup(): ?string
    {
        return __('sovereign.navigation.groups.liquidity');
    }

    public static function getNavigationLabel(): string
    {
        return __('sovereign.navigation.methods');
    }

    protected static ?int $navigationSort = 2;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make(__('sovereign.navigation.methods'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('sovereign.methods.fields.name'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Select::make('type')
                            ->label(__('sovereign.methods.fields.type'))
                            ->options([
                                'bank' => 'Bank Transfer',
                                'cash' => 'Cash / OTC',
                                'digital' => 'Digital / E-wallet',
                                'bridge' => 'Bridge / Crypto',
                            ])
                            ->required(),
                        TextInput::make('icon')
                            ->placeholder('heroicon-o-star'),
                        Textarea::make('description')
                            ->columnSpanFull(),
                        Toggle::make('is_global')
                            ->label(__('sovereign.methods.fields.is_global'))
                            ->hint(__('sovereign.methods.fields.is_global_hint')),
                        Toggle::make('is_active')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'bank' => 'info',
                        'cash' => 'warning',
                        'digital' => 'success',
                        'bridge' => 'primary',
                        default => 'gray',
                    }),
                IconColumn::make('is_global')
                    ->boolean()
                    ->label('Global'),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                TextColumn::make('created_at')
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
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Treasury\Resources\LiquidityMethodResource\Pages\ListLiquidityMethods::route('/'),
        ];
    }
}
