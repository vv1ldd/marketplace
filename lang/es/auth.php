<?php

declare(strict_types=1);

return [
    'failed' => 'Estas credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña indicada no es correcta.',
    'throttle' => 'Demasiados intentos de acceso. Intenta de nuevo en :seconds segundos.',
    'simple_l1' => [
        'page_title' => 'Continuar a Maestrooo Identity',
        'countdown_prefix' => 'Redirigiendo en',
        'countdown_suffix' => 'segundos...',
        'inline' => [
            'title' => 'Maestrooo Identity se abrirá ahora',
            'body' => 'Aprueba en Maestrooo Identity y luego volverás a Meanly.',
            'cta' => 'Continuar ahora',
            'countdown' => 'Redirigiendo en :seconds segundos...',
            'redirecting' => 'Redirigiendo...',
            'popup_body' => 'Completa la configuración de passkey en la ventana emergente. Si no se abrió, revisa el bloqueador de ventanas emergentes o pulsa abajo.',
            'popup_reopen' => 'Abrir de nuevo',
        ],
        'vault_open' => [
            'title' => '¿Abrir tu caja segura?',
            'body' => 'Maestrooo Identity se abrirá ahora. Aprueba tu Vault y volverás a la caja segura de tus compras.',
            'facts' => [
                'owner_only' => 'Solo el dueño de la cuenta puede ver los códigos.',
                'no_keys' => 'Meanly nunca recibe tus claves.',
            ],
            'cta' => 'Aprobar en Maestrooo Identity',
        ],
        'wallet_pay' => [
            'title' => '¿Pagar con tu wallet?',
            'body' => 'Maestrooo Identity se abrirá ahora. Aprueba el pago allí y vuelve al checkout.',
            'facts' => [
                'wallet_stays_private' => 'Tu saldo y operaciones permanecen en Maestrooo Identity.',
                'result_only' => 'Meanly recibe solo el resultado del pago.',
            ],
            'cta' => 'Continuar al pago',
        ],
        'identity_create' => [
            'title' => '¿Continuar con Maestrooo Identity?',
            'body' => 'Maestrooo Identity se abrirá ahora. Crea o confirma tu cuenta de Meanly allí y luego vuelve a Meanly.',
            'facts' => [
                'passkey_device' => 'Tu clave segura permanece en tu dispositivo.',
                'return_after' => 'Después de crear la cuenta, vuelves a Meanly.',
            ],
            'cta' => 'Continuar en Maestrooo Identity',
        ],
        'identity_confirm' => [
            'title' => '¿Entrar con Maestrooo Identity?',
            'body' => 'Maestrooo Identity se abrirá ahora. Aprueba tu cuenta y volverás a Meanly.',
            'facts' => [
                'no_password' => 'No necesitas contraseña.',
                'passkey_device' => 'Meanly recibe solo un resultado verificado.',
            ],
            'cta' => 'Entrar con Maestrooo Identity',
        ],
    ],
];
