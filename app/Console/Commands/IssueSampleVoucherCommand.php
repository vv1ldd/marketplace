<?php

namespace App\Console\Commands;

use App\Helpers\GenerateSecureCode;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\Product;
use App\Models\Shop;
use App\Models\WildflowCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Random\RandomException;

class IssueSampleVoucherCommand extends Command
{
    protected $signature = 'dev:issue-sample-voucher
                            {--shop= : ID магазина (по умолчанию первый в таблице shops)}
                            {--sku= : SKU позиции}
                            {--provider= : provider_id товара (1=Wildflow, 2=PlayStation и т.д. — берётся первый product этого провайдера)}
                            {--instant : Мгновенный GIFTCARD_EXAMPLE в том же запросе (старое dev_simulation)}
                            {--no-test : Реальный Wildflow (деньги/лимиты), без dev-режимов}
                            {--demo-only : Демо /redeem без wildflow_catalogs: фиктивный SKU, только dev_simulation или async-demo}';

    protected $description = 'Создать тестовый заказ с одной позицией и вывести код для страницы /redeem';

    public function handle(): int
    {
        $shopId = $this->option('shop');
        $shop = $shopId ? Shop::query()->find((int) $shopId) : Shop::query()->first();
        if (! $shop) {
            $this->error('Магазин не найден. Создайте запись в shops или укажите --shop=<id>.');

            return self::FAILURE;
        }

        $demoOnly = (bool) $this->option('demo-only');
        if ($demoOnly && $this->option('no-test')) {
            $this->error('Нельзя одновременно --demo-only и --no-test.');

            return self::FAILURE;
        }

        $sku = $this->option('sku');
        $providerId = $this->option('provider');
        $product = null;

        if ($demoOnly) {
            $sku = 'REDEEM-DEMO-'.strtoupper(Str::random(10));
        } elseif ($sku) {
            $product = Product::queryByOfferSku($sku)->first();
        } elseif ($providerId !== null && $providerId !== '') {
            $product = Product::query()
                ->where('provider_id', (int) $providerId)
                ->orderBy('id')
                ->first();
            $sku = $product?->sku;
        } else {
            $product = null;
            $sku = Product::query()->where('sku', 'like', 'VOUCHER-%')->value('sku')
                ?? WildflowCatalog::query()->value('sku');
        }

        if (! $sku) {
            $this->error('Не удалось подобрать SKU. Укажите --sku=... или --provider=1|2, либо --demo-only, либо выполните php artisan dev:issue-redeem-demos');

            return self::FAILURE;
        }

        if (! $demoOnly && ! isset($product)) {
            $product = Product::queryByOfferSku($sku)->first();
        }

        $liveWildflow = ! $demoOnly && (bool) $this->option('no-test');
        $instantSimulation = (bool) $this->option('instant');
        if ($liveWildflow && $instantSimulation) {
            $this->error('Нельзя одновременно --no-test и --instant.');

            return self::FAILURE;
        }
        if ($liveWildflow && ! WildflowCatalog::findForOrderOfferSku($sku)) {
            $this->error('С флагом --no-test SKU должен существовать в wildflow_catalogs (иначе автозакуп не запустится).');

            return self::FAILURE;
        }
        if ($liveWildflow) {
            $this->warn('ВНИМАНИЕ: --no-test — при активации будет реальный вызов Wildflow (списание/заказ кода).');
        }

        try {
            $key = GenerateSecureCode::generate($shop->voucher_prefix);
        } catch (RandomException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $key = strtoupper(preg_replace('/\s+/', '', $key));
        $externalOrderId = 'DEV-SAMPLE-'.now()->format('YmdHis').'-'.random_int(1000, 9999);

        if ($product && $product->type_form_id !== null) {
            $typeFormId = (int) $product->type_form_id;
        } else {
            $typeFormId = str_starts_with((string) $sku, 'VOUCHER-') ? 2 : 1;
        }

        $order = DB::transaction(function () use ($shop, $sku, $key, $externalOrderId, $typeFormId, $liveWildflow, $instantSimulation) {
            $info = [
                'source' => 'dev:issue-sample-voucher',
                'sku' => $sku,
            ];
            if ($liveWildflow) {
                // без dev-флагов
            } elseif ($instantSimulation) {
                $info['dev_simulation'] = true;
            } else {
                $info['dev_async_redeem_demo'] = true;
            }

            $order = Order::create([
                'order_id' => $externalOrderId,
                'uuid' => (string) Str::uuid(),
                'info' => $info,
                'client_info' => ['email' => 'sample@example.com', 'firstName' => 'Sample'],
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

        $this->newLine();
        $this->info('Готово. Код для ввода на /redeem:');
        $this->line('  '.$key);
        $this->newLine();
        $this->line('Магазин: '.$shop->name.' (id '.$shop->id.')');
        $this->line('SKU: '.$sku);
        if ($product) {
            $this->line('Product id: '.$product->id.'; provider_id: '.(string) ($product->provider_id ?? '—'));
        }
        $this->line('type_form_id позиции: '.$typeFormId);
        if ($demoOnly) {
            $this->line('Режим: --demo-only — SKU не в wildflow_catalogs, Wildflow не вызывается.');
        } elseif ($liveWildflow) {
            $this->line('Режим: реальный Wildflow (--no-test).');
        } elseif ($instantSimulation) {
            $this->line('Режим: --instant — GIFTCARD_EXAMPLE сразу после формы, без очереди.');
        } else {
            $this->line('Режим: как прод (pending + опрос / письмо); код DEMO-REDEEM-* без Wildflow; на /redeem job после ответа — без отдельного queue:work.');
        }
        $this->line('Внутренний id заказа: '.$order->id);
        $this->line('Внешний order_id: '.$externalOrderId);
        $this->newLine();

        return self::SUCCESS;
    }
}
