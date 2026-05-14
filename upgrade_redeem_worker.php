<?php
$file = '/Users/w1ld/Documents/GitHub/new/marketplace/app/Jobs/ProcessRedeemWildflowPurchase.php';
$c = file_get_contents($file);

$oldBlock = "            sleep(1);\n" .
            "\n" .
            "            // 2. Get Cards/Codes\n" .
            "            \$codes = \$driver->getCodes(\$externalOrderId);\n" .
            "            \$original_code = !empty(\$codes) ? \$codes[0] : null;";

$newBlock = "            // 2. Poll for Cards (Adaptive Strategy to combat async delay)\n" .
            "            \$codes = [];\n" .
            "            for (\$attempt = 1; \$attempt <= 4; \$attempt++) {\n" .
            "                sleep(2); // Give provider space to breathe\n" .
            "                \$codes = \$driver->getCodes(\$externalOrderId);\n" .
            "                if (!empty(\$codes)) {\n" .
            "                    break;\n" .
            "                }\n" .
            "                \\Illuminate\\Support\\Facades\\Log::info(\"Redeem Polling attempt {\$attempt}/4 gave no codes yet for order {\$externalOrderId}. Waiting...\");\n" .
            "            }\n" .
            "            \$original_code = !empty(\$codes) ? \$codes[0] : null;";

if (strpos($c, $oldBlock) !== false) {
    file_put_contents($file, str_replace($oldBlock, $newBlock, $c));
    echo "✅ ROBOT UPDATED SUCCESSFULLY WITH ADAPTIVE POLLING!\n";
} else {
    echo "❌ Regex failed. Trying fall back substitution...\n";
    // Simple substitute by line matching for extra robustness
    $lines = file($file);
    // We know line 140 is sleep(1). Index 139.
    if (strpos($lines[139], 'sleep(1);') !== false) {
         $lines[139] = "";
         $lines[140] = "";
         $lines[141] = "";
         $lines[142] = $newBlock . "\n";
         $lines[143] = "";
         file_put_contents($file, implode("", array_filter($lines)));
         echo "✅ RECOVERED BY LINE REPLACEMENT!\n";
    } else {
         echo "☠️ Total fail. Manual inspection needed.";
    }
}
