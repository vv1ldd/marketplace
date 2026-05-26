<?php

$dir = new RecursiveDirectoryIterator(__DIR__ . '/../resources/views');
$iter = new RecursiveIteratorIterator($dir);

$INCLUDE = "@include('partials.theme-sync-body')";

foreach ($iter as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') continue;
    $path = $file->getPathname();

    // Skip emails, errors, and the partials themselves
    if (strpos($path, '/emails/') !== false) continue;
    if (strpos($path, '/partials/') !== false) continue;

    $content = file_get_contents($path);

    // Only process files with a <body tag
    if (!preg_match('/<body[^>]*>/', $content)) continue;

    // Skip if already injected
    if (strpos($content, 'theme-sync-body') !== false) continue;

    // Inject include right after the opening <body ...> tag
    $new = preg_replace('/(<body[^>]*>)/', "$1\n$INCLUDE", $content, 1);

    if ($new !== $content) {
        file_put_contents($path, $new);
        echo "Injected: $path\n";
    }
}
echo "Done.\n";
