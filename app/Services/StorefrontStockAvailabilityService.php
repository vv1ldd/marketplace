<?php

namespace App\Services;

use App\Models\WildflowCatalog;
use Illuminate\Support\Facades\DB;

class StorefrontStockAvailabilityService
{
    public function check(
        WildflowCatalog $catalogItem,
        int $quantity = 1,
        ?float $price = null,
        ?string $terminalId = null
    ): array {
        $quantity = max(1, $quantity);
        $serviceSku = $this->serviceSku($catalogItem);
        $localAvailable = $this->localVoucherCount($serviceSku);

        if ($localAvailable >= $quantity) {
            return [
                'available' => true,
                'source' => 'local_vouchers',
                'service_sku' => $serviceSku,
                'local_available' => $localAvailable,
            ];
        }

        $providerAvailability = app(WildflowService::class)->checkAvailability(
            service_sku: $serviceSku,
            quantity: $quantity,
            price: $price,
            terminalId: $terminalId
        );

        if ($providerAvailability['available'] ?? false) {
            return [
                'available' => true,
                'source' => 'provider',
                'service_sku' => $serviceSku,
                'local_available' => $localAvailable,
                'provider' => $providerAvailability,
            ];
        }

        $error = (string) ($providerAvailability['error'] ?? data_get($providerAvailability, 'raw.message') ?? '');

        return [
            'available' => false,
            'source' => $this->looksLikeProviderAuthFailure($error) ? 'provider_auth_failed' : 'provider',
            'service_sku' => $serviceSku,
            'local_available' => $localAvailable,
            'provider' => $providerAvailability,
            'error' => $this->looksLikeProviderAuthFailure($error)
                ? 'Провайдер не подтвердил сток из-за ошибки авторизации upstream API. Это не означает, что товара нет в наличии.'
                : 'Товар временно нет в наличии у поставщика или запрошенное количество недоступно.',
        ];
    }

    private function serviceSku(WildflowCatalog $catalogItem): string
    {
        return (string) app(VaultTransitService::class)->decrypt($catalogItem->service_sku);
    }

    private function localVoucherCount(string $serviceSku): int
    {
        try {
            return (int) DB::table('api_wildflow_dev.local_vouchers')
                ->where('service_sku', $serviceSku)
                ->where('is_used', false)
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function looksLikeProviderAuthFailure(string $error): bool
    {
        $error = strtolower($error);

        return str_contains($error, '401')
            || str_contains($error, 'unauthorized')
            || str_contains($error, 'token_not_valid')
            || str_contains($error, 'signature has expi');
    }
}
