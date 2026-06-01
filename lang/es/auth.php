<?php

declare(strict_types=1);

return [
    'failed' => 'Estas credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña indicada no es correcta.',
    'throttle' => 'Demasiados intentos de acceso. Intenta de nuevo en :seconds segundos.',
    'simple_l1' => [
        'page_title' => 'Continuar a Simple Layer One',
        'countdown_prefix' => 'Redirigiendo en',
        'countdown_suffix' => 'segundos...',
        'inline' => [
            'title' => 'Simple Layer One se abrirá ahora',
            'body' => 'Confirma con tu passkey y luego volverás a Meanly.',
            'cta' => 'Continuar ahora',
            'countdown' => 'Redirigiendo en :seconds segundos...',
            'redirecting' => 'Redirigiendo...',
        ],
        'vault_open' => [
            'title' => '¿Abrir tu caja segura?',
            'body' => 'SL1 Connect se abrirá ahora. Confirma con tu passkey y volverás a la caja segura de tus compras.',
            'facts' => [
                'owner_only' => 'Solo el dueño de la cuenta puede ver los códigos.',
                'no_keys' => 'Meanly nunca recibe tus claves.',
            ],
            'cta' => 'Confirmar en Simple Layer One',
        ],
        'wallet_pay' => [
            'title' => '¿Pagar con tu wallet?',
            'body' => 'SL1 Wallet se abrirá ahora. Confirma el pago allí y vuelve al checkout.',
            'facts' => [
                'wallet_stays_private' => 'Tu saldo y operaciones permanecen en SL1 Wallet.',
                'result_only' => 'Meanly recibe solo el resultado del pago.',
            ],
            'cta' => 'Continuar al pago',
        ],
        'identity_create' => [
            'title' => '¿Crear una cuenta con SL1?',
            'body' => 'SL1 Connect se abrirá ahora. Elige un nombre y crea una passkey para entrar a Meanly sin contraseña.',
            'facts' => [
                'passkey_device' => 'Tu passkey permanece en tu dispositivo.',
                'return_after' => 'Después de crear la cuenta, vuelves a Meanly.',
            ],
            'cta' => 'Crear en Simple Layer One',
        ],
        'identity_confirm' => [
            'title' => '¿Entrar con Simple Layer One?',
            'body' => 'SL1 Connect se abrirá ahora. Si ya tienes cuenta, entra con tu passkey. Si no, créala en unos pasos. Después volverás a Meanly.',
            'facts' => [
                'no_password' => 'No necesitas contraseña.',
                'passkey_device' => 'Tu passkey permanece en tu dispositivo.',
            ],
            'cta' => 'Entrar con Simple Layer One',
        ],
    ],
];
