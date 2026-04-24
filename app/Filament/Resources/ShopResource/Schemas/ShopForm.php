<?php

namespace App\Filament\Resources\ShopResource\Schemas;

use Filament\Schemas\Components\TextInput;
use Filament\Schemas\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShopForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Основная информация')->schema([
                TextInput::make('name')
                    ->label('Название магазина')
                    ->required(),
                Toggle::make('is_active')
                    ->label('Активен')
                    ->default(true),
            ]),
            
            Section::make('Интеграция Яндекс.Маркет')->schema([
                TextInput::make('business_id')
                    ->label('Business ID')
                    ->required(),
                TextInput::make('campaign_id')
                    ->label('Campaign ID')
                    ->required(),
                TextInput::make('api_key')
                    ->label('API Key')
                    ->password()
                    ->revealable()
                    ->required(),
                TextInput::make('notification_token')
                    ->label('Токен уведомлений')
                    ->password()
                    ->revealable()
                    ->required(),
            ]),

            Section::make('Автоматизация')->schema([
                Toggle::make('auto_purchase_enabled')
                    ->label('Автозакупка включена')
                    ->default(true),
            ]),
        ]);
    }
}
