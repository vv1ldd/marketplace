<?php

namespace App\Filament\Partner\Pages;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;

class EditProfile extends BaseEditProfile
{
    protected static bool $isDiscovered = true;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'profile';

    protected string $view = 'filament.pages.auth.edit-profile-custom';

    public static function isSimple(): bool
    {
        return false;
    }

    public static function getRouteName(?Panel $panel = null): string
    {
        $panel ??= \Filament\Facades\Filament::getCurrentOrDefaultPanel();

        return $panel->generateRouteName(static::getRelativeRouteName($panel));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Личная информация')
                    ->description('Обновите свои личные данные и контактную информацию.')
                    ->schema([
                        TextInput::make('first_name')
                            ->label('Имя')
                            ->required()
                            ->maxLength(255)
                            ->autofocus(),
                        TextInput::make('last_name')
                            ->label('Фамилия')
                            ->maxLength(255),
                        TextInput::make('middle_name')
                            ->label('Отчество')
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Телефон')
                            ->tel()
                            ->maxLength(255),
                        $this->getEmailFormComponent(),
                    ])->columns(2),
            ]);
    }
}
