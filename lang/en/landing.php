<?php

return [
    'nav' => [
        'how'      => 'How it Works',
        'features' => 'Features',
        'security' => 'Security',
        'login'    => 'Partner Login →',
    ],

    'hero' => [
        'badge'         => '🛡️ Sovereign B2B Platform',
        'title'         => 'Sell digital goods on :highlight',
        'highlight'     => 'autopilot',
        'desc'          => 'Meanly automates the full sales cycle for gift cards and activation codes across marketplaces — from order receipt to code delivery.',
        'cta_primary'   => 'Open Partner Dashboard',
        'cta_secondary' => 'How it Works',
        'stat_channels' => 'Sales Channels',
        'stat_crypto'   => 'Data Encryption',
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
            ['icon' => '📊', 'title' => 'Analytics & Ledger', 'desc' => 'Full transaction history with a deterministic audit log in real time.'],
            ['icon' => '🔌', 'title' => 'Multi-Channel',      'desc' => 'Yandex Market, WooCommerce, Telegram — all orders in one interface.'],
            ['icon' => '🛡️', 'title' => 'PII Encryption',     'desc' => 'Buyer data stored encrypted. Search via blind indexes without decryption.'],
            ['icon' => '🌍', 'title' => 'Multi-Currency',     'desc' => 'RUB, TRY, USDT and more with automatic conversion at live rates.'],
            ['icon' => '🧪', 'title' => 'Sandbox Mode',       'desc' => 'Test integrations and APIs on real infrastructure without risk to live data.'],
        ],
    ],

    'channels' => [
        'label' => 'Integrations',
        'title' => 'Works with Your Channels',
    ],

    'security' => [
        'label' => 'Security',
        'title' => 'Sovereign Architecture',
        'items' => [
            ['title' => 'Vault Transit Encryption', 'desc' => 'All personal data encrypted via HashiCorp Vault or AES-256-CBC. The app never holds keys.'],
            ['title' => 'Blind Index Auth',          'desc' => 'Authentication against encrypted fields via HMAC-SHA256 — no full-table decryption.'],
            ['title' => 'Deterministic Ledger',      'desc' => 'Every action recorded with a state hash. Tampering is impossible.'],
            ['title' => 'Multi-Guard Auth',          'desc' => 'Separate guards for Admins and Partners with fully isolated sessions.'],
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
