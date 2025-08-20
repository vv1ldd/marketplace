<?php

namespace App\Http\Controllers;

use App\Http\Controllers\PlayStation\MainController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WooPriceUpdateController extends Controller
{
    public function update()
    {
        ini_set('max_execution_time', 1200);

        $log = Log::channel('update_woo_prices')->withContext([
            'log_id' => Str::random(8),
        ]);

        $log->info('-------------');
        $log->info('Запуск обновления цен');

        $controller = new MainController();

        $products = $controller->prices()->original;

        $log->info('Получены все цены', ['count' => count($products ?? [])]);

        if (empty($products)) {
            $log->error('Пустые данные');
            return response()->json(['error' => 'Пустые данные'], 404);
        }

        $updated = 0;

        $db_connection = DB::connection('ps_plus');

        try {
            foreach ($products as $item) {

                DB::beginTransaction();

                if (empty($item['sku']) || empty($item['base_price'])) {
//                    $log->debug('Нет sku или пустая цена', [$item]);
                    continue;
                }

                $sku = $item['sku'];
                $price = round($item['base_price'] / 100);
                $salePrice = ($item['price_with_discount'] / 100) < $price ? $item['price_with_discount'] / 100 : null;

                if ($salePrice) {
                    $salePrice = round($salePrice);
                }

                $newPrice = $salePrice && $salePrice < $price ? $salePrice : $price;

//                $log->debug('Обновление цены', [
//                    'sku' => $sku,
//                    'price' => $price,
//                    'sale_price' => $salePrice,
//                ]);

                // Находим товар по SKU
                $productId = $db_connection->table('wp_postmeta')
                    ->where('meta_key', '_sku')
                    ->where('meta_value', $sku)
                    ->value('post_id');

                if (!$productId) {
//                    $log->debug('Товар не найден по SKU', ['sku' => $sku]);
                    continue;
                }

//                $log->debug('Товар найден', ['product_id' => $productId]);

                // Получаем текущие значения
                $meta = $db_connection->table('wp_postmeta')
                    ->where('post_id', $productId)
                    ->whereIn('meta_key', ['_regular_price', '_price', '_sale_price'])
                    ->pluck('meta_value', 'meta_key');

                $oldRegular = $meta['_regular_price'] ?? null;
                $oldPrice = $meta['_price'] ?? null;
                $oldSale = $meta['_sale_price'] ?? null;

                // Проверяем изменения
                $needUpdate = false;
                if ($oldRegular != $price || $oldPrice != $newPrice) {
                    $needUpdate = true;
                }

                if (!$needUpdate) {
//                    $log->debug('Цена не изменилась, пропуск', [
//                        'product_id' => $productId,
//                        'sku' => $sku,
//                    ]);
                    continue;
                }

                // --- Обновляем только если изменилось ---
                $db_connection->table('wp_postmeta')->updateOrInsert(
                    ['post_id' => $productId, 'meta_key' => '_regular_price'],
                    ['meta_value' => $price]
                );

                $db_connection->table('wp_postmeta')->updateOrInsert(
                    ['post_id' => $productId, 'meta_key' => '_price'],
                    ['meta_value' => $newPrice]
                );

                if ($salePrice && $salePrice < $price) {

                    $log->debug('Цена со скидкой', [$salePrice]);

                    $db_connection->table('wp_postmeta')->updateOrInsert(
                        ['post_id' => $productId, 'meta_key' => '_sale_price'],
                        ['meta_value' => $salePrice]
                    );
                } else {
                    $db_connection->table('wp_postmeta')
                        ->where('post_id', $productId)
                        ->where('meta_key', '_sale_price')
                        ->delete();
                }

                $updated++;
                $log->info('Цена изменена', [
                    'product_id' => $productId,
                    'sku' => $sku,
                    'old' => compact('oldRegular', 'oldPrice', 'oldSale'),
                    'new' => compact('price', 'newPrice', 'salePrice'),
                ]);

                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $log->error('Ошибка обновления цен', ['exception' => $e->getMessage()]);
            return response()->json(['error' => 'Ошибка обновления', 'message' => $e->getMessage()], 500);
        }

        $log->info('Полное обновление цен завершено', ['updated' => $updated]);

        return response()->json([
            'status' => 'ok',
            'updated' => $updated,
            'message' => 'Полное обновление цен завершено'
        ]);
    }
}
