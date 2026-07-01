<?php

namespace App\Support;

class SupplyContour
{
    public static function usesKernelHttpCatalog(): bool
    {
        return filled(config('services.wildflow.kernel_url'))
            && (string) config('services.wildflow.kernel_mode', 'http') === 'http';
    }

    public static function isRemoteKernelConsumer(): bool
    {
        if ((bool) config('services.wildflow.force_direct_supply', false)) {
            return false;
        }

        return self::usesKernelHttpCatalog();
    }

    public static function isDirectSupplyAuthority(): bool
    {
        return ! self::isRemoteKernelConsumer();
    }

    public static function kernelMode(): string
    {
        return self::isRemoteKernelConsumer()
            ? 'remote_kernel_consumer'
            : 'direct_supply_authority';
    }

    public static function kernelAuthorityHost(): string
    {
        if (! self::isRemoteKernelConsumer()) {
            return 'meanly.one';
        }

        $host = parse_url((string) config('services.wildflow.kernel_url'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'api.meanly.one';
    }

    /**
     * @return array<string, mixed>
     */
    public static function kernelPayload(): array
    {
        if (self::isRemoteKernelConsumer()) {
            return [
                'mode' => self::kernelMode(),
                'authority' => self::kernelAuthorityHost(),
                'upstream' => 'meanly.one',
                'upstream_label' => 'Meanly ONE (global supply kernel)',
                'compatibility_host' => self::kernelAuthorityHost(),
                'ezpin_env_configured' => false,
                'compatibility_aliases' => ['wildflow catalog source pulls unified-catalog from ONE'],
                'support_planes' => [
                    'docs' => [
                        'catalog' => '/api/v1/providers/{provider}/unified-catalog',
                        'availability' => '/api/v1/providers/{provider}/check-availability/{sku}',
                        'orders' => '/api/v1/providers/{provider}/order',
                        'balance' => '/api/v1/partners/{partner}',
                    ],
                    'devices' => [
                        'terminals_total' => \App\Models\SellerTerminal::count(),
                        'terminals_active' => \App\Models\SellerTerminal::where('is_active', true)->count(),
                    ],
                ],
            ];
        }

        return [
            'mode' => self::kernelMode(),
            'authority' => 'meanly.one',
            'upstream' => 'ezpin+fazercards',
            'upstream_label' => 'EZPin + Fazer Cards',
            'compatibility_host' => 'api.meanly.one',
            'ezpin_env_configured' => filled(config('services.ezpin.client_id')) && filled(config('services.ezpin.secret_key')),
            'compatibility_aliases' => ['legacy provider records resolve to ezpin'],
            'support_planes' => [
                'docs' => [
                    'catalog' => '/api/v1/providers/{provider}/unified-catalog',
                    'availability' => '/api/v1/providers/{provider}/check-availability/{sku}',
                    'orders' => '/api/v1/providers/{provider}/order',
                    'balance' => '/api/v1/partners/{partner}',
                ],
                'devices' => [
                    'terminals_total' => \App\Models\SellerTerminal::count(),
                    'terminals_active' => \App\Models\SellerTerminal::where('is_active', true)->count(),
                ],
            ],
        ];
    }
}
