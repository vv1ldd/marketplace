<?php

declare(strict_types=1);

return [
    'failed' => 'Estas credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña indicada no es correcta.',
    'throttle' => 'Demasiados intentos de acceso. Intenta de nuevo en :seconds segundos.',
    'simple_l1' => [
        'page_title' => 'Continuar a Meanly One',
        'countdown_prefix' => 'Redirigiendo en',
        'countdown_suffix' => 'segundos...',
        'inline' => [
            'title' => 'Meanly One se abrirá ahora',
            'body' => 'Aprueba en Meanly One y luego volverás a Meanly.',
            'cta' => 'Continuar ahora',
            'countdown' => 'Redirigiendo en :seconds segundos...',
            'redirecting' => 'Redirigiendo...',
        ],
        'vault_open' => [
            'title' => '¿Abrir tu caja segura?',
            'body' => 'Meanly One se abrirá ahora. Aprueba tu Vault y volverás a la caja segura de tus compras.',
            'facts' => [
                'owner_only' => 'Solo el dueño de la cuenta puede ver los códigos.',
                'no_keys' => 'Meanly nunca recibe tus claves.',
            ],
            'cta' => 'Aprobar en Meanly One',
        ],
        'wallet_pay' => [
            'title' => '¿Pagar con tu wallet?',
            'body' => 'Meanly One se abrirá ahora. Aprueba el pago allí y vuelve al checkout.',
            'facts' => [
                'wallet_stays_private' => 'Tu saldo y operaciones permanecen en Meanly One.',
                'result_only' => 'Meanly recibe solo el resultado del pago.',
            ],
            'cta' => 'Continuar al pago',
        ],
        'identity_create' => [
            'title' => '¿Continuar con Meanly One?',
            'body' => 'Meanly One se abrirá ahora. Crea o confirma tu cuenta de Meanly allí y luego vuelve a Meanly.',
            'facts' => [
                'passkey_device' => 'Tu clave segura permanece en tu dispositivo.',
                'return_after' => 'Después de crear la cuenta, vuelves a Meanly.',
            ],
            'cta' => 'Continuar en Meanly One',
        ],
        'identity_confirm' => [
            'title' => '¿Entrar con Meanly One?',
            'body' => 'Meanly One se abrirá ahora. Aprueba tu cuenta y volverás a Meanly.',
            'facts' => [
                'no_password' => 'No necesitas contraseña.',
                'passkey_device' => 'Meanly recibe solo un resultado verificado.',
            ],
            'cta' => 'Entrar con Meanly One',
        ],
    ],
];
