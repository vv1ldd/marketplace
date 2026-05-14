<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WildflowCatalog;
use Illuminate\Support\Str;

$items = WildflowCatalog::all();
$total = $items->count();
$anyContent = 0;
$noContent = 0;
$samples = [];

foreach ($items as $item) {
    $descRaw = data_get($item->data, 'product.description') ?? data_get($item->data, 'data.product.description');
    
    if (!$descRaw) {
        $noContent++;
        continue;
    }

    $descJson = is_array($descRaw) ? $descRaw : json_decode($descRaw, true);
    if (!$descJson) {
        $noContent++;
        continue;
    }

    $contents = $descJson['content'] ?? [];
    
    $fullText = '';
    $hasRedeem = false;
    foreach ($contents as $section) {
        $desc = strip_tags($section['description'] ?? '');
        if (!empty(trim($desc))) {
            $fullText .= " [" . ($section['title'] ?? 'Section') . "]: " . $desc;
        }
        if (($section['type'] ?? '') === 'redeem') {
            $hasRedeem = true;
        }
    }

    if (empty(trim($fullText))) {
        $noContent++;
    } else {
        $anyContent++;
        // Если инструкции НЕТ, но контент ЕСТЬ - берем в пример
        if (!$hasRedeem && count($samples) < 20) {
            $samples[$item->brand_name] = [
                'sku' => $item->sku,
                'content' => Str::limit(trim($fullText), 300)
            ];
        }
    }
}

echo "СТАТИСТИКА ПО КОНТЕНТУ:\n";
echo "-------------------\n";
echo "Всего товаров: $total\n";
echo "С любым описанием: $anyContent\n";
echo "Вообще без описания: $noContent\n\n";

echo "ПРИМЕРЫ ТОВАРОВ БЕЗ БЛОКА REDEEM, НО С ДРУГИМ КОНТЕНТОМ:\n";
echo "-------------------\n";
foreach ($samples as $brand => $data) {
    echo "[$brand] ({$data['sku']}):\n";
    echo "   > " . $data['content'] . "\n\n";
}
