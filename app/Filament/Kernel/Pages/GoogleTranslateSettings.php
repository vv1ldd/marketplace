<?php

namespace App\Filament\Kernel\Pages;

use App\Models\Settings as SettingsModel;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GoogleTranslateSettings extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected string $view = 'filament.pages.google-translate-settings';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('admin.google_translate.navigation_label');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('admin.google_translate.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Администрирование';
    }

    protected static ?int $navigationSort = 103;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-language';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user->can('page_Settings') || $user->can('page_GoogleTranslateSettings');
    }

    public function mount(): void
    {
        $this->data = [
            'GOOGLE_TRANSLATE_API_KEY' => SettingsModel::get('GOOGLE_TRANSLATE_API_KEY', ''),
        ];

        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.google_translate.section_title'))
                    ->description(__('admin.google_translate.section_description'))
                    ->icon('heroicon-o-language')
                    ->aside()
                    ->schema([
                        TextInput::make('GOOGLE_TRANSLATE_API_KEY')
                            ->label(__('admin.google_translate.fields.api_key'))
                            ->password()
                            ->revealable()
                            ->autocomplete(false)
                            ->required()
                            ->helperText(__('admin.google_translate.helpers.api_key')),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        SettingsModel::set(
            'GOOGLE_TRANSLATE_API_KEY',
            $this->data['GOOGLE_TRANSLATE_API_KEY'] ?? ''
        );

        Notification::make()
            ->title(__('admin.settings.notifications.saved'))
            ->success()
            ->send();
    }
}
