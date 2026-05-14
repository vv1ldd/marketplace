<?php

namespace App\Filament\Kernel\Resources\ProviderResource\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.products.sections.main_info'))
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('name')
                            ->label(__('admin.products.name'))
                            ->required(),
                        Select::make('type')
                            ->label(__('admin.products.fields.driver'))
                            ->options([
                                'wildflow' => 'Wildflow',
                                'playstation' => 'PlayStation Store',
                            ])
                            ->required()
                            ->live(),
                        Toggle::make('is_active')
                            ->label(__('admin.products.fields.is_active'))
                            ->default(true)
                            ->inline(false),
                    ]),
                ]),

            Section::make('Комплаенс (Черные списки)')
                ->description('Настройте запрещенные слова для разных регионов. Товары, содержащие эти слова, будут скрыты для магазинов соответствующих регионов.')
                ->schema([
                    Repeater::make('compliance_rules')
                        ->label('')
                        ->schema([
                            Select::make('region')
                                ->label('Регион')
                                ->options([
                                    'RU' => 'Russia (РФ)',
                                    'UAE' => 'UAE (ОАЭ)',
                                    'KSA' => 'KSA (Саудовская Аравия)',
                                    'GLOBAL' => 'Other / Global',
                                ])
                                ->required(),
                            TagsInput::make('blacklist')
                                ->label('Черный список слов')
                                ->placeholder('Введите слово и нажмите Enter')
                                ->required(),
                        ])
                        ->columns(2)
                        ->itemLabel(fn (array $state): ?string => $state['region'] ?? null)
                        ->collapsible(),
                ]),

            Section::make(__('admin.products.sections.credentials'))
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('credentials.base_url')
                            ->label('Base URL (API Endpoint)')
                            ->placeholder('https://api.wildflow.com')
                            ->visible(fn (Get $get) => $get('type') === 'wildflow'),
                        TextInput::make('credentials.api_key')
                            ->label('API Key')
                            ->password()
                            ->revealable()
                            ->visible(fn (Get $get) => $get('type') === 'wildflow'),
                    ]),
                ]),
        ]);
    }
}
