<?php

namespace App\Filament\Resources\ShopResource\Schemas;

use App\Models\Brand;
use App\Models\Provider;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ShopForm
{
    /**
     * Normalize messy brand names from providers into unified ones.
     */
    public static function normalizeBrandName(string $name): string
    {
        $name = strtolower($name);

        $mappings = [
            'Amazon' => ['amazon', 'amzn'],
            'Sony PlayStation' => ['playstation', 'psn', 'sony', 'ps5', 'ps4', 'dualshock'],
            'Xbox' => ['xbox', 'microsoft xbox', 'game pass'],
            'Nintendo' => ['nintendo', 'eshop', 'switch'],
            'Apple' => ['itunes', 'apple', 'aple', 'app store', 'appstore', 'icloud'],
            'Google Play' => ['google play', 'googleplay', 'gplay', 'android store'],
            'Steam' => ['steam', 'valve'],
            'Roblox' => ['roblox'],
            'Razer' => ['razer'],
            'Netflix' => ['netflix'],
            'Spotify' => ['spotify'],
            'Twitch' => ['twitch'],
            'Discord' => ['discord'],
            'PUBG' => ['pubg'],
            'Free Fire' => ['free fire', 'freefire'],
            'Mobile Legends' => ['mobile legends', 'mlbb'],
            'Valorant' => ['valorant'],
            'League of Legends' => ['league of legends', 'lol'],
            'Fortnite' => ['fortnite', 'v-bucks'],
            'Minecraft' => ['minecraft'],
            'Blizzard' => ['blizzard', 'battle.net', 'battlenet'],
            'EA' => ['ea play', 'origin', 'electronic arts'],
            'Ubisoft' => ['ubisoft', 'uplay'],
            'Riot Games' => ['riot'],
            'Sephora' => ['sephora'],
            'Airbnb' => ['airbnb'],
            'Uber' => ['uber'],
            'Starbucks' => ['starbucks'],
            'Nike' => ['nike'],
            'Adidas' => ['adidas'],
            'H&M' => ['h&m', 'h & m'],
            'Zara' => ['zara'],
            'IKEA' => ['ikea'],
            'Walmart' => ['walmart'],
            'eBay' => ['ebay'],
        ];

        foreach ($mappings as $unified => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($name, $kw)) {
                    return $unified;
                }
            }
        }

        return Str::title($name);
    }

    public static function getGroupsMap(string $region): array
    {
        // 🛡️ Get Blacklist
        $blacklist = [];
        $providers = Provider::where('is_active', true)->get();
        foreach ($providers as $provider) {
            $rules = $provider->compliance_rules ?? [];
            foreach ($rules as $rule) {
                if (($rule['region'] ?? '') === $region) {
                    $blacklist = array_merge($blacklist, $rule['blacklist'] ?? []);
                }
            }
        }
        $blacklist = array_unique($blacklist);

        $filterBrands = function ($brands) use ($blacklist) {
            if (empty($blacklist)) {
                return $brands;
            }

            return array_filter($brands, function ($name) use ($blacklist) {
                foreach ($blacklist as $word) {
                    if (stripos($name, $word) !== false) {
                        return false;
                    }
                }

                return true;
            });
        };

        // 📦 Get Groups from DB
        $map = [];
        $mappings = [
            'Gaming & Streaming' => ['PlayStation', 'Xbox', 'Nintendo', 'Steam', 'Razer', 'Roblox', 'Minecraft', 'PUBG', 'Free Fire', 'Mobile Legends', 'BIGO', 'Netflix', 'Hulu', 'Disney+', 'Spotify', 'Twitch', 'Valorant', 'League of Legends', 'Blizzard', 'Origin', 'EA', 'Uplay', 'Fortnite', 'Riot'],
            'Software' => ['Microsoft', 'Windows', 'Office', 'Adobe', 'Antivirus', 'Kaspersky', 'Norton', 'McAfee', 'VPN', 'Zoom'],
            'Food & Drink' => ['Uber Eats', 'Deliveroo', 'Just Eat', 'Hello Fresh', 'Starbucks', 'Costa', 'Nandos', 'Pizza Hut', 'Burger King', 'KFC', 'McDonald\'s', 'Food', 'Drink'],
            'Fashion & Accessories' => ['Sephora', 'Nike', 'Adidas', 'Zara', 'H&M', 'Foot Locker', 'Saks', 'Fashion', 'Beauty'],
            'Retail & Services' => ['Amazon', 'eBay', 'Walmart', 'Target', 'Best Buy', 'Apple', 'Google', 'iTunes', 'Airbnb', 'Hotels.com', 'Expedia', 'Skype', 'IKEA', 'Homecentre', 'Lifestyle', 'Max', 'Splash', 'Babyshop', 'Retail'],
        ];

        $brands = Brand::orderBy('name')->get(['id', 'name']);
        foreach ($brands as $brand) {
            $foundGroup = 'Others';
            foreach ($mappings as $groupName => $keywords) {
                foreach ($keywords as $kw) {
                    if (stripos($brand->name, $kw) !== false) {
                        $foundGroup = $groupName;
                        break 2;
                    }
                }
            }
            $map[$foundGroup][$brand->id] = $brand->name;
        }

        // Apply filtering and sort
        $finalMap = [];
        foreach ($map as $group => $options) {
            $filtered = $filterBrands($options);
            if (! empty($filtered)) {
                asort($filtered);
                $finalMap[$group] = $filtered;
            }
        }

        uksort($finalMap, function ($a, $b) use ($finalMap) {
            if ($a === 'Others') {
                return 1;
            }
            if ($b === 'Others') {
                return -1;
            }

            return count($finalMap[$b]) <=> count($finalMap[$a]);
        });

        return $finalMap;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Основное')
                ->schema([
                    Grid::make(3)->schema([
                        Select::make('shop_region')
                            ->label('Регион / Юрисдикция')
                            ->options([
                                'RU' => 'Russia (РФ)',
                                'UAE' => 'UAE (ОАЭ)',
                                'KSA' => 'KSA (Саудовская Аравия)',
                                'GLOBAL' => 'Other / Global',
                            ])
                            ->default('RU')
                            ->required()
                            ->live()
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),

                        TextInput::make('name')
                            ->label('Название магазина')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, $state) {
                                $slug = Str::slug($state, '-');
                                $set('voucher_prefix', Str::upper($slug));
                            })
                            ->columnSpan(1),

                        Toggle::make('is_active')
                            ->label('Магазин активен')
                            ->default(true)
                            ->columnSpan(1),

                        Toggle::make('auto_purchase_enabled')
                            ->label('Авто-выдача ключей')
                            ->helperText('Если включено, система сама закупает и выдает ключ после активации ваучера')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),

                    Grid::make(2)->schema([
                        Select::make('legal_entity_id')
                            ->label('Юр. лицо (Владелец)')
                            ->relationship('legalEntity', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm(\App\Filament\Resources\B2B\Schemas\LegalEntitySchema::get())
                            ->columnSpan(1),

                        TextInput::make('domain')
                            ->label('Домен')
                            ->placeholder('example.com')
                            ->prefix('https://')
                            ->columnSpan(1),
                    ]),

                    Grid::make(2)->schema([
                        Toggle::make('use_custom_redeem_url')
                            ->label('Свой URL активации (Redeem)')
                            ->helperText('Выключено — используется системный redeem из настроек + ?shop=префикс.')
                            ->default(false)
                            ->live(),
                        Toggle::make('redeem_requires_extended_profile')
                            ->label('Redeem: ФИО и телефон (KYC)')
                            ->helperText('Вкл. — имя, фамилия, телефон RU + email + код. Выкл. — global: только email и код из письма. PlayStation всегда с расширенными полями.')
                            ->default(false),
                        TextInput::make('redeem_url')
                            ->label('URL страницы активации')
                            ->url()
                            ->nullable()
                            ->maxLength(2048)
                            ->placeholder('https://partner.example.com/redeem')
                            ->visible(fn ($get) => (bool) $get('use_custom_redeem_url'))
                            ->required(fn ($get) => (bool) $get('use_custom_redeem_url'))
                            ->columnSpanFull(),
                    ]),

                    Hidden::make('voucher_prefix'),
                ]),

            Section::make('Служба поддержки')
                ->description('Контакты, которые будут отображаться на информационных карточках товаров.')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('support_email')
                            ->label('Email поддержки')
                            ->email()
                            ->placeholder('help@example.com'),

                        TextInput::make('support_telegram')
                            ->label('Telegram поддержки')
                            ->placeholder('@support_bot')
                            ->prefix('https://t.me/'),
                    ]),
                ]),

            Section::make('Комплаенс и ограничения')
                ->description('Информация об ограничениях провайдеров для выбранного региона.')
                ->collapsible()
                ->collapsed()
                ->icon('heroicon-o-shield-check')
                ->schema([
                    Placeholder::make('compliance_summary')
                        ->label('Статус региона')
                        ->content(function ($get) {
                            $region = $get('shop_region') ?? 'RU';

                            $blacklist = [];
                            $providers = Provider::where('is_active', true)->get();
                            foreach ($providers as $provider) {
                                $rules = $provider->compliance_rules ?? [];
                                foreach ($rules as $rule) {
                                    if (($rule['region'] ?? '') === $region) {
                                        $blacklist = array_merge($blacklist, $rule['blacklist'] ?? []);
                                    }
                                }
                            }
                            $blacklist = array_unique($blacklist);
                            sort($blacklist);

                            if (empty($blacklist)) {
                                return new HtmlString('<span class="text-success-600 font-medium">✅ Ограничений для этого региона не обнаружено. Все бренды доступны.</span>');
                            }

                            $list = implode(', ', array_map(fn ($b) => "<strong>{$b}</strong>", $blacklist));

                            return new HtmlString(
                                '<div class="p-3 bg-danger-50 border border-danger-100 rounded-lg text-danger-700">'.
                                '<p class="mb-2 font-bold">⚠️ Внимание! Следующие бренды заблокированы провайдерами для региона '.$region.':</p>'.
                                '<div class="text-sm opacity-90">'.$list.'</div>'.
                                '</div>'
                            );
                        }),
                ]),




            Section::make('Интеграция Yandex Market')
                ->description('Настройка интеграции с Яндекс Маркетом (DBS).')
                ->collapsible()
                ->collapsed()
                ->icon('heroicon-o-shopping-bag')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('business_id')
                            ->label('Business ID')
                            ->placeholder('1234567')
                            ->numeric()
                            ->helperText('Кабинет бизнеса в Яндексе'),

                        TextInput::make('campaign_id')
                            ->label('Campaign ID')
                            ->placeholder('149014578')
                            ->numeric()
                            ->helperText('ID кампании (магазина) в Яндексе'),
                    ]),

                    TextInput::make('api_key')
                        ->label('OAuth Токен (Api-Key)')
                        ->password() // Secure mask
                        ->revealable() // Add visual eye icon
                        ->placeholder('ACMA:...')
                        ->helperText('Получите токен в кабинете разработчика Яндекса')
                        ->columnSpanFull(),
                ]),

        ]);
    }
}
