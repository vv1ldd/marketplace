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
