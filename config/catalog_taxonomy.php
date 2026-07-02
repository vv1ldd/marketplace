<?php

return [
    'default' => 'gift_cards',

    'categories' => [
        'gift_cards' => [
            'label_ru' => 'Подарочные карты',
            'label_en' => 'Gift cards',
            'description_ru' => 'Цифровые подарочные карты и сертификаты для онлайн-магазинов, брендов и сервисов с электронной выдачей после checkout.',
            'seo_indexable' => true,
            'schema_org' => 'Product',
            'google_product_category' => 'Gift Cards',
        ],
        'console_payment_cards' => [
            'label_ru' => 'Карты оплаты для игровых приставок',
            'label_en' => 'Console payment cards',
            'description_ru' => 'Карты оплаты и пополнения для PlayStation, Xbox, Nintendo и других игровых экосистем с привязкой к платформе и региону.',
            'seo_indexable' => true,
            'schema_org' => 'Product',
            'google_product_category' => 'Software > Video Game Software',
        ],
        'game_wallet_topups' => [
            'label_ru' => 'Игровые кошельки и пополнения',
            'label_en' => 'Game wallet top-ups',
            'description_ru' => 'Пополнения игровых кошельков, внутриигровые валюты и digital codes для Steam, Roblox, PUBG, Battle.net, Epic Games и похожих сервисов.',
            'seo_indexable' => true,
            'schema_org' => 'Product',
            'google_product_category' => 'Software > Video Game Software',
        ],
        'mobile_app_store_cards' => [
            'label_ru' => 'Карты оплаты магазинов приложений',
            'label_en' => 'App store payment cards',
            'description_ru' => 'Карты оплаты и пополнения баланса для App Store, iTunes, Google Play и мобильных цифровых экосистем.',
            'seo_indexable' => true,
            'schema_org' => 'Product',
            'google_product_category' => 'Gift Cards',
        ],
        'subscriptions' => [
            'label_ru' => 'Подписки',
            'label_en' => 'Subscriptions',
            'description_ru' => 'Цифровые подписки, membership codes и продление доступа к сервисам, играм, приложениям и контентным платформам.',
            'seo_indexable' => true,
            'schema_org' => 'Product',
            'google_product_category' => 'Software',
        ],
        'software_licenses' => [
            'label_ru' => 'Лицензии и цифровое ПО',
            'label_en' => 'Software licenses',
            'description_ru' => 'Лицензии, ключи активации и цифровые продукты для программного обеспечения, антивирусов, VPN и рабочих приложений.',
            'seo_indexable' => true,
            'schema_org' => 'SoftwareApplication',
            'google_product_category' => 'Software',
        ],
        'payment_prepaid_cards' => [
            'label_ru' => 'Платежные и предоплаченные карты',
            'label_en' => 'Payment and prepaid cards',
            'description_ru' => 'Предоплаченные платежные продукты и digital cards, где важны номинал, валюта, регион и правила использования.',
            'seo_indexable' => true,
            'schema_org' => 'Product',
            'google_product_category' => 'Gift Cards',
        ],
        'telecom_topups' => [
            'label_ru' => 'Пополнение связи и интернета',
            'label_en' => 'Telecom top-ups',
            'description_ru' => 'Пополнение мобильной связи, интернет-пакетов и telecom-балансов через цифровую выдачу или API fulfillment.',
            'seo_indexable' => true,
            'schema_org' => 'Product',
            'google_product_category' => 'Electronics > Communications',
        ],
        'travel_entertainment_vouchers' => [
            'label_ru' => 'Путешествия и развлечения',
            'label_en' => 'Travel and entertainment vouchers',
            'description_ru' => 'Ваучеры для поездок, развлечений, билетов, поездок и related digital experiences с электронной доставкой.',
            'seo_indexable' => true,
            'schema_org' => 'Product',
            'google_product_category' => 'Gift Cards',
        ],
        'local_vouchers' => [
            'label_ru' => 'Локальные ваучеры Meanly',
            'label_en' => 'Meanly local vouchers',
            'description_ru' => 'Локальные ваучеры и складские цифровые позиции, выпускаемые или управляемые внутри Meanly.',
            'seo_indexable' => false,
            'schema_org' => 'Product',
            'google_product_category' => 'Gift Cards',
        ],
    ],

    'keyword_rules' => [
        'console_payment_cards' => [
            'playstation', 'play station', 'psn', 'xbox', 'nintendo', 'switch online',
        ],
        'mobile_app_store_cards' => [
            'apple', 'itunes', 'app store', 'google play', 'play store',
        ],
        'payment_prepaid_cards' => [
            'american express', 'amex', 'visa', 'mastercard', 'master card', 'prepaid',
            'virtual card',
        ],
        'software_licenses' => [
            'bitdefender', 'kaspersky', 'mcafee', 'norton', 'antivirus', 'vpn',
            'microsoft', 'office', 'windows', 'adobe', 'software', 'license', 'licence',
        ],
        'subscriptions' => [
            'subscription', 'подписка', 'abonnement', 'abonelik', 'spotify', 'netflix',
            'disney+', 'youtube premium', 'icloud', 'prime video',
        ],
        'game_wallet_topups' => [
            'steam', 'roblox', 'battle.net', 'battle net', 'epic games', 'pubg',
            'garena', 'free fire', 'razer gold', 'riot', 'valorant', 'league of legends',
        ],
        'telecom_topups' => [
            'mobile topup', 'mobile top-up', 'airtime', 'data pack', 'internet pack',
            'telecom', 'vodafone', 'orange', 'ooredoo', 'stc', 'etisalat',
        ],
        'travel_entertainment_vouchers' => [
            'airbnb', 'booking.com', 'uber', 'bolt', 'cinema', 'movie', 'ticket',
            'hotel', 'airline', 'flight',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Search aliases (intent corridors, brands, product keywords)
    |--------------------------------------------------------------------------
    |
    | Powers storefront search token expansion and CatalogQueryUnderstanding.
    | Brand aliases from intent_corridors.brand_overrides are merged at runtime.
    */
    'search_aliases' => [
        'intents' => [
            'play' => [
                'игры', 'игра', 'играть', 'gaming', 'gamer', 'gamers', 'game pass', 'gamepass',
                'консоль', 'приставка', 'playstation store', 'ps store', 'xbox live', 'nintendo eshop',
                'in-game', 'ingame', 'skin', 'skins', 'vbucks', 'v-bucks', 'robux', 'diamonds',
                'battle pass', 'dlc', 'game key', 'game code', 'wallet code',
            ],
            'stream' => [
                'подписка', 'подписки', 'подписку', 'стриминг', 'streaming', 'music', 'музыка',
                'фильмы', 'сериалы', 'watch', 'listen', 'premium', 'plus', 'membership',
                'netflix', 'spotify', 'disney', 'youtube premium', 'hbo', 'crunchyroll',
            ],
            'work' => [
                'работа', 'офис', 'office', 'license', 'licence', 'лицензия', 'ключ', 'activation',
                'antivirus', 'антивирус', 'vpn', 'software', 'по', 'windows', 'microsoft 365',
            ],
            'shop' => [
                'подарок', 'подарочная', 'подарочную', 'gift', 'gift card', 'giftcard', 'voucher',
                'сертификат', 'магазин', 'shopping', 'retail', 'ecommerce', 'e-commerce',
            ],
            'pay' => [
                'оплата', 'платеж', 'payment', 'prepaid', 'virtual card', 'virtualcard',
                'visa card', 'mastercard card', 'amex card', 'без карты', 'pay without card',
            ],
            'mobile' => [
                'телефон', 'мобильный', 'mobile', 'app store', 'google play', 'play store',
                'itunes', 'esim', 'e-sim', 'sim', 'связь', 'баланс', 'topup mobile', 'airtime',
            ],
            'go' => [
                'путешествие', 'поездка', 'travel', 'trip', 'hotel', 'отель', 'такси', 'taxi',
                'uber', 'ride', 'cinema', 'кино', 'ticket', 'билет', 'airbnb', 'booking',
            ],
        ],
        'brands' => [
            'Amazon' => ['amazon', 'амазон'],
            'Netflix' => ['netflix', 'нетфликс'],
            'Disney+' => ['disney+', 'disney plus', 'дисней'],
            'YouTube' => ['youtube', 'youtube premium', 'ютуб'],
            'Microsoft' => ['microsoft', 'майкрософт', 'ms office'],
            'NordVPN' => ['nordvpn', 'nord vpn', 'норд впн'],
            'Telegram Premium' => ['telegram premium', 'telegram', 'телеграм'],
            'Visa' => ['visa', 'виза'],
            'Mastercard' => ['mastercard', 'master card', 'мастеркард'],
            'Uber' => ['uber', 'убер'],
            'Airbnb' => ['airbnb', 'эрбнб'],
        ],
        'products' => [
            'gift_cards' => [
                'gift card', 'giftcard', 'gift-card', 'voucher', 'e-voucher', 'evoucher',
                'подарочная карта', 'подарочную карту', 'сертификат', 'gift code', 'store credit',
            ],
            'console_payment_cards' => [
                'psn card', 'psn code', 'playstation card', 'xbox card', 'xbox code',
                'nintendo card', 'eshop card', 'console card',
            ],
            'game_wallet_topups' => [
                'top up', 'topup', 'top-up', 'wallet', 'wallet code', 'game wallet',
                'пополнение', 'пополнить', 'баланс steam', 'steam wallet', 'robux', 'vbucks',
                'uc', 'diamonds', 'coins', 'credits', 'points pack',
            ],
            'mobile_app_store_cards' => [
                'app store card', 'itunes card', 'google play card', 'play store card',
                'apple gift', 'google play gift',
            ],
            'subscriptions' => [
                'subscription', 'membership', 'monthly pass', 'annual pass', 'renewal',
                'абонемент', 'membership code',
            ],
            'software_licenses' => [
                'license key', 'licence key', 'activation key', 'product key', 'software key',
                'ключ активации', 'лицензионный ключ',
            ],
            'payment_prepaid_cards' => [
                'prepaid card', 'virtual card', 'virtualcard', 'payment card', 'reloadable card',
            ],
            'telecom_topups' => [
                'mobile topup', 'mobile top-up', 'airtime', 'data pack', 'internet pack',
                'пополнение телефона', 'мобильный баланс',
            ],
            'travel_entertainment_vouchers' => [
                'travel voucher', 'hotel voucher', 'flight voucher', 'cinema ticket', 'movie ticket',
            ],
        ],
        'synonyms' => [
            'ps' => ['playstation', 'psn', 'sony'],
            'psn' => ['playstation', 'sony'],
            'ps4' => ['playstation', 'sony'],
            'ps5' => ['playstation', 'sony'],
            'play' => ['playstation'],
            'playstation' => ['ps', 'psn', 'sony'],
            'плейстейшн' => ['playstation', 'psn', 'sony'],
            'плейстейшен' => ['playstation', 'psn', 'sony'],
            'плейстешн' => ['playstation', 'psn', 'sony'],
            'сони' => ['sony', 'playstation'],
            'стим' => ['steam'],
            'сша' => ['us', 'usa', 'united states', 'unitedstates'],
            'usa' => ['us', 'united states', 'unitedstates', 'сша'],
            'us' => ['usa', 'united states', 'unitedstates', 'сша'],
            'ssha' => ['сша', 'us', 'usa', 'united states', 'unitedstates'],
            'cif' => ['сша', 'us', 'usa', 'united states', 'unitedstates'],
            'america' => ['us', 'usa', 'united states', 'unitedstates', 'сша'],
            'америка' => ['us', 'usa', 'united states', 'unitedstates', 'сша'],
            'штаты' => ['us', 'usa', 'united states', 'unitedstates', 'сша'],
            'states' => ['us', 'usa', 'united states', 'unitedstates'],
            'unitedstates' => ['us', 'usa', 'united states', 'сша'],
            'pleysteyshn' => ['playstation', 'psn', 'sony'],
            'pleystation' => ['playstation', 'psn', 'sony'],
            'tr' => ['turkey', 'turkiye', 'türkiye', 'турция'],
            'turkey' => ['tr', 'turkiye', 'türkiye', 'турция'],
            'turkiye' => ['tr', 'turkey', 'türkiye', 'турция'],
            'турция' => ['tr', 'turkey', 'turkiye', 'türkiye'],
            'подписка' => ['subscription', 'premium', 'plus'],
            'подписку' => ['subscription', 'premium', 'plus'],
            'подписки' => ['subscription', 'premium', 'plus'],
            'карта' => ['card', 'gift card'],
            'гифт' => ['gift', 'gift card'],
            'код' => ['code', 'key'],
            'иксбокс' => ['xbox'],
            'хбокс' => ['xbox'],
            'нинтендо' => ['nintendo'],
            'свитч' => ['nintendo', 'switch'],
            'спотифай' => ['spotify'],
            'спотифи' => ['spotify'],
            'нетфликс' => ['netflix'],
            'гугл плей' => ['google play'],
            'апп стор' => ['app store', 'apple'],
            'эпл' => ['apple'],
            'айтюнс' => ['itunes', 'apple'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Intent-based discovery corridors (ADR 0040)
    |--------------------------------------------------------------------------
    |
    | discovery_intent = why the buyer arrived (storefront navigation).
    | canonical_category = legacy product type (SEO / Yandex Market).
    |
    | Resolution: brand_overrides (priority order) → legacy_categories → default.
    */
    'discovery_default' => 'unclassified',

    'intent_resolution_priority' => [
        'play',
        'stream',
        'work',
        'pay',
        'go',
        'mobile',
        'shop',
    ],

    'intent_corridors' => [
        'play' => [
            'intent_key' => 'discover:play',
            'label_ru' => 'Играть',
            'label_en' => 'Play',
            'description_ru' => 'Пополнение игровых аккаунтов, консолей и in-game валют.',
            'description_en' => 'Game account top-ups, console wallets, and in-game currency.',
            'legacy_categories' => ['console_payment_cards', 'game_wallet_topups'],
            'brand_overrides' => [
                'PlayStation' => ['playstation', 'play station', 'psn', 'ps4', 'ps5', 'sony'],
                'Xbox' => ['xbox', 'game pass', 'gamepass', 'ea play'],
                'Nintendo' => ['nintendo', 'switch online'],
                'Steam' => ['steam'],
                'Roblox' => ['roblox', 'robux'],
                'Epic Games' => ['epic games', 'epic digital', 'fortnite'],
                'Riot Games' => ['riot games', 'riot', 'valorant', 'league of legends'],
                'PUBG' => ['pubg', 'pubg mobile'],
                'Free Fire' => ['free fire', 'garena'],
                'Battle.net' => ['battle.net', 'battle net', 'blizzard'],
                'Razer Gold' => ['razer gold', 'razer'],
                'Minecraft' => ['minecraft'],
                'Meta Quest' => ['meta quest', 'oculus'],
            ],
        ],
        'stream' => [
            'intent_key' => 'discover:stream',
            'label_ru' => 'Смотреть и слушать',
            'label_en' => 'Watch & listen',
            'description_ru' => 'Подписки на стриминг, музыку и видеоконтент.',
            'description_en' => 'Streaming, music, and video content subscriptions.',
            'legacy_categories' => ['subscriptions'],
            'brand_overrides' => [
                'Spotify' => ['spotify'],
                'Netflix' => ['netflix'],
                'Disney+' => ['disney+', 'disney plus'],
                'YouTube' => ['youtube premium', 'youtube'],
                'Crunchyroll' => ['crunchyroll'],
                'HBO' => ['hbo', 'max'],
                'Paramount+' => ['paramount+', 'paramount plus'],
                'Apple TV+' => ['apple tv+', 'apple tv'],
                'Starzplay' => ['starzplay', 'starz'],
                'Anghami' => ['anghami'],
                'Twitch' => ['twitch'],
                'Tinder' => ['tinder'],
                'Deezer' => ['deezer'],
                'Tidal' => ['tidal'],
            ],
        ],
        'work' => [
            'intent_key' => 'discover:work',
            'label_ru' => 'Работать и защитить',
            'label_en' => 'Work & protect',
            'description_ru' => 'Лицензии, VPN, антивирусы и рабочее ПО.',
            'description_en' => 'Software licenses, VPN, antivirus, and productivity tools.',
            'legacy_categories' => ['software_licenses'],
            'brand_overrides' => [
                'Microsoft' => ['microsoft', 'office 365', 'office365', 'windows'],
                'Adobe' => ['adobe'],
                'Norton' => ['norton'],
                'NordVPN' => ['nordvpn', 'nord vpn'],
                'Kaspersky' => ['kaspersky'],
                'Bitdefender' => ['bitdefender'],
                'McAfee' => ['mcafee'],
                'Telegram Premium' => ['telegram premium'],
            ],
        ],
        'shop' => [
            'intent_key' => 'discover:shop',
            'label_ru' => 'Подарить или потратить',
            'label_en' => 'Gift & shop',
            'description_ru' => 'Подарочные карты магазинов и e-commerce брендов.',
            'description_en' => 'Retail and e-commerce gift cards.',
            'legacy_categories' => ['gift_cards'],
            'exclude_brands' => [
                'Steam', 'PlayStation', 'Xbox', 'Nintendo', 'Roblox', 'Riot Games',
                'PUBG', 'Free Fire', 'Razer Gold', 'Epic Games', 'Battle.net',
            ],
            'brand_overrides' => [
                'Amazon' => ['amazon'],
                'IKEA' => ['ikea'],
                'Zalando' => ['zalando'],
                'Nike' => ['nike'],
                'Adidas' => ['adidas'],
                'Sephora' => ['sephora'],
                'Starbucks' => ['starbucks'],
                'Walmart' => ['walmart'],
                'Target' => ['target'],
                'Noon' => ['noon'],
                'Talabat' => ['talabat'],
                'Carrefour' => ['carrefour'],
                'Decathlon' => ['decathlon'],
                'Huawei' => ['huawei'],
                'HelloFresh' => ['hellofresh'],
                'Deliveroo' => ['deliveroo'],
                'Nordstrom' => ['nordstrom'],
                'Bol.com' => ['bol.com', 'bol com'],
            ],
        ],
        'pay' => [
            'intent_key' => 'discover:pay',
            'label_ru' => 'Оплатить без карты',
            'label_en' => 'Pay without a card',
            'description_ru' => 'Предоплаченные и виртуальные платёжные карты.',
            'description_en' => 'Prepaid and virtual payment cards.',
            'legacy_categories' => ['payment_prepaid_cards'],
            'brand_overrides' => [
                'Visa' => ['visa'],
                'Mastercard' => ['mastercard', 'master card'],
                'American Express' => ['american express', 'amex'],
                'Rewarble' => ['rewarble'],
            ],
        ],
        'mobile' => [
            'intent_key' => 'discover:mobile',
            'label_ru' => 'На телефоне',
            'label_en' => 'On your phone',
            'description_ru' => 'App Store, Google Play, мобильная связь и eSIM.',
            'description_en' => 'App Store, Google Play, mobile balance, and eSIM.',
            'legacy_categories' => ['mobile_app_store_cards', 'telecom_topups'],
            'brand_overrides' => [
                'Apple' => ['apple', 'itunes', 'app store'],
                'Google Play' => ['google play', 'play store'],
                'Etisalat' => ['etisalat'],
                'Vodafone' => ['vodafone'],
                'Orange' => ['orange'],
                'Ooredoo' => ['ooredoo'],
                'STC' => ['stc'],
                'iCloud' => ['icloud+', 'icloud'],
                'Google One' => ['google one'],
            ],
        ],
        'go' => [
            'intent_key' => 'discover:go',
            'label_ru' => 'В путь и на досуг',
            'label_en' => 'Go & enjoy',
            'description_ru' => 'Поездки, такси, отели, кино и развлечения.',
            'description_en' => 'Travel, rides, hotels, cinema, and entertainment.',
            'legacy_categories' => ['travel_entertainment_vouchers'],
            'brand_overrides' => [
                'Uber' => ['uber'],
                'Airbnb' => ['airbnb'],
                'Booking.com' => ['booking.com', 'booking'],
                'Hotels.com' => ['hotels.com', 'hotelsgift'],
                'TripGift' => ['tripgift', 'flystaygift'],
                'Reel Cinemas' => ['reel cinemas', 'cinema'],
                'StubHub' => ['stubhub'],
                'Barnes & Noble' => ['barnes & noble', 'barnes and noble'],
            ],
        ],
    ],

    'cross_links' => [
        'play' => [
            [
                'target_corridor' => 'mobile',
                'label_ru' => 'Оплата на iOS/Android',
                'label_en' => 'Pay on iOS/Android',
                'brand_filter' => ['Apple', 'Google Play'],
            ],
        ],
    ],

    'channels' => [
        'yandex_market' => [
            'categories' => [
                'gift_cards' => [
                    'setting' => 'YM_RETAIL_GIFT_CATEGORY_ID',
                    'default' => 989939,
                ],
                'console_payment_cards' => [
                    'setting' => 'YM_CONSOLE_PAYMENT_CATEGORY_ID',
                    'default' => 76746760,
                ],
                'game_wallet_topups' => [
                    'setting' => 'YM_GAME_DIGITAL_CATEGORY_ID',
                    'default' => 70301474,
                ],
                'mobile_app_store_cards' => [
                    'setting' => 'YM_APP_STORE_PAYMENT_CATEGORY_ID',
                    'default' => 76748492,
                ],
                'software_licenses' => [
                    'setting' => 'YM_SOFTWARE_DIGITAL_CATEGORY_ID',
                    'default' => 70167260,
                ],
                'subscriptions' => [
                    'setting' => 'YM_SUBSCRIPTION_PAYMENT_CATEGORY_ID',
                    'default' => 989939,
                ],
                'payment_prepaid_cards' => [
                    'setting' => 'YM_PAYMENT_PREPAID_CATEGORY_ID',
                    'default' => 989939,
                ],
                'telecom_topups' => [
                    'setting' => 'YM_TELECOM_TOPUP_CATEGORY_ID',
                    'default' => 989939,
                ],
                'travel_entertainment_vouchers' => [
                    'setting' => 'YM_TRAVEL_ENTERTAINMENT_CATEGORY_ID',
                    'default' => 989939,
                ],
                'local_vouchers' => [
                    'setting' => 'YM_LOCAL_VOUCHER_CATEGORY_ID',
                    'default' => 989939,
                ],
            ],
        ],
    ],
];
