<?php

$dir = new RecursiveDirectoryIterator(__DIR__ . '/../resources/views');
$iter = new RecursiveIteratorIterator($dir);

foreach ($iter as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        
        // Skip emails and errors if we want, but let's just do all that have <head>
        if (strpos($file->getPathname(), 'emails/') !== false) continue;
        
        if (strpos($content, '<head>') !== false && strpos($content, "@include('partials.theme-sync')") === false) {
            
            // If it has the old script, replace it
            $content = preg_replace('/<script>\s*\(function\(\) \{\s*const savedTheme = localStorage.*?\}\)\(\);\s*<\/script>\s*/s', '', $content);
            
            $content = str_replace('<head>', "<head>\n    @include('partials.theme-sync')", $content);
            file_put_contents($file->getPathname(), $content);
            echo "Updated: " . $file->getPathname() . "\n";
        }
    }
}
