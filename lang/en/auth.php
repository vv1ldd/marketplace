<?php

declare(strict_types=1);

return [
    'failed'   => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    'simple_l1' => [
        'page_title' => 'Continue to Maestrooo Identity',
        'countdown_prefix' => 'Redirecting in',
        'countdown_suffix' => 'seconds...',
        'inline' => [
            'title' => 'Maestrooo Identity is opening',
            'body' => 'Approve in Maestrooo Identity, then return to Meanly.',
            'cta' => 'Continue now',
            'countdown' => 'Redirecting in :seconds seconds...',
            'redirecting' => 'Redirecting...',
            'popup_body' => 'Complete passkey setup in the popup window. If nothing opened, check your popup blocker or click below.',
            'popup_reopen' => 'Open again',
        ],
        'vault_open' => [
            'title' => 'Open your safe?',
            'body' => 'Maestrooo Identity will open now. Approve your Vault, and we will bring you back to your purchase safe.',
            'facts' => [
                'owner_only' => 'Only the account owner can see the codes.',
                'no_keys' => 'Meanly never receives your keys.',
            ],
            'cta' => 'Approve in Maestrooo Identity',
        ],
        'wallet_pay' => [
            'title' => 'Pay with your wallet?',
            'body' => 'Maestrooo Identity will open now. Approve the payment there, then return to checkout.',
            'facts' => [
                'wallet_stays_private' => 'Your balance and operations stay in Maestrooo Identity.',
                'result_only' => 'Meanly receives only the payment result.',
            ],
            'cta' => 'Continue to payment',
        ],
        'identity_create' => [
            'title' => 'Continue with Maestrooo Identity?',
            'body' => 'Maestrooo Identity will open now. Create or confirm your Meanly account there, then return to Meanly.',
            'facts' => [
                'passkey_device' => 'Your secure key stays on your device.',
                'return_after' => 'After creating the account, you return to Meanly.',
            ],
            'cta' => 'Continue in Maestrooo Identity',
        ],
        'identity_confirm' => [
            'title' => 'Continue with Maestrooo Identity?',
            'body' => 'Maestrooo Identity will open now. Confirm login or create an account, then return to Meanly.',
            'facts' => [
                'no_password' => 'No password needed.',
                'passkey_device' => 'Secure key stays on your device.',
            ],
            'cta' => 'Continue in Maestrooo Identity',
        ],
    ],
];
