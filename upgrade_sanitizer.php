<?php
$file = '/Users/w1ld/Documents/GitHub/new/marketplace/app/Jobs/ProcessRedeemWildflowPurchase.php';
$c = file_get_contents($file);

// Defining the SUPER CLEAN Humanizer
$newHumanize = '    private function humanizeWildflowError(string $internalSnippet): string
    {
        // Pre-clean snippet from nasty backslashes for easier matching
        $clean = str_replace("\\\\\"", "\"", $internalSnippet);

        // 1. Critical mapping: Lack of Balance!
        if (str_contains($clean, \'"code":"610"\') || stripos($clean, \'balance is not sufficient\') !== false || stripos($clean, \'Insufficient balance\') !== false) {
            return \'У поставщика временно закончились лимиты для выдачи этого товара\';
        }

        // 2. Recognized error code mappings
        if (str_contains($clean, \'"code":"635"\') || stripos($clean, \'Not enough cards available\') !== false) {
            return \'У поставщика закончился сток (карты данного типа)\';
        }
        if (str_contains($clean, \'"code":"612"\') || stripos($clean, \'Product price is incorrect\') !== false) {
            return \'Цена товара изменилась у поставщика\';
        }
        if (str_contains($clean, \'"code":"602"\') || stripos($clean, \'Product is not available\') !== false) {
            return \'Товар временно недоступен у поставщика\';
        }

        // 3. Intelligent extraction of "detail" message using simpler substring logic
        if (stripos($clean, \'"detail":"\') !== false) {
            $parts = explode(\'"detail":"\', $clean);
            $end = explode(\'"\', $parts[1]);
            if (!empty($end[0])) {
                return \'Провайдер сообщает: \' . trim($end[0]);
            }
        }

        // 4. Fallback to generic but clean message
        return \'Временная техническая недоступность провайдера\';
    }';

// Find current function position
$startPos = strpos($c, 'private function humanizeWildflowError(');
$endPos = strpos($c, 'return null;', $startPos);
// Include last brace
$endPos = strpos($c, '}', $endPos);

if ($startPos !== false && $endPos !== false) {
    $c = substr_replace($c, $newHumanize, $startPos, ($endPos + 1) - $startPos);
}

// Update the saving of purchase_error to use CLEAN VARIABLE
$c = str_replace('\'purchase_error\' => Str::limit($internalSnippet, 250),', '\'purchase_error\' => $humanError, // Filtered for Seller eyes', $c);

// Clean up comment body to drop "internal: " json output
$oldCommentLine = '\'comment\' => $commentBody . ($humanError ? \' | Ошибка: \' . $humanError : \'\') . \' | internal: \' . Str::limit($internalSnippet, 500),';
$newCommentLine = '\'comment\' => $commentBody . \' | Ошибка: \' . $humanError,';

// Attempt direct replacement, or regex for wider compatibility
if (strpos($c, $oldCommentLine) !== false) {
    $c = str_replace($oldCommentLine, $newCommentLine, $c);
} else {
    // Fallback to simpler match
    $c = preg_replace("/'comment' => \\\$commentBody .*?Str::limit\(.*?500\),/", "'comment' => \$commentBody . ' | Ошибка: ' . \$humanError,", $c);
}

file_put_contents($file, $c);
echo "✅ ROBOT CLEANER DEPLOYED SUCCESSFULLY WITH ROBUST EXTRACTION!";
