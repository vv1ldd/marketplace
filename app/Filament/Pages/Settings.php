<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use UnitEnum;
use App\Models\Settings as SettingsModel;

class Settings extends Page implements HasForms
{
    use HasPageShield, InteractsWithForms;

    protected string $view = 'filament.pages.settings';

    protected static string|UnitEnum|null $navigationGroup = 'Управление';

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-cog';

    protected static ?string $navigationLabel = 'Настройки';

    protected static ?string $slug = 'settings';

    protected static ?string $title = 'Настройки';

    protected static ?int $navigationSort = 99;

    public static function canAccess(): bool
    {
        return auth()->user()->can('page_Settings');
    }

    public ?array $data = [];

    public function mount(): void
    {
        $all = SettingsModel::all();

        $this->data = $all->pluck('value', 'key')->toArray();

        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        $components = [];

        foreach ($this->data as $key => $value) {
            if (str_starts_with($key, 'YM_') || in_array($key, ['PS_TAX', 'PS_TAX_FOR_SITES'])) {
                continue;
            }

            $components[] = TextInput::make($key)
                ->password()
                ->revealable()
                ->autocomplete(false)
                ->label($key)
                ->required();
        }

        return $schema->columns()->
        components($components)->statePath('data');
    }

    public function save(): void
    {
        foreach ($this->data as $key => $value) {
            SettingsModel::set($key, $value);
        }

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }
}
