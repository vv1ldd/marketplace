<?php

namespace App\Console\Commands;

use App\Helpers\GenerateSecureCode;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Shop;
use App\Models\WildflowCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Random\RandomException;

/**
 * Два тестовых кода для /redeem: провайдер 1 (Wildflow / ваучер) и провайдер 2 (PlayStation / форма PSN).
 */
class IssueRedeemDemoVouchersCommand extends Command
{
    public const PS_SAMPLE_SKU = 'DEV-SAMPLE-PS-REDEEM-1';

    protected $signature = 'dev:issue-redeem-demos
                            {--shop= : ID магазина (по умолчанию первый shops)}';

    protected $description = 'Создать 2 тестовых заказа: Wildflow (provider 1) и PlayStation (provider 2) для проверки redeem';

    public function handle(): int
    {
        $shopId = $this->option('shop');
        $shop = $shopId ? Shop::query()->find((int) $shopId) : Shop::query()->first();
        if (! $shop) {
            $this->error('Магазин не найден. Укажите --shop=<id>.');

            return self::FAILURE;
        }

        $wildflowSku = Product::query()
            ->where('provider_id', 1)
            ->where('sku', 'like', 'VOUCHER-%')
            ->value('sku')
            ?? WildflowCatalog::query()->value('sku');

        if (! $wildflowSku) {
            $this->error('Нет SKU Wildflow (VOUCHER- в products или строка в wildflow_catalogs).');

            return self::FAILURE;
        }

        $psProvider = Provider::query()->firstOrCreate(
            ['type' => 'playstation'],
            ['name' => 'PlayStation', 'is_active' => true]
        );

        $psProduct = Product::query()->firstOrCreate(
            ['sku' => self::PS_SAMPLE_SKU],
            [
                'name' => '[DEV] Sample PlayStation redeem',
                'provider_id' => $psProvider->id,
                'type' => 'playstation',
                'type_form_id' => 1,
                'is_manual' => true,
                'is_active' => true,
                'price_rub' => 100,
            ]
        );

        $wfProduct = Product::queryByOfferSku($wildflowSku)->first();

        $rows = [];
        try {
            $rows[] = $this->issueRow(
                $shop,
                $wildflowSku,
                'Wildflow (provider_id='.(string) ($wfProduct?->provider_id ?? 1).', sku из каталога)',
                $this->inferTypeFormId($wildflowSku, $wfProduct)
            );
            $rows[] = $this->issueRow(
                $shop,
                $psProduct->sku,
                'PlayStation (providers.id='.$psProvider->id.', type=playstation)',
                (int) ($psProduct->type_form_id ?? 1)
            );
        } catch (RandomException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(['Поток', 'SKU', 'Код redeem', 'order_id (внеш.)', 'orders.id'], $rows);
        $this->newLine();
        $this->line('Откройте /redeem с префиксом магазина (?shop=...) при необходимости.');

        return self::SUCCESS;
    }

    private function inferTypeFormId(string $sku, ?Product $product): int
    {
        if ($product && $product->type_form_id !== null) {
            return (int) $product->type_form_id;
        }

        return str_starts_with($sku, 'VOUCHER-') ? 2 : 1;
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string, 4: int}
     */
    private function issueRow(Shop $shop, string $sku, string $label, int $typeFormId): array
    {
        $key = strtoupper(preg_replace('/\s+/', '', GenerateSecureCode::generate($shop->voucher_prefix)));
        $externalOrderId = 'DEV-REDEEM-'.strtoupper(Str::random(6)).'-'.now()->format('His');

        $order = DB::transaction(function () use ($shop, $sku, $key, $externalOrderId, $typeFormId, $label) {
            $order = Order::create([
                'order_id' => $externalOrderId,
                'uuid' => (string) Str::uuid(),
                'info' => [
                    'source' => 'dev:issue-redeem-demos',
                    'label' => $label,
                    'sku' => $sku,
                    'dev_async_redeem_demo' => true,
                ],
                'client_info' => ['email' => 'redeem-demo@example.com', 'firstName' => 'Redeem'],
                'shop_id' => $shop->id,
                'is_test' => false,
                'progress_id' => 1,
                'status' => 'NEW',
            ]);

            OrderItems::create([
                'uuid' => (string) Str::uuid(),
                'key' => $key,
                'order_id' => $order->id,
                'sku' => $sku,
                'count' => 1,
                'activate_till' => now()->addYear()->toDateString(),
                'is_redeemed' => false,
                'is_activated' => false,
                'type_form_id' => $typeFormId,
                'purchase_status' => 'none',
            ]);

            return $order;
        });

        return [$label, $sku, $key, $externalOrderId, $order->id];
    }
}
