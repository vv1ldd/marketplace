<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Brand;
use App\Models\CatalogGroup;
use App\Models\ProviderCategoryMapping;
use App\Models\Product;
use App\Models\ProviderProduct;

echo "--- Начинаем аудит маппингов ---\n\n";

// 1. Создаем соответствие ключевых слов и групп
$groupKeywords = [
    'Игры' => ['playstation', 'psn', 'xbox', 'nintendo', 'steam', 'roblox', 'riot', 'valorant', 'ea sports', 'fifa', 'pubg', 'fortnite', 'free fire', 'razer', 'blizzard', 'battlenet', 'mobile legends', 'genshin', 'minecraft', 'electronic arts', 'ubisoft', 'gamestop', 'activision'],
    'Подписки' => ['netflix', 'spotify', 'tinder', 'bumble', 'crunchyroll', 'disney', 'hulu', 'paramount', 'anghami', 'deezer', 'apple music', 'tidal'],
    'Пополнение счета' => ['apple', 'itunes', 'google play', 'amazon', 'ebay', 'walmart', 'target', 'best buy', 'visa', 'mastercard', 'american express', 'huawei', 'skrill', 'payoneer'],
    'Софт' => ['meta', 'oculus', 'microsoft office', 'adobe', 'skype', 'norton', 'mcafee', 'kaspersky', 'bitdefender', 'discord', 'skype', 'telegram'],
    'Ритейл' => ['sephora', 'nike', 'adidas', 'ikea', 'zara', 'h&m', 'shein', 'carrefour', 'starbucks', 'uber', 'deliveroo', 'talabat', 'zomato', 'bath & body', 'lobster', 'barnes', 'steaks', 'subway', 'mcdonald'],
];

$groups = CatalogGroup::all()->keyBy('name');

if ($groups->isEmpty()) {
    echo "Ошибка: Группы не найдены. Сначала запустите сидер групп.\n";
    exit;
}

$mappedCount = 0;
$unmappedBrands = [];

echo "1. Маппинг Брендов -> Группы...\n";
Brand::chunk(100, function ($brands) use ($groupKeywords, $groups, &$mappedCount, &$unmappedBrands) {
    foreach ($brands as $brand) {
        if ($brand->catalog_group_id) continue;

        $foundGroup = null;
        $name = mb_strtolower($brand->name);

        foreach ($groupKeywords as $groupName => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($name, $kw)) {
                    $foundGroup = $groups->get($groupName);
                    break 2;
                }
            }
        }

        if ($foundGroup) {
            $brand->update(['catalog_group_id' => $foundGroup->id]);
            $mappedCount++;
            echo "   [OK] Бренд '{$brand->name}' -> '{$foundGroup->name}'\n";
        } else {
            $unmappedBrands[] = $brand->name;
        }
    }
});

echo "\nИтог по брендам: Смаплено новых: {$mappedCount}. Осталось без группы: " . count($unmappedBrands) . "\n";

echo "\n2. Аудит маппинга категорий провайдеров...\n";
$mappings = ProviderCategoryMapping::with('catalogGroup')->get();
echo "   Всего маппингов категорий: " . $mappings->count() . "\n";
foreach ($mappings as $m) {
    echo "   - [{$m->provider->name}] '{$m->provider_category_name}' -> '{$m->catalogGroup->name}'\n";
}

echo "\n3. Проверка суб-брендов (ProviderBrandMapping)...\n";
$brandMappings = \App\Models\ProviderBrandMapping::with('brand')->limit(10)->get();
foreach ($brandMappings as $bm) {
    echo "   - '{$bm->external_name}' -> '{$bm->brand->name}'\n";
}

echo "\n--- Аудит завершен ---\n";
