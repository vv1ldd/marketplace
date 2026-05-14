<?php

namespace App\Filament\Partner\Pages;

use Filament\Pages\Page;
use Filament\Facades\Filament;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class Finance extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected string $view = 'filament.partner.pages.finance';

    protected static string|\UnitEnum|null $navigationGroup = 'Настройки';

    protected static int|null $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return 'Финансы';
    }

    public static function getLabel(): string
    {
        return 'Управление балансом';
    }

    public function getTitle(): string|HtmlString
    {
        return 'Финансы и Биллинг';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('topup')
                ->label('Пополнить баланс')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->modalHeading('Пополнение баланса')
                ->modalDescription('В данный момент шлюз автоматического эквайринга находится в разработке. Пожалуйста, обратитесь к вашему менеджеру для пополнения депозита.')
                ->modalSubmitActionLabel('Понятно')
                ->modalCancelAction(false)
                ->action(function () {
                    // This action just closes the modal, or you can send a telegram alert to the manager
                    Notification::make()
                        ->title('Заявка принята')
                        ->body('Пожалуйста, свяжитесь с поддержкой для получения реквизитов.')
                        ->info()
                        ->send();
                }),
        ];
    }
}
