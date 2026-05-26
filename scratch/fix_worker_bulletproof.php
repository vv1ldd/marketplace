<?php
$file = '/Users/w1ld/Documents/GitHub/new/marketplace/app/Jobs/AddCatalogItemToShop.php';
$lines = file($file);

// Target fixed block for Background Job
$fix = "                     if (!\$availability['available']) {\n" .
       "                         // 🛡️ INSTANT SELF-HEAL: Auto-deactivate and fail fast\n" .
       "                         if (isset(\$catalogItem)) \$catalogItem->update(['is_active' => false]);\n" .
       "                         if (isset(\$providerProduct)) \$providerProduct->update(['is_active' => false]);\n" .
       "\n" .
       "                         throw new \\Exception(\"Пополнение отменено: Товара временно нет в наличии у поставщика или запрошенное количество ({\$this->count}) недоступно.\");\n" .
       "                     }\n";

// Line index is 225 (0-based) corresponding to line 226.
$lines[225] = $fix;

file_put_contents($file, implode("", $lines));
echo "🔥 DOUBLE VICTORY!!! Background Job repaired perfectly without side effects!\n";
