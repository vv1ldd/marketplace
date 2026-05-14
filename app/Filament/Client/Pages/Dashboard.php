<?php

namespace App\Filament\Client\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Html;
use Illuminate\Support\HtmlString;

class Dashboard extends BaseDashboard
{
    public function content(Schema $schema): Schema
    {
        $user = auth()->user();
        $hasPasskeys = $user->passkeys()->exists();

        return $schema
            ->components([
                Section::make('Добро пожаловать в Sovereign Network')
                    ->description('Ваш личный кабинет для управления заказами и активацией кодов.')
                    ->schema([
                        Html::make(new HtmlString('
                                <div class="bg-sky-50 border-s-4 border-sky-500 p-4 dark:bg-sky-950 mb-4">
                                    <div class="flex items-center">
                                        <div class="ms-3">
                                            <p class="text-sm text-sky-700 dark:text-sky-300">
                                                🔒 <strong>Защитите свой аккаунт:</strong> Привяжите Passkey (TouchID или FaceID), чтобы входить в систему без пароля.
                                            </p>
                                            <p class="mt-2 text-sm">
                                                <a href="/profile" class="font-medium text-sky-700 underline hover:text-sky-600 dark:text-sky-300">
                                                    Перейти в профиль и настроить ключ &rarr;
                                                </a>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            '))
                            ->hidden($hasPasskeys),
                        
                        Html::make(new HtmlString("Приветствуем, <strong>@{$user->name}</strong>! Здесь вы сможете отслеживать свои покупки.")),
                    ])
            ]);
    }
}
