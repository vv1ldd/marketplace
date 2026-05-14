<?php
/**
 * Массовое заполнение brand_id в provider_products.
 * Проходит по всем продуктам без brand_id и запускает MappingService::resolveBrand.
 */
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProviderProduct;
use App\Services\MappingService;

echo "=== Массовое заполнение brand_id ===" . PHP_EOL;

$total = 0;
$resolved = 0;
$skipped = 0;

// Обрабатываем батчами по 500
ProviderProduct::whereNull('brand_id')
    ->orderBy('id')
    ->chunk(500, function ($products) use (&$total, &$resolved, &$skipped) {
        foreach ($products as $product) {
            $total++;

            // Берём название бренда из category (для Fazer) или из name (для Wildflow)
            $externalName = $product->category ?: null;
            $title = $product->name;

            $brandId = MappingService::resolveBrand(
                $product->provider_id,
                $externalName,
                $product->sku,
                $title
            );

            if ($brandId) {
                $product->update(['brand_id' => $brandId]);
                $resolved++;
            } else {
                $skipped++;
            }
        }

        echo "Обработано: {$total} | Разрешено: {$resolved} | Пропущено: {$skipped}\r";
    });

echo PHP_EOL . "=== Готово ===" . PHP_EOL;
echo "Всего обработано: {$total}" . PHP_EOL;
echo "brand_id заполнен: {$resolved}" . PHP_EOL;
echo "Без brand_id осталось: {$skipped}" . PHP_EOL;

// Показываем топ-10 брендов, которые появились
echo PHP_EOL . "=== Топ брендов ===" . PHP_EOL;
$top = \Illuminate\Support\Facades\DB::table('provider_products')
    ->join('brands', 'brands.id', '=', 'provider_products.brand_id')
    ->selectRaw('brands.name, count(*) as cnt')
    ->whereNotNull('provider_products.brand_id')
    ->groupBy('brands.name')
    ->orderByDesc('cnt')
    ->limit(15)
    ->get();

foreach ($top as $row) {
    echo "  {$row->name}: {$row->cnt}" . PHP_EOL;
}
