<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WildflowCatalog;
use Illuminate\Support\Str;

function getRedeemInstructions($itemData) {
    // Возможные пути к описанию
    $paths = [
        'product.description',
        'data.product.description',
        'data.description'
    ];

    $descRaw = null;
    foreach ($paths as $path) {
        $descRaw = data_get($itemData, $path);
        if ($descRaw) break;
    }

    if (!$descRaw) return null;

    $descJson = is_array($descRaw) ? $descRaw : json_decode($descRaw, true);
    if (!$descJson) return null;

    $contents = $descJson['content'] ?? [];
    
    foreach ($contents as $section) {
        $type = $section['type'] ?? '';
        $title = $section['title'] ?? '';
        if ($type === 'redeem' || stripos($title, 'Redemption') !== false) {
            $text = $section['description'] ?? '';
            // Декодируем HTML-сущности и чистим теги
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
            $text = strip_tags($text);
            return trim($text);
        }
    }

    return null;
}

$items = WildflowCatalog::all();
$total = $items->count();
$stats = [
    'has_instructions' => 0,
    'no_instructions' => 0,
];

$byBrand = [];

foreach ($items as $item) {
    $instruction = getRedeemInstructions($item->data);
    
    if ($instruction) {
        $stats['has_instructions']++;
        $brand = $item->brand_name;
        if (!isset($byBrand[$brand])) {
            $byBrand[$brand] = $instruction;
        }
    } else {
        $stats['no_instructions']++;
    }
}

echo "ИТОГОВАЯ ПРОВЕРКА ИНСТРУКЦИЙ:\n";
echo "---------------------------\n";
echo "Всего товаров: $total\n";
echo "С инструкциями: {$stats['has_instructions']} (" . round($stats['has_instructions']/$total*100, 1) . "%)\n";
echo "Без инструкций: {$stats['no_instructions']}\n\n";

echo "ПРИМЕРЫ ОЧИЩЕННЫХ ИНСТРУКЦИЙ:\n";
echo "---------------------------\n";
foreach (array_slice($byBrand, 0, 15) as $brand => $text) {
    echo "[$brand]:\n";
    echo "   " . Str::limit($text, 250) . "\n\n";
}
