<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WildflowCatalog;
use Illuminate\Support\Str;

function extractActivationUrl($text) {
    if (empty($text)) return null;

    // Ищем ссылки, которые часто встречаются в инструкциях
    $patterns = [
        '/(https?:\/\/[^\s\n\r]+)/i',
        '/\b([a-z0-9]+\.[a-z0-9]+\/(?:redeem|setup|activate|account|entry)[^\s\n\r]*)/i', // без http
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $url = $matches[1];
            // Чистим от точек в конце предложения
            return rtrim($url, '.');
        }
    }
    return null;
}

function getRedeemData($itemData) {
    $paths = ['product.description', 'data.product.description', 'data.description'];
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
            $text = html_entity_decode($section['description'] ?? '', ENT_QUOTES | ENT_HTML5);
            $text = strip_tags($text);
            return trim($text);
        }
    }
    return null;
}

$items = WildflowCatalog::all();
$byBrand = [];

foreach ($items as $item) {
    $instruction = getRedeemData($item->data);
    $brand = $item->brand_name;
    
    if ($instruction && !isset($byBrand[$brand])) {
        $url = extractActivationUrl($instruction);
        $byBrand[$brand] = [
            'url' => $url,
            'text' => Str::limit($instruction, 100)
        ];
    }
}

echo "АНАЛИЗ ССЫЛОК АКТИВАЦИИ ПО БРЕНДАМ:\n";
echo "-----------------------------------\n";

foreach ($byBrand as $brand => $data) {
    echo "[$brand]:\n";
    echo "   URL: " . ($data['url'] ?: '--- не найден ---') . "\n";
    echo "   TEXT: " . $data['text'] . "\n\n";
}
