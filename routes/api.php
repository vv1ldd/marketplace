<?php

// use App\Http\Controllers\PlayStation\MainController;
use App\Http\Controllers\WooPriceUpdateController;
use App\Http\Controllers\Ym\MainController as YmMainController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'ps'], function () {
/*
    Route::post('all-from-region', [MainController::class, 'allFromRegion']);
    Route::post('detail-from-region', [MainController::class, 'detailFromRegion']);
    Route::get('regions', [MainController::class, 'regions']);
    Route::get('categories', [MainController::class, 'categories']);
*/

    Route::group(['prefix' => 'ym'], function () {
        Route::post('send-items', [YmMainController::class, 'prepareToSendItems']);
        Route::post('update-price-items', [YmMainController::class, 'prepareToUpdatePriceItems']);
        Route::post('send-stock-items', [YmMainController::class, 'prepareSendStockItems']);
        Route::post('items-show', [YmMainController::class, 'prepareToItemsShow']);
        Route::post('delete-items', [YmMainController::class, 'prepareToDeleteItems']);
    });

//    Route::get('prices', [MainController::class, 'prices']);
});

Route::group(['prefix' => 'ym'], function () {
    Route::any('{token}/notification', [YmMainController::class, 'notification']);

    Route::post('send-items-wildflow', [YmMainController::class, 'sendItemsWildflow']);
    Route::post('send-stock-items-wildflow', [YmMainController::class, 'prepareSendStockItemsWildflow']);
});

// Route::get('update-woo-prices', [WooPriceUpdateController::class, 'update']);

Route::group(['prefix' => 'redeem', 'middleware' => 'api.redeem.auth:shop'], function () {
    Route::post('verify-code', [\App\Http\Controllers\Api\RedeemApiController::class, 'verifyCode']);
    Route::post('send-verification', [\App\Http\Controllers\Api\RedeemApiController::class, 'sendVerification']);
    Route::post('activate', [\App\Http\Controllers\Api\RedeemApiController::class, 'activate']);
});

/** Bearer ApiApplication: токен магазина — только свои SKU / заказы; platform — полный снимок (внутренний). */
Route::group(['prefix' => 'ledger', 'middleware' => 'api.ledger.auth:ledger'], function () {
    Route::get('catalog-map', [\App\Http\Controllers\Api\LedgerApiController::class, 'catalogMap']);
    Route::get('redeem-events', [\App\Http\Controllers\Api\LedgerApiController::class, 'redeemEvents']);
    Route::get('trace/{reference}', [\App\Http\Controllers\Api\LedgerApiController::class, 'trace']);
});

Route::get('image-generate', [YmMainController::class, 'imageGenerate']);
Route::get('description-generate', [YmMainController::class, 'descriptionGenerate']);

Route::post('telegram/webhook/{token}', [\App\Http\Controllers\TelemetryController::class, 'telegramWebhook']);

// Route::post('webhooks/fazer', [\App\Http\Controllers\Api\Webhooks\FazerWebhookController::class, 'handle']);

/** Unified provider catalog — GET /api/catalog/products?token=xxx&provider=fazer */
Route::prefix('catalog')->group(function () {
    Route::get('products',        [\App\Http\Controllers\Api\CatalogApiController::class, 'products']);
    Route::get('products/{sku}',  [\App\Http\Controllers\Api\CatalogApiController::class, 'show']);
    Route::get('summary',         [\App\Http\Controllers\Api\CatalogApiController::class, 'summary']);
});

// delivery type = 3 - whatsapp, 0 - ничего, 2- sms, 1 - email
// 1. create-order -> приходит referenceCode
// 2. запускаем orders/{referenceCode} -> отправляем оригинал клиенту при активации
Route::post('/telemetry/report', [App\Http\Controllers\Api\TelemetryController::class, 'report']);
Route::post('/b2b/search', [App\Http\Controllers\Api\B2BController::class, 'search']);

// Festive Holiday Google-Doodle-style API
Route::get('/holidays/active', [\App\Http\Controllers\Api\HolidayApiController::class, 'getActiveHoliday']);

/** Seller Terminal API endpoints protected by seller.terminal middleware */
Route::group(['prefix' => 'seller', 'middleware' => 'seller.terminal'], function () {
    Route::get('balance',           [\App\Http\Controllers\Api\SellerOrderController::class, 'balance']);
    Route::get('catalog',           [\App\Http\Controllers\Api\SellerOrderController::class, 'catalog']);
    Route::post('order',            [\App\Http\Controllers\Api\SellerOrderController::class, 'createOrder']);
    Route::get('order/{reference}', [\App\Http\Controllers\Api\SellerOrderController::class, 'showOrder']);
});

