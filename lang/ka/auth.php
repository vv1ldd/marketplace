<?php

declare(strict_types=1);

return [
    'failed' => 'ეს მონაცემები ჩვენს ჩანაწერებს არ ემთხვევა.',
    'password' => 'მითითებული პაროლი არასწორია.',
    'throttle' => 'შესვლის ძალიან ბევრი მცდელობაა. სცადეთ თავიდან :seconds წამში.',
    'simple_l1' => [
        'page_title' => 'Simple Layer One-ზე გადასვლა',
        'countdown_prefix' => 'გადამისამართება',
        'countdown_suffix' => 'წამში...',
        'inline' => [
            'title' => 'Simple Layer One ახლავე გაიხსნება',
            'body' => 'დაადასტურეთ passkey-ით და შემდეგ Meanly-ში დაბრუნდებით.',
            'cta' => 'გაგრძელება ახლა',
            'countdown' => 'გადამისამართება :seconds წამში...',
            'redirecting' => 'გადამისამართება...',
        ],
        'vault_open' => [
            'title' => 'გავხსნათ თქვენი სეიფი?',
            'body' => 'ახლა გაიხსნება SL1 Connect. დაადასტურეთ passkey-ით და დაგაბრუნებთ თქვენი შენაძენების სეიფში.',
            'facts' => [
                'owner_only' => 'კოდებს მხოლოდ ანგარიშის მფლობელი ნახავს.',
                'no_keys' => 'Meanly თქვენს გასაღებებს არასოდეს იღებს.',
            ],
            'cta' => 'დადასტურება Simple Layer One-ში',
        ],
        'wallet_pay' => [
            'title' => 'გადავიხადოთ wallet-ით?',
            'body' => 'ახლა გაიხსნება SL1 Wallet. დაადასტურეთ გადახდა იქ და დაბრუნდით checkout-ზე.',
            'facts' => [
                'wallet_stays_private' => 'ბალანსი და ოპერაციები SL1 Wallet-ში რჩება.',
                'result_only' => 'Meanly იღებს მხოლოდ გადახდის შედეგს.',
            ],
            'cta' => 'გადახდაზე გადასვლა',
        ],
        'identity_create' => [
            'title' => 'შევქმნათ ანგარიში SL1-ით?',
            'body' => 'ახლა გაიხსნება SL1 Connect. აირჩიეთ სახელი და შექმენით passkey, რომ Meanly-ში პაროლის გარეშე შეხვიდეთ.',
            'facts' => [
                'passkey_device' => 'თქვენი passkey თქვენს მოწყობილობაზე რჩება.',
                'return_after' => 'ანგარიშის შექმნის შემდეგ Meanly-ში დაბრუნდებით.',
            ],
            'cta' => 'შექმნა Simple Layer One-ში',
        ],
        'identity_confirm' => [
            'title' => 'შევიდეთ Simple Layer One-ით?',
            'body' => 'ახლა გაიხსნება SL1 Connect. თუ ანგარიში უკვე გაქვთ, შედით passkey-ით. თუ არა, შექმენით რამდენიმე ნაბიჯში. შემდეგ Meanly-ში დაბრუნდებით.',
            'facts' => [
                'no_password' => 'პაროლი არ არის საჭირო.',
                'passkey_device' => 'თქვენი passkey თქვენს მოწყობილობაზე რჩება.',
            ],
            'cta' => 'Simple Layer One-ით შესვლა',
        ],
    ],
];
