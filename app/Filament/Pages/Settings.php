<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

use Filament\Schemas\Components\Section;
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
        $keys = array_keys($this->data);

        $groups = [];

        $labels = [];

        // Keys to ignore because they moved to Shops
        $migratedKeys = [
            'MEANLY_TOKEN', 
            'PS_TAX', 
            'PS_TAX_FOR_SITES',
            'YM_BUSINESS_ID',
            'YM_CAMPAIGN_ID',
            'YM_API_KEY',
            'TRUSTED_HOSTS',
            'TG_TOKEN',
            'TG_CHAT_ID',
            'SMTP_HOST',
            'SMTP_PORT',
            'SMTP_USER',
            'SMTP_PASSWORD',
            'SMTP_ENCRYPTION',
            'SMTP_FROM_ADDRESS',
            'SMTP_FROM_NAME',
            'SMTP_SUBJECT'
        ];
        $keys = array_diff($keys, $migratedKeys);

        $sections = [];

        // Build defined groups
        foreach ($groups as $id => $group) {
            $groupComponents = [];
            foreach ($group['keys'] as $key) {
                if (!array_key_exists($key, $this->data)) continue;

                $groupComponents[] = $this->createSettingField($key, $labels[$key] ?? $key);
                // Remove from local keys list to track what's left
                $keys = array_diff($keys, [$key]);
            }

            if (!empty($groupComponents)) {
                $sections[] = Section::make($group['title'])
                    ->description('Глобальные настройки для всей системы')
                    ->icon($group['icon'])
                    ->aside()
                    ->schema($groupComponents);
            }
        }

        // Build "Other" group for remaining keys
        if (!empty($keys)) {
            $otherComponents = [];
            foreach ($keys as $key) {
                $otherComponents[] = $this->createSettingField($key, $key);
            }
            $sections[] = Section::make('Прочие настройки')
                ->aside()
                ->schema($otherComponents);
        }

        $sections[] = Section::make('Интеграции')
            ->aside()
            ->schema([
                \Filament\Schemas\Components\View::make('filament.settings.api-apps-link'),
            ]);

        return $schema->components($sections)->statePath('data');
    }

    private function createSettingField(string $key, string $label): TextInput
    {
        return TextInput::make($key)
            ->label($label)
            ->password(fn() => str_contains($key, 'TOKEN') || str_contains($key, 'KEY'))
            ->revealable(fn() => str_contains($key, 'TOKEN') || str_contains($key, 'KEY'))
            ->autocomplete(false)
            ->required();
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
