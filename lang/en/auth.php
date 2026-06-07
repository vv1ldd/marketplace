<?php

declare(strict_types=1);

return [
    'failed'   => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    'simple_l1' => [
        'page_title' => 'Continue to Meanly One',
        'countdown_prefix' => 'Redirecting in',
        'countdown_suffix' => 'seconds...',
        'inline' => [
            'title' => 'Meanly One is opening',
            'body' => 'Approve the identity request in Meanly One, then return to Meanly.',
            'cta' => 'Continue now',
            'countdown' => 'Redirecting in :seconds seconds...',
            'redirecting' => 'Redirecting...',
        ],
        'vault_open' => [
            'title' => 'Open your safe?',
            'body' => 'Meanly One will open now. Approve the identity request, and we will bring you back to your purchase safe.',
            'facts' => [
                'owner_only' => 'Only the account owner can see the codes.',
                'no_keys' => 'Meanly never receives your keys.',
            ],
            'cta' => 'Approve in Meanly One',
        ],
        'wallet_pay' => [
            'title' => 'Pay with your wallet?',
            'body' => 'Meanly One will open now. Approve the payment there, then return to checkout.',
            'facts' => [
                'wallet_stays_private' => 'Your balance and operations stay in Meanly One.',
                'result_only' => 'Meanly receives only the payment result.',
            ],
            'cta' => 'Continue to payment',
        ],
        'identity_create' => [
            'title' => 'Continue with Meanly One?',
            'body' => 'Meanly One will open now. Create or confirm your SL1 identity there, then return to Meanly.',
            'facts' => [
                'passkey_device' => 'Your credential stays inside the identity layer.',
                'return_after' => 'After creating the account, you return to Meanly.',
            ],
            'cta' => 'Continue in Meanly One',
        ],
        'identity_confirm' => [
            'title' => 'Sign in with Meanly One?',
            'body' => 'Meanly One will open now. Approve your SL1 identity, then return to Meanly.',
            'facts' => [
                'no_password' => 'No password needed.',
                'passkey_device' => 'Meanly receives only a verified identity result.',
            ],
            'cta' => 'Sign in with Meanly One',
        ],
    ],
];
