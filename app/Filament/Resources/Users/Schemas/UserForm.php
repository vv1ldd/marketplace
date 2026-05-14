<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        $is_update = (bool) $schema->getRecord();
        $is_super_admin = auth()->user()->hasRole('super_admin');

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
                            ->required()
                            ->email()
                            ->unique(ignoreRecord: $is_update)
                            ->label(__('admin.customers.email')),
                        TextInput::make('phone')
                            ->required()
                            ->unique(ignoreRecord: $is_update)
                            ->mask('+79999999999')
                            ->label(__('admin.customers.phone')),
                    ])->columnSpanFull(),

                    Grid::make()->schema([
                        TextInput::make('password')
                            ->password()
                            ->label(__('admin.users.password'))
                            ->required()
                            ->confirmed()
                            ->revealable()
                            ->hidden($is_update),
                        TextInput::make('password_confirmation')
                            ->password()
                            ->label(__('admin.users.password_confirmation'))
                            ->revealable()
                            ->required()
                            ->hidden($is_update),

                        Select::make('roles')
                            ->label(__('admin.users.roles'))
                            ->relationship('roles', 'name', fn ($query) => $is_super_admin ? $query : $query->where('name', '<>', 'super_admin'))
                            ->multiple()
                            ->maxItems(1)
                            ->preload()
                            ->searchable()
                            ->default($schema->getModel() === \App\Models\Seller::class ? ['b2b_partner'] : null)
                            ->hidden($schema->getModel() === \App\Models\Seller::class),


                    ]),
                ])->columnSpanFull(),
            ]);
    }
}
