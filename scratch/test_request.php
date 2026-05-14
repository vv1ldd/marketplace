<?php

use App\Models\Seller;
use Illuminate\Support\Facades\Auth;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$seller = Seller::first();
Auth::guard('sellers')->login($seller);

$request = Illuminate\Http\Request::create('/partner/', 'GET');
$response = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);

echo "Status: " . $response->getStatusCode() . "\n";
echo "Redirect: " . $response->headers->get('Location') . "\n";
