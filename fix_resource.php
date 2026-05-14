<?php
$f = '/Users/w1ld/Documents/GitHub/new/marketplace/app/Filament/Partner/Resources/ProviderProductResource.php';
$c = file_get_contents($f);

// Find the broken string literally
$broken = 'if (!$availability[\'available\']) {\n                                 // 🛡️ INSTANT SELF-HEAL\n                                 $wf->update([\'is_active\' => false]);\n                                 $record->update([\'is_active\' => false]);\n\n                                 Notification::make()\n                                     ->title(\'🚫 Нет в наличии\')\n                                     ->body(\'Товара временно нет в наличии у поставщика. Он автоматически скрыт.\')\n                                     ->danger()\n                                     ->persistent()\n                                     ->send();\n                                 return;\n                             }';

// The clean replacement
$clean = 'if (!$availability[\'available\']) {
                                 // 🛡️ INSTANT SELF-HEAL
                                 $wf->update([\'is_active\' => false]);
                                 $record->update([\'is_active\' => false]);

                                 Notification::make()
                                     ->title(\'🚫 Нет в наличии\')
                                     ->body(\'Товара временно нет в наличии у поставщика. Он автоматически скрыт.\')
                                     ->danger()
                                     ->persistent()
                                     ->send();
                                 return;
                             }';

if (strpos($c, $broken) !== false) {
    $newC = str_replace($broken, $clean, $c);
    file_put_contents($f, $newC);
    echo "✅ SUCCESS: Resource repaired perfectly!\n";
} else {
    echo "❌ FAILED: The literal string '{$broken}' wasn't found in the file!\n";
    
    // Backup attempt: just replace the single literal line via simple regex that maps backslash n
    echo "🔍 Trying Regex approach on broken content...\n";
    $newC = preg_replace('/if\\s*\\(!\\\$availability\\[\\'available\\'\\]\\)\\{\\\\n.*?\\\\n\\s*\\}/is', $clean, $c, 1, $count);
    if ($count > 0) {
        file_put_contents($f, $newC);
        echo "🏆 WIN: Regex caught it and saved the day!\n";
    } else {
         echo "☠️ Absolute fail! Need to inspect the exact byte sequence!\n";
    }
}
