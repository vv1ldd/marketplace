<?php
$files = [
    'resources/views/landing.blade.php',
    'resources/views/ops/kernel.blade.php',
    'resources/views/partner/register_step2_enroll.blade.php',
    'resources/views/partner/verify_handshake.blade.php',
    'resources/views/cabinet.blade.php',
    'resources/views/partner/dashboard.blade.php'
];

$find = "<body data-theme=\"{{ request()->\n@include('partials.theme-sync-body')cookie('theme') ?? 'consortium' }}\" @if(request()->cookie('holiday')) data-holiday=\"{{ request()->cookie('holiday') }}\" @endif>";
$replace = "@include('partials.theme-sync-body')\n<body data-theme=\"{{ request()->cookie('theme') ?? 'consortium' }}\" @if(request()->cookie('holiday')) data-holiday=\"{{ request()->cookie('holiday') }}\" @endif>";

foreach ($files as $f) {
    if (file_exists($f)) {
        $content = file_get_contents($f);
        if (strpos($content, "@include('partials.theme-sync-body')cookie('theme')") !== false) {
            $content = str_replace($find, $replace, $content);
            file_put_contents($f, $content);
            echo "Fixed $f\n";
        }
    }
}
