<?php

declare(strict_types=1);

return [
    'failed'   => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    'simple_l1' => [
        'page_title' => 'Continue to Simple Layer One',
        'countdown_prefix' => 'Redirecting in',
        'countdown_suffix' => 'seconds...',
        'inline' => [
            'title' => 'Simple Layer One is opening',
            'body' => 'Confirm with your passkey, then return to Meanly.',
            'cta' => 'Continue now',
            'countdown' => 'Redirecting in :seconds seconds...',
            'redirecting' => 'Redirecting...',
        ],
        'vault_open' => [
            'title' => 'Open your safe?',
            'body' => 'SL1 Connect will open now. Confirm with your passkey, and we will bring you back to your purchase safe.',
            'facts' => [
                'owner_only' => 'Only the account owner can see the codes.',
                'no_keys' => 'Meanly never receives your keys.',
            ],
            'cta' => 'Confirm in Simple Layer One',
        ],
        'wallet_pay' => [
            'title' => 'Pay with your wallet?',
            'body' => 'SL1 Wallet will open now. Confirm the payment there, then return to checkout.',
            'facts' => [
                'wallet_stays_private' => 'Your balance and operations stay in SL1 Wallet.',
                'result_only' => 'Meanly receives only the payment result.',
            ],
            'cta' => 'Continue to payment',
        ],
        'identity_create' => [
            'title' => 'Create an account with SL1?',
            'body' => 'SL1 Connect will open now. Choose a name and create a passkey to sign in to Meanly without a password.',
            'facts' => [
                'passkey_device' => 'Your passkey stays on your device.',
                'return_after' => 'After creating the account, you return to Meanly.',
            ],
            'cta' => 'Create in Simple Layer One',
        ],
        'identity_confirm' => [
            'title' => 'Sign in with Simple Layer One?',
            'body' => 'SL1 Connect will open now. If you already have an account, sign in with your passkey. If not, create one in a few steps. After that, you return to Meanly.',
            'facts' => [
                'no_password' => 'No password needed.',
                'passkey_device' => 'Your passkey stays on your device.',
            ],
            'cta' => 'Sign in with Simple Layer One',
        ],
    ],
];
