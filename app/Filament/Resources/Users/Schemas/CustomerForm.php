<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        $is_update = (bool)$schema->getRecord();

        return $schema
            ->components([
                Section::make()->schema([
                    Grid::make(4)->schema([
                        TextInput::make('first_name')
                            ->required()
                            ->label(__('admin.customers.first_name')),
                        TextInput::make('last_name')
                            ->required()
                            ->label(__('admin.customers.last_name')),
                        TextInput::make('email')
                            ->email()
                            ->unique(ignoreRecord: $is_update)
                            ->label(__('admin.customers.email')),
                        TextInput::make('phone')
                            ->required()
                            ->unique(ignoreRecord: $is_update)
                            ->mask('+79999999999')
                            ->label(__('admin.customers.phone')),
                    ])->columnSpanFull(),

                    Grid::make(2)->schema([
                        Select::make('shop_id')
                            ->label(__('admin.users.shop'))
                            ->relationship('shop', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        
                        TextInput::make('ym_user_id')
                            ->label('YM User ID')
                            ->disabled($is_update),
                    ]),

                ])->columnSpanFull()
            ]);
    }
}
