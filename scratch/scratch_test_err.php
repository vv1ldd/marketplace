<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProviderProduct;
use App\Models\WildflowCatalog;
use App\Models\Shop;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\ProductInventory;
use App\Services\FinanceService;
use App\Services\StandardizationService;
use App\Jobs\AddCatalogItemToShop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

try {
    $record = ProviderProduct::first();
    if (!$record) throw new Exception("No provider product");
    
    $shop = Shop::where('is_active', true)->first();
    if (!$shop) throw new Exception("No shop");

    $wf = WildflowCatalog::where('sku', $record->market_sku)->first();
    if (!$wf) throw new Exception("No WF");

    $data = ['amount' => 10, 'quantity' => 1, 'shop_id' => $shop->id];

    DB::beginTransaction();
    
    $legalEntity = $shop->legalEntity;
    echo "Legal entity loaded: " . get_class($legalEntity) . "\n";

    (new AddCatalogItemToShop(
        catalogItemId: $wf->id,
        shopId: $shop->id,
        sellerId: 1,
        count: 0
    ))->handle();
    
    echo "AddCatalogItemToShop finished synchronously.\n";

    $legalEntity->decrement('available_balance', 1.0);
    
    echo "Decrement finished.\n";

    $order = Order::create([
        'order_id'     => 'TEST-' . Str::random(5),
        'uuid'         => Str::uuid()->toString(),
        'status'       => 'PROCESSING',
        'shop_id'      => $shop->id,
        'sales_channel'=> 'manual',
    ]);

    echo "Order created.\n";

    app(\App\Services\LedgerService::class)->record($shop, 'FINANCE_CAPTURE', $order, [
        'amount_rub'  => 100,
        'reference'   => 'TEST-123',
    ]);
    echo "Finance Capture Ledger Recorded.\n";

    $item = OrderItems::create([
        'key' => 'TEST-KEY',
        'uuid' => Str::uuid()->toString(),
        'order_id' => $order->id,
        'activate_till' => now()->addYear()->format('Y-m-d'),
        'sku' => 'TEST-SKU',
        'count' => 1,
        'price_rub' => 10000,
        'price_try' => 0,
    ]);
    echo "OrderItem created.\n";

    app(\App\Services\LedgerService::class)->recordGlobal('MANUAL_VOUCHER_ISSUE', $order, [
        'sku'        => 'WF-TEST',
        'count'      => 1,
        'total_rub'  => 100,
    ]);
    echo "Global Ledger Recorded.\n";
    
    DB::rollBack();
    echo "SUCCESS REPRODUCTION TEST\n";

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    DB::rollBack();
}
