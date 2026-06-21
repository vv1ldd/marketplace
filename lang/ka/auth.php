<?php

declare(strict_types=1);

return [
    'failed' => 'ეს მონაცემები ჩვენს ჩანაწერებს არ ემთხვევა.',
    'password' => 'მითითებული პაროლი არასწორია.',
    'throttle' => 'შესვლის ძალიან ბევრი მცდელობაა. სცადეთ თავიდან :seconds წამში.',
    'simple_l1' => [
        'page_title' => 'Maestrooo Identity-ზე გადასვლა',
        'countdown_prefix' => 'გადამისამართება',
        'countdown_suffix' => 'წამში...',
        'inline' => [
            'title' => 'Maestrooo Identity ახლავე გაიხსნება',
            'body' => 'დაადასტურეთ Maestrooo Identity-ში და შემდეგ Meanly-ში დაბრუნდებით.',
            'cta' => 'გაგრძელება ახლა',
            'countdown' => 'გადამისამართება :seconds წამში...',
            'redirecting' => 'გადამისამართება...',
            'popup_body' => 'დაასრულეთ passkey-ის შექმნა გახსნილ ფანჯარაში. თუ ფანჯარა არ გაიხსნა, გამორთეთ popup-ბლოკერი ან დააჭირეთ ქვემოთ.',
            'popup_reopen' => 'ხელახლა გახსნა',
        ],
        'vault_open' => [
            'title' => 'გავხსნათ თქვენი სეიფი?',
            'body' => 'ახლა გაიხსნება Maestrooo Identity. დაადასტურეთ Vault და დაგაბრუნებთ თქვენი შენაძენების სეიფში.',
            'facts' => [
                'owner_only' => 'კოდებს მხოლოდ ანგარიშის მფლობელი ნახავს.',
                'no_keys' => 'Meanly თქვენს გასაღებებს არასოდეს იღებს.',
            ],
            'cta' => 'დადასტურება Maestrooo Identity-ში',
        ],
        'wallet_pay' => [
            'title' => 'გადავიხადოთ wallet-ით?',
            'body' => 'ახლა გაიხსნება Maestrooo Identity. დაადასტურეთ გადახდა იქ და დაბრუნდით checkout-ზე.',
            'facts' => [
                'wallet_stays_private' => 'ბალანსი და ოპერაციები Maestrooo Identity-ში რჩება.',
                'result_only' => 'Meanly იღებს მხოლოდ გადახდის შედეგს.',
            ],
            'cta' => 'გადახდაზე გადასვლა',
        ],
        'identity_create' => [
            'title' => 'გავაგრძელოთ Maestrooo Identity-ით?',
            'body' => 'ახლა გაიხსნება Maestrooo Identity. იქ შექმნით ან დაადასტურებთ Meanly ანგარიშს და შემდეგ Meanly-ში დაბრუნდებით.',
            'facts' => [
                'passkey_device' => 'თქვენი დაცული გასაღები თქვენს მოწყობილობაზე რჩება.',
                'return_after' => 'ანგარიშის შექმნის შემდეგ Meanly-ში დაბრუნდებით.',
            ],
            'cta' => 'გაგრძელება Maestrooo Identity-ში',
        ],
        'identity_confirm' => [
            'title' => 'შევიდეთ Maestrooo Identity-ით?',
            'body' => 'ახლა გაიხსნება Maestrooo Identity. დაადასტურეთ ანგარიში და შემდეგ Meanly-ში დაბრუნდებით.',
            'facts' => [
                'no_password' => 'პაროლი არ არის საჭირო.',
                'passkey_device' => 'Meanly იღებს მხოლოდ დადასტურებულ შედეგს.',
            ],
            'cta' => 'Maestrooo Identity-ით შესვლა',
        ],
    ],
];
