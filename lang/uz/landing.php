<?php

return [
    'nav' => [
        'how'      => 'Qanday ishlaydi',
        'features' => 'Imkoniyatlar',
        'security' => 'Xavfsizlik',
        'login'    => 'Kabinetga kirish →',
    ],

    'hero' => [
        'badge'         => '🛡️ Suveren B2B-platforma',
        'title'         => 'Raqamli tovarlarni :highlight sotish',
        'highlight'     => 'avtopilotda',
        'desc'          => 'Meanly sovg\'a kartalari va faollashtirish kodlarini marketpleyslar orqali sotishning to\'liq tsiklini avtomatlashtiradi — sargyt qabul qilishdan kodni yetkazib berishgacha.',
        'cta_primary'   => 'Hamkor kabinetini ochish',
        'cta_secondary' => 'Qanday ishlaydi',
        'stat_channels' => 'Sotuv kanallari',
        'stat_crypto'   => 'Ma\'lumotlarni shifrlash',
        'stat_api'      => 'Integratsiyalar',
        'stat_realtime' => 'Analitika',
    ],

    'how' => [
        'label' => 'Qanday ishlaydi',
        'title' => 'Avtomatlashtirishga to\'rt qadam',
        'desc'  => 'Do\'konni ulashdan avtomatik kod berishgacha — barchasi bir joyda.',
        'steps' => [
            ['title' => 'Do\'konni ulang',     'desc' => 'Yandex Market API yoki boshqa kanal orqali avtorizatsiyadan o\'ting. Buyurtmalaringiz avtomatik kela boshlaydi.'],
            ['title' => 'Katalogni yuklang',   'desc' => 'Faollashtirish kodli tovarlarni qo\'shing, narxlar va yetkazib berish parametrlarini belgilang.'],
            ['title' => 'Buyurtmalar o\'zi keladi', 'desc' => 'Marketpleys vebhuklari tizimda buyurtmalar yaratadi va avtomatik ishlov berishni boshlaydi.'],
            ['title' => 'Kod xaridorga ketadi', 'desc' => 'Tizim avtomatik ravishda marketpleys chati va xaridor emailiga faollashtirish kodini yuboradi.'],
        ],
    ],

    'features' => [
        'label' => 'Imkoniyatlar',
        'title' => 'Raqamli savdo uchun barchasi',
        'desc'  => 'Vaqtni tejaydigan va operatsion xatolarni kamaytiradigan vositalar.',
        'items' => [
            ['icon' => '🤖', 'title' => 'Avto-faollashtirish', 'desc' => 'Kodlar buyurtma tasdiqlangandan so\'ng operator ishtirokisiz avtomatik beriladi.'],
            ['icon' => '📊', 'title' => 'Analitika va Ledger', 'desc' => 'Har bir tranzaksiyaning batafsil tarixi va real vaqtdagi audit jurnali.'],
            ['icon' => '🔌', 'title' => 'Ko\'p kanallilik',    'desc' => 'Yandex Market, WooCommerce, Telegram — barcha buyurtmalar bir interfeysda.'],
            ['icon' => '🛡️', 'title' => 'PII shifrlash',      'desc' => 'Xaridor ma\'lumotlari shifrlangan holda saqlanadi. Blind index orqali qidiruv.'],
            ['icon' => '🌍', 'title' => 'Ko\'p valyutalilik', 'desc' => 'RUB, TRY, USDT va boshqalar — dolzarb kurs bo\'yicha avtomatik konvertatsiya.'],
            ['icon' => '🧪', 'title' => 'Sandbox rejimi',    'desc' => 'Integratsiyalar va API-larni real infratuzilmada xavfsiz sinab ko\'ring.'],
        ],
    ],

    'channels' => [
        'label' => 'Integratsiyalar',
        'title' => 'Sizning kanallaringiz bilan ishlaydi',
    ],

    'security' => [
        'label' => 'Xavfsizlik',
        'title' => 'Suveren arxitektura',
        'items' => [
            ['title' => 'Vault Shifrlash',     'desc' => 'Barcha shaxsiy ma\'lumotlar HashiCorp Vault yoki AES-256-CBC orqali shifrlanadi.'],
            ['title' => 'Blind Index Auth',    'desc' => 'Shifrlangan maydonlar bo\'yicha HMAC-SHA256 orqali autentifikatsiya.'],
            ['title' => 'Deterministik Ledger', 'desc' => 'Har bir amal holat xeshi bilan audit jurnaliga yoziladi. Soxtalashtirish imkonsiz.'],
            ['title' => 'Multi-Guard Auth',    'desc' => 'Adminlar va Hamkorlar uchun alohida seansli gvardiyalar.'],
        ],
    ],

    'cta' => [
        'title'  => 'Raqamli tovarlarni sotishga tayyormisiz?',
        'desc'   => 'Hamkor kabinetiga kiring va katalogni boshqarishni hoziroq boshlang.',
        'button' => 'Hamkor kabinetini ochish →',
    ],

    'footer' => [
        'rights'  => 'Barcha huquqlar himoyalangan',
        'partner' => 'Hamkorlarga',
        'admin'   => 'Ma\'muriyat',
        'redeem'  => 'Kodni faollashtirish',
    ],
];
