<?php

return [
    'nav' => [
        'how'      => 'How it Works',
        'features' => 'Features',
        'security' => 'Security',
        'login'    => 'Partner Login →',
    ],

    'hero' => [
        'badge'         => 'Digital commerce platform',
        'title'         => 'Sell digital goods on :highlight',
        'highlight'     => 'autopilot',
        'desc'          => 'Meanly automates the full sales cycle for gift cards and activation codes across marketplaces — from order receipt to code delivery.',
        'cta_primary'   => 'Open Partner Dashboard',
        'cta_secondary' => 'How it Works',
        'stat_channels' => 'Sales Channels',
        'stat_crypto'   => 'Data Protection',
        'stat_api'      => 'Integrations',
        'stat_realtime' => 'Analytics',
    ],

    'how' => [
        'label' => 'How it Works',
        'title' => 'Four Steps to Automation',
        'desc'  => 'From connecting your store to automatic code delivery — everything in one place.',
        'steps' => [
            ['title' => 'Connect Your Store',    'desc' => 'Authenticate via Yandex Market API or another channel. Your orders will start flowing in automatically.'],
            ['title' => 'Upload Your Catalog',   'desc' => 'Add products with activation codes, set prices and delivery parameters for each item.'],
            ['title' => 'Orders Come In',        'desc' => 'Marketplace webhooks create orders in the system and trigger automated processing.'],
            ['title' => 'Code Sent to Buyer',    'desc' => 'The system automatically delivers the activation code via the marketplace chat and buyer email.'],
        ],
    ],

    'features' => [
        'label' => 'Features',
        'title' => 'Everything for Digital Commerce',
        'desc'  => 'Tools that truly save time and reduce operational errors.',
        'items' => [
            ['icon' => '🤖', 'title' => 'Auto-Activation',   'desc' => 'Codes are issued automatically after order confirmation — no operator needed.'],
            ['icon' => '📊', 'title' => 'Order Analytics', 'desc' => 'Clear order, code delivery, and operations history in one interface.'],
            ['icon' => '🔌', 'title' => 'Multi-Channel',      'desc' => 'Yandex Market, WooCommerce, Telegram — all orders in one interface.'],
            ['icon' => '🛡️', 'title' => 'Data Protection',     'desc' => 'Buyer contacts and order data are protected and shown only to the right roles.'],
            ['icon' => '🌍', 'title' => 'Multiple Currencies',     'desc' => 'RUB, TRY and other currencies with automatic conversion at current rates.'],
            ['icon' => '🧪', 'title' => 'Sandbox Mode',       'desc' => 'Test integrations and APIs on real infrastructure without risk to live data.'],
        ],
    ],

    'channels' => [
        'label' => 'Integrations',
        'title' => 'Works with Your Channels',
    ],

    'security' => [
        'label' => 'Security',
        'title' => 'Reliable daily operations',
        'items' => [
            ['title' => 'Personal data protection', 'desc' => 'Contacts, orders, and service data are handled safely and shown only where needed.'],
            ['title' => 'Secure sign-in',          'desc' => 'Sign-in and important actions use passwordless confirmation that is simple for the user.'],
            ['title' => 'Operations history',      'desc' => 'Important actions are saved in a clear activity history for fast support and control.'],
            ['title' => 'Access control',          'desc' => 'Owners, team members, and support staff can have different levels of access.'],
        ],
    ],

    'cta' => [
        'title'  => 'Ready to Start Selling Digital Goods?',
        'desc'   => 'Open the partner dashboard and start managing your catalog right now.',
        'button' => 'Open Partner Dashboard →',
    ],

    'footer' => [
        'rights'  => 'All rights reserved',
        'partner' => 'Partners',
        'admin'   => 'Administration',
        'redeem'  => 'Activate Code',
    ],
];
