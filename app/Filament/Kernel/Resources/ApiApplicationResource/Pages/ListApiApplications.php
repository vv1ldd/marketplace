<?php

namespace App\Filament\Kernel\Resources\ApiApplicationResource\Pages;

use App\Filament\Kernel\Resources\ApiApplicationResource;
use App\Models\Settings as SettingsModel;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListApiApplications extends ListRecords
{
    protected static string $resource = ApiApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('platformApiToken')
                ->label(__('admin.platform.configure_token'))
                ->icon('heroicon-o-shield-check')
                ->visible(fn (): bool => auth()->user()->can('page_Settings'))
                ->form([
                    TextInput::make('PLATFORM_API_TOKEN')
                        ->label(__('admin.settings.fields.platform_token'))
                        ->password()
                        ->revealable()
                        ->autocomplete(false)
                        ->required(),
                ])
                ->fillForm(fn (): array => [
                    'PLATFORM_API_TOKEN' => SettingsModel::get('PLATFORM_API_TOKEN', ''),
                ])
                ->action(function (array $data): void {
                    SettingsModel::set('PLATFORM_API_TOKEN', $data['PLATFORM_API_TOKEN'] ?? '');
                    Notification::make()
                        ->title(__('admin.settings.notifications.saved'))
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
