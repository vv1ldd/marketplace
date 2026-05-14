<?php

namespace App\Filament\Resources\Brands\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BrandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Section::make('Основная информация')
                    ->schema([
                        TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, \Filament\Forms\Set $set) => $operation === 'create' ? $set('slug', \Illuminate\Support\Str::slug($state)) : null),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        \Filament\Forms\Components\Select::make('catalog_group_id')
                            ->label('Группа в каталоге')
                            ->relationship('catalogGroup', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')->required(),
                                TextInput::make('icon')->placeholder('🎮'),
                            ]),
                        \Filament\Forms\Components\Placeholder::make('logo_preview')
                            ->label('Текущий логотип (Авто)')
                            ->content(fn($get) => new \Illuminate\Support\HtmlString('<img src="' . \App\Services\BrandLogoService::getLogoUrl($get('name')) . '" style="height: 60px; width: 60px; border-radius: 8px; background: #f3f4f6; padding: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.1);" />')),
                        \Filament\Forms\Components\FileUpload::make('logo')
                            ->label('Загрузить свой логотип')
                            ->image()
                            ->directory('brands'),
                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                        \Filament\Forms\Components\Textarea::make('description')
                            ->label('Описание бренда')
                            ->columnSpanFull(),
                    ])->columns(2),

                \Filament\Forms\Components\Section::make('Визуальная айдентика (Identity)')
                    ->description('Эти настройки будут использоваться для автоматической генерации баннеров и превью.')
                    ->schema([
                        \Filament\Forms\Components\ColorPicker::make('primary_color')
                            ->label('Основной цвет')
                            ->default('#000000'),
                        \Filament\Forms\Components\ColorPicker::make('secondary_color')
                            ->label('Дополнительный цвет')
                            ->default('#FFFFFF'),
                        \Filament\Forms\Components\ColorPicker::make('text_color')
                            ->label('Цвет текста')
                            ->default('#FFFFFF'),
                        \Filament\Forms\Components\FileUpload::make('cover_path')
                            ->label('Обложка/Паттерн')
                            ->image()
                            ->directory('brands/covers'),
                    ])->columns(2),

                \Filament\Forms\Components\Section::make('Интеграция с Яндекс.Маркетом')
                    ->description('Укажите названия бренда, которые приходят из Яндекса, для правильного сопоставления.')
                    ->schema([
                        \Filament\Forms\Components\TagsInput::make('ym_vendor_names')
                            ->label('Псевдонимы в Яндекс.Маркете')
                            ->placeholder('Добавьте название и нажмите Enter...')
                            ->suggestions([
                                'Sony', 'Microsoft', 'Nintendo', 'Steam', 'Roblox'
                            ])
                            ->columnSpanFull(),
                    ])
            ]);
    }
}
