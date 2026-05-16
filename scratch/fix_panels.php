<?php
 
$dir = 'app/Providers/Filament/';
$files = glob($dir . '*PanelProvider.php');
 
foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // 1. Remove the semicolon before ->font
    $content = preg_replace('/->spa\(false\);\s+->font\(/', '->spa(false)->font(', $content);
    $content = preg_replace('/isPersistent: true\)\s+->font\(/', 'isPersistent: true)->font(', $content);
    $content = preg_replace('/Authenticate::class,\s+\]\)\s+->font\(/', "Authenticate::class,\n            ])->font(", $content);
    
    // 2. Fix the malformed return at the end
    // Find the renderHook block end and fix what follows
    $content = preg_replace('/}\)\s+\)\s+&\(\$panel/', "})\n            );\n\n        return FilamentPanelDomain::apply(\$panel", $content);
    
    // 3. General cleanup of double arrows or semicolons introduced by bad sed
    $content = str_replace('; ->font', '->font', $content);
    
    file_put_contents($file, $content);
    echo "Fixed $file\n";
}
