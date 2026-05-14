<?php
$file = '/Users/w1ld/Documents/GitHub/new/marketplace/app/Filament/Partner/Resources/ProviderProductResource.php';
$lines = file($file);

// Target fixed block
$fix = "                             if (!\$availability['available']) {\n" .
       "                                 // 🛡️ INSTANT SELF-HEAL\n" .
       "                                 \$wf->update(['is_active' => false]);\n" .
       "                                 \$record->update(['is_active' => false]);\n" .
       "\n" .
       "                                 Notification::make()\n" .
       "                                     ->title('🚫 Нет в наличии')\n" .
       "                                     ->body('Товара временно нет в наличии у поставщика. Он автоматически скрыт.')\n" .
       "                                     ->danger()\n" .
       "                                     ->persistent()\n" .
       "                                     ->send();\n" .
       "                                 return;\n" .
       "                             }\n";

// We saw in the previous view file that line 509 was the broken line.
// Line index is 508 (0-based).
$lines[508] = $fix;

file_put_contents($file, implode("", $lines));
echo "🎉 VICTORY!!! File overwritten line by line! NO ROOM FOR ESCAPE ERRORS NOW!\n";
