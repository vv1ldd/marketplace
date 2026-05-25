<?php

namespace App\Filament\Resources\B2B\Schemas;

use App\Services\DaDataService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;

class LegalEntitySchema
{
    public static function get(): array
    {
        return [
            Section::make(__('admin.b2b.sections.main_info'))
                ->columns(2)
                ->schema([
                    Select::make('country_code')
                        ->label('Юрисдикция (Страна)')
                        ->options([
                            'RU' => '🇷🇺 Россия (RU)',
                            'KZ' => '🇰🇿 Казахстан (KZ)',
                            'BY' => '🇧🇾 Беларусь (BY)',
                            'UZ' => '🇺🇿 Узбекистан (UZ)',
                            'OTHER' => '🌍 Другая страна',
                        ])
                        ->default('RU')
                        ->searchable()
                        ->live()
                        ->required()
                        ->columnSpanFull(),

                    TextInput::make('inn')
                        ->label(fn ($get) => $get('country_code') === 'RU' ? __('admin.b2b.fields.inn') : 'Рег. номер / ИНН')
                        ->required()
                        ->length(fn ($state, $get) => $get('country_code') === 'RU' ? (strlen($state) === 12 ? 12 : 10) : null)
                        ->maxLength(fn ($get) => $get('country_code') === 'RU' ? 12 : 50)
                        ->suffixAction(
                            Action::make('lookupByInn')
                                ->icon('heroicon-m-magnifying-glass')
                                ->visible(fn ($get) => $get('country_code') === 'RU')
                                ->action(function ($state, Set $set) {
                                    if (empty($state)) {
                                        Notification::make()->title('Введите ИНН')->warning()->send();

                                        return;
                                    }

                                    $service = new DaDataService;
                                    $data = $service->findByInn($state);

                                    if (! $data) {
                                        Notification::make()->title('Организация не найдена')->danger()->send();

                                        return;
                                    }

                                    $set('name', $data['name']['full_with_opf'] ?? '');
                                    $set('short_name', $data['name']['short_with_opf'] ?? '');
                                    $set('kpp', $data['kpp'] ?? '');
                                    $set('ogrn', $data['ogrn'] ?? '');
                                    $set('legal_address', $data['address']['value'] ?? '');
                                    $set('director_name', $data['management']['name'] ?? '');

                                    Notification::make()->title('Данные заполнены из DaData')->success()->send();
                                })
                        ),
                    TextInput::make('name')
                        ->label(__('admin.b2b.fields.full_name'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('short_name')
                        ->label(__('admin.b2b.fields.short_name'))
                        ->maxLength(255),
                    TextInput::make('kpp')
                        ->label(__('admin.b2b.fields.kpp'))
                        ->visible(fn ($get) => $get('country_code') === 'RU')
                        ->length(9),
                    TextInput::make('ogrn')
                        ->label(fn ($get) => $get('country_code') === 'RU' ? __('admin.b2b.fields.ogrn') : 'ОГРН / Рег. номер')
                        ->maxLength(15),
                    Select::make('seller_id')
                        ->label(__('admin.b2b.fields.owner'))
                        ->relationship('seller', 'email')
                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->getFullName()} ({$record->email})")
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm(fn (\Filament\Schemas\Schema $schema) => \App\Filament\Resources\Users\Schemas\UserForm::configure($schema)->getComponents()),
                ]),

            Section::make(__('admin.b2b.sections.address_contact'))
                ->columns(2)
                ->schema([
                    Textarea::make('legal_address')
                        ->label(__('admin.b2b.fields.legal_address'))
                        ->columnSpanFull(),
                    Textarea::make('postal_address')
                        ->label(__('admin.b2b.fields.postal_address'))
                        ->columnSpanFull(),
                    TextInput::make('phone')
                        ->label(__('admin.b2b.fields.phone'))
                        ->tel(),
                    TextInput::make('email')
                        ->label(__('admin.b2b.fields.email_docs'))
                        ->email(),
                    TextInput::make('director_name')
                        ->label(__('admin.b2b.fields.director'))
                        ->columnSpanFull(),
                ])->collapsed(),

            Section::make(__('admin.b2b.sections.bank_details'))
                ->columns(2)
                ->schema([
                    TextInput::make('bank_name')
                        ->label(__('admin.b2b.fields.bank_name')),
                    TextInput::make('bank_bic')
                        ->label(__('admin.b2b.fields.bic'))
                        ->length(9),
                    TextInput::make('bank_account')
                        ->label(__('admin.b2b.fields.account'))
                        ->length(20),
                    TextInput::make('bank_correspondent_account')
                        ->label(__('admin.b2b.fields.corr_account'))
                        ->length(20),
                ])->collapsed(),

            Section::make('Финансы и Налоги')
                ->icon('heroicon-o-banknotes')
                ->columns(2)
                ->schema([
                    Select::make('tax_system')
                        ->label('Система налогообложения')
                        ->options(\App\Enums\TaxSystemEnum::options())
                        ->searchable()
                        ->live(),

                    TextInput::make('tax_rate')
                        ->label('Ставка основного налога (%)')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->maxValue(100),

                    Toggle::make('is_vat_payer')
                        ->label('Плательщик НДС')
                        ->helperText('Включить при ОСНО или превышении порогов УСН')
                        ->default(false)
                        ->live(),

                    TextInput::make('vat_rate')
                        ->label('Ставка НДС (%)')
                        ->numeric()
                        ->placeholder('Напр. 20')
                        ->visible(fn ($get) => (bool) $get('is_vat_payer'))
                        ->required(fn ($get) => (bool) $get('is_vat_payer'))
                        ->suffix('%'),
                ])->collapsible(),

            Section::make(__('admin.b2b.sections.status'))
                ->schema([
                    Toggle::make('is_active')
                        ->label(__('admin.b2b.fields.is_active'))
                        ->default(true),
                ])->collapsed(),

            Section::make('B2B и Каталог')
                ->schema([
                    Toggle::make('is_global_catalog_enabled')
                        ->label('Доступ к глобальному каталогу')
                        ->helperText('Разрешить партнеру импортировать товары из глобальной базы провайдеров.')
                        ->live()
                        ->default(false),

                    Toggle::make('allow_all_brands')
                        ->label('Разрешить все бренды')
                        ->helperText('Снять все ограничения по брендам для этого партнера.')
                        ->visible(fn ($get) => (bool) $get('is_global_catalog_enabled'))
                        ->live()
                        ->default(false),

                    \Filament\Schemas\Components\Grid::make(2)
                        ->visible(fn ($get) => (bool) $get('is_global_catalog_enabled'))
                        ->schema([
                            Select::make('tariff_type')
                                ->label('Тарифный план (B2B)')
                                ->options([
                                    'privileged' => 'Тариф А (Закупка + 1%) — VIP / Wholesale',
                                    'retail' => 'Тариф Б (Рекомендованная розница) — Standart / Retail',
                                ])
                                ->default('privileged')
                                ->required(),
                        ]),
                ])->collapsible(),

            Section::make('Комплаенс-контроль подписанта (ЭЦП / Доверенность)')
                ->icon('heroicon-o-shield-check')
                ->columns(2)
                ->schema([
                    Placeholder::make('signer_role_display')
                        ->label('Роль подписанта')
                        ->content(fn ($record) => $record && isset($record->agreement_metadata['signer_role'])
                            ? ($record->agreement_metadata['signer_role'] === 'ceo' ? '👔 Руководитель компании (CEO)' : '📜 Представитель по доверенности')
                            : 'Не указана'),

                    Placeholder::make('signer_name_display')
                        ->label('ФИО подписанта')
                        ->content(fn ($record) => $record && isset($record->agreement_metadata['signer_name']) ? $record->agreement_metadata['signer_name'] : ($record->director_name ?? 'Не указано')),

                    Placeholder::make('l1_address_display')
                        ->label('Суверенный L1-адрес (ЭЦП)')
                        ->content(fn ($record) => $record && isset($record->agreement_metadata['l1_address']) ? $record->agreement_metadata['l1_address'] : 'Не сгенерирован'),

                    Placeholder::make('passkey_id_display')
                        ->label('ID Passkey-ключа')
                        ->content(fn ($record) => $record && isset($record->agreement_metadata['passkey_id']) ? $record->agreement_metadata['passkey_id'] : 'Не привязан'),

                    // 🛡️ Блок автоматизации комплаенса и проверки доверенностей
                    Fieldset::make('Верификация полномочий (ФНС / Нотариат)')
                        ->visible(fn ($record) => $record && isset($record->agreement_metadata['signer_role']) && $record->agreement_metadata['signer_role'] === 'representative')
                        ->schema([
                            Toggle::make('agreement_metadata.poa_verified')
                                ->label('Доверенность верифицирована модератором')
                                ->default(false)
                                ->live(),

                            DatePicker::make('agreement_metadata.poa_expires_at')
                                ->label('Срок действия доверенности')
                                ->live(),

                            TextInput::make('agreement_metadata.poa_guid')
                                ->label('GUID МЧД в реестре ФНС / Номер реестра нотариусов')
                                ->placeholder('Напр. 49302b1f-e8b2-4cd8-8685-6b3a3c20021b')
                                ->live(),

                            Actions::make([
                                FormAction::make('openFnsRegistry')
                                    ->label('Проверить МЧД в ФНС')
                                    ->icon('heroicon-m-arrow-top-right-on-square')
                                    ->color('primary')
                                    ->url(fn ($record) => 'https://mchd.nalog.ru/', shouldOpenInNewTab: true),

                                FormAction::make('openNotaryRegistry')
                                    ->label('Проверить у нотариусов')
                                    ->icon('heroicon-m-arrow-top-right-on-square')
                                    ->color('info')
                                    ->url(fn ($record) => 'https://reestr-dover.ru/', shouldOpenInNewTab: true),
                            ])->columnSpanFull(),
                        ])->columnSpanFull(),
                ])->collapsible(),
        ];
    }
}
