<?php

namespace App\Filament\Partner\Pages\Auth;

use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use App\Services\DaDataService;

class Register extends BaseRegister
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('country_code')
                    ->label('Выберите юрисдикцию (Страну)')
                    ->options([
                        'RU' => '🇷🇺 Россия (RU)',
                        'KZ' => '🇰🇿 Казахстан (KZ)',
                        'BY' => '🇧🇾 Беларусь (BY)',
                        'UZ' => '🇺🇿 Узбекистан (UZ)',
                        'GE' => '🇬🇪 Грузия (GE)',
                        'AM' => '🇦🇲 Армения (AM)',
                        'AE' => '🇦🇪 ОАЭ (AE)',
                        'OTHER' => '🌍 Другая страна',
                    ])
                    ->default(fn() => match(app()->getLocale()) {
                        'kk' => 'KZ',
                        'ru' => 'RU',
                        'uz' => 'UZ',
                        'ka' => 'GE',
                        'hy' => 'AM',
                        default => 'RU'
                    })
                    ->live()
                    ->required(),

                TextInput::make('inn')
                    ->label(fn ($get) => $get('country_code') === 'RU' ? 'Ваш ИНН (для юрлица)' : 'Регистрационный номер / ИНН')
                    ->helperText('Введите ИНН для автоматической проверки организации')
                    ->required()
                    ->length(fn ($state, $get) => $get('country_code') === 'RU' ? (strlen($state) === 12 ? 12 : 10) : null)
                    ->suffixAction(
                        Action::make('lookupByInn')
                            ->icon('heroicon-m-magnifying-glass')
                            ->visible(fn ($get) => $get('country_code') === 'RU')
                            ->action(function ($state, Set $set) {
                                if (empty($state)) {
                                    Notification::make()->title('Сначала введите ИНН')->warning()->send();
                                    return;
                                }
                                
                                $service = new DaDataService;
                                $data = $service->findByInn($state);

                                if (! $data) {
                                    Notification::make()->title('Организация не найдена в DaData')->danger()->send();
                                    return;
                                }

                                $name = $data['name']['short_with_opf'] ?? $data['name']['full_with_opf'] ?? 'Найдена';
                                Notification::make()
                                    ->title("Успешно: {$name}")
                                    ->success()
                                    ->body('Организация проверена. Ваши реквизиты будут подтянуты после завершения регистрации!')
                                    ->send();
                            })
                    ),

                $this->getNameFormComponent()->label('Ваше имя / Псевдоним'),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ])
            ->statePath('data');
    }

    protected function handleRegistration(array $data): Model
    {
        // Save inbound entity context to session for seamless onboarding step
        if (!empty($data['inn'])) {
            session(['pending_inn' => $data['inn']]);
        }
        if (!empty($data['country_code'])) {
            session(['pending_country' => $data['country_code']]);
        }

        // Creates seller using standard flow
        return parent::handleRegistration($data);
    }
}
