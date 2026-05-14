<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$url = 'https://github.com/xinntao/Real-ESRGAN-ncnn-vulkan/releases/download/v0.2.0/realesrgan-ncnn-vulkan-v0.2.0-macos.zip';
echo "Downloading via PHP Http facade...\n";
$response = Http::withHeaders([
    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
    'Accept' => 'application/octet-stream',
])->get($url);

if ($response->successful()) {
    file_put_contents(__DIR__ . '/../bin/realesrgan_php.zip', $response->body());
    echo "Success! Size: " . strlen($response->body()) . " bytes\n";
} else {
    echo "Failed! Status: " . $response->status() . "\n";
}
