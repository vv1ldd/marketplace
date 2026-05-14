<?php

return [
    'channels' => [
        'yandex_market' => [
            'label' => 'Яндекс Маркет',
            'icon' => '🟡',
            'enabled' => true,
            'implemented' => true,
            'group' => 'marketplaces',
        ],
        'avito' => [
            'label' => 'Авито',
            'icon' => '🟢',
            'enabled' => true,
            'implemented' => false,
            'group' => 'marketplaces',
        ],
        'wildberries' => [
            'label' => 'Wildberries',
            'icon' => '🟣',
            'enabled' => true,
            'implemented' => false,
            'group' => 'marketplaces',
        ],
        'ozon' => [
            'label' => 'Ozon',
            'icon' => '🔵',
            'enabled' => true,
            'implemented' => false,
            'group' => 'marketplaces',
        ],
        'offline_store' => [
            'label' => 'Оффлайн магазин',
            'icon' => '🏪',
            'enabled' => true,
            'implemented' => true,
            'group' => 'offline',
        ],
        'woocommerce' => [
            'label' => 'WooCommerce',
            'icon' => '🛒',
            'enabled' => true,
            'implemented' => true,
            'group' => 'cms',
        ],
        'telegram_bot' => [
            'label' => 'Telegram Bot (Магазин)',
            'icon' => '🤖',
            'enabled' => true,
            'implemented' => false,
            'group' => 'messengers',
        ],
        'vk_store' => [
            'label' => 'ВКонтакте (Магазин)',
            'icon' => '📱',
            'enabled' => true,
            'implemented' => false,
            'group' => 'messengers',
        ],
        'whatsapp_business' => [
            'label' => 'WhatsApp Business',
            'icon' => '💬',
            'enabled' => true,
            'implemented' => false,
            'group' => 'messengers',
        ],
        'messenger_max' => [
            'label' => 'Messenger Max',
            'icon' => '⚡',
            'enabled' => true,
            'implemented' => false,
            'group' => 'messengers',
        ],
    ],
];
