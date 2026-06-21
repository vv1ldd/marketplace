<?php

// use App\Http\Controllers\PlayStation\MainController;
use App\Http\Controllers\WooPriceUpdateController;
use App\Http\Controllers\Api\Storefront\StorefrontCatalogController;
use App\Http\Controllers\Api\Storefront\StorefrontCheckoutController;
use App\Http\Controllers\Api\Storefront\StorefrontContextController;
use App\Http\Controllers\Api\Storefront\StorefrontIdentityController;
use App\Http\Controllers\Api\Storefront\StorefrontPartnerRegistrationController;
use App\Http\Controllers\Api\Storefront\StorefrontPersonalizationController;
use App\Http\Controllers\Api\Storefront\StorefrontVaultController;
use App\Http\Controllers\Api\Storefront\StorefrontWalletController;
use App\Http\Controllers\Api\UiProjectionController;
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

// Provider webhooks terminate in Digital Goods Source.

/** Unified provider catalog — GET /api/catalog/products?token=xxx&provider=fazer */
Route::prefix('catalog')->group(function () {
    Route::get('products',        [\App\Http\Controllers\Api\CatalogApiController::class, 'products']);
    Route::get('products/{sku}',  [\App\Http\Controllers\Api\CatalogApiController::class, 'show']);
    Route::get('summary',         [\App\Http\Controllers\Api\CatalogApiController::class, 'summary']);
});

Route::prefix('storefront/v1')
    ->middleware([
        \App\Http\Middleware\ResolveMarketContext::class,
        \App\Http\Middleware\ResolvePricingContext::class,
    ])
    ->group(function () {
    Route::get('context', [StorefrontContextController::class, 'show']);
    Route::get('catalog', [StorefrontCatalogController::class, 'index']);
    Route::get('catalog/search', [StorefrontCatalogController::class, 'search']);
    Route::get('catalog/suggest', [StorefrontCatalogController::class, 'suggest']);
    Route::get('catalog/categories/{category}', [StorefrontCatalogController::class, 'category']);
    Route::get('catalog/groups/{category}/{brandSlug}/{kindSlug}', [StorefrontCatalogController::class, 'group']);
    Route::get('catalog/products/{slug}', [StorefrontCatalogController::class, 'product']);
    Route::post('checkout/availability', [StorefrontCheckoutController::class, 'availability']);
    Route::post('checkout/intent', [StorefrontCheckoutController::class, 'intent']);
    Route::post('checkout/create', [StorefrontCheckoutController::class, 'create'])
        ->middleware('storefront.token:storefront:checkout');
    Route::get('orders/{order:uuid}/safe/status', [StorefrontCheckoutController::class, 'orderSafe'])
        ->middleware('storefront.token:storefront:read');
    Route::post('orders/{order:uuid}/safe/open', [StorefrontCheckoutController::class, 'open'])
        ->middleware('storefront.token:storefront:read');
    Route::post('orders/{order:uuid}/safe/scratch', [StorefrontCheckoutController::class, 'scratch'])
        ->middleware('storefront.token:storefront:read');
    Route::get('orders/{order:uuid}/safe/support', [StorefrontCheckoutController::class, 'support'])
        ->middleware('storefront.token:storefront:read');
    Route::get('vault', [StorefrontVaultController::class, 'index'])
        ->middleware('storefront.token:storefront:vault');
    Route::get('wallet', [StorefrontWalletController::class, 'show'])
        ->middleware('storefront.token:storefront:vault');
    Route::get('wallet/bindings', [StorefrontWalletController::class, 'bindings'])
        ->middleware('storefront.token:storefront:vault');
    Route::post('wallet/bindings/challenge', [StorefrontWalletController::class, 'issueBindingChallenge'])
        ->middleware('storefront.token:storefront:vault');
    Route::post('wallet/bindings/verify', [StorefrontWalletController::class, 'verifyBindingChallenge'])
        ->middleware('storefront.token:storefront:vault');
    Route::post('wallet/bindings', [StorefrontWalletController::class, 'storeBinding'])
        ->middleware('storefront.token:storefront:vault');
    Route::delete('wallet/bindings/{identityBinding}', [StorefrontWalletController::class, 'destroyBinding'])
        ->middleware('storefront.token:storefront:vault');
    Route::get('wallet/assets', [StorefrontWalletController::class, 'assets'])
        ->middleware('storefront.token:storefront:vault');
    Route::post('wallet/proofs/usdc-transfer', [StorefrontWalletController::class, 'verifyUsdcTransferProof'])
        ->middleware('storefront.token:storefront:vault');
    Route::get('personalization/home', [StorefrontPersonalizationController::class, 'home'])
        ->middleware('storefront.token:storefront:read');
    Route::post('favorites/toggle', [StorefrontPersonalizationController::class, 'toggleFavorite'])
        ->middleware('storefront.token:storefront:read');
    Route::get('partner-registration/state', [StorefrontPartnerRegistrationController::class, 'state']);
    Route::post('identity/token', [StorefrontIdentityController::class, 'exchange']);
    Route::get('identity/session', [StorefrontIdentityController::class, 'session'])
        ->middleware('storefront.token:storefront:read');
});

if (config('identity_governance.stream_authorize_enabled')) {
    Route::post('sl1e/authorize/options', [\App\Http\Controllers\IdentityGovernanceStreamAuthorizeController::class, 'options']);
    Route::post('sl1e/authorize/verify', [\App\Http\Controllers\IdentityGovernanceStreamAuthorizeController::class, 'verify']);
}

Route::any('sl1e/{path?}', [\App\Http\Controllers\SimpleL1WebWalletProxyController::class, 'sl1eApi'])
    ->where('path', '.*');

Route::prefix('ui/v1')->group(function () {
    Route::get('projections/{surface}/{path?}', [UiProjectionController::class, 'show'])
        ->where('path', '.*');
});

Route::prefix('v1')
    ->middleware(['meanly.api.auth', 'meanly.financial.signature'])
    ->group(function () {
        Route::post('check-availability', [\App\Http\Controllers\Api\MeanlyApiController::class, 'checkAvailabilityFromPayload']);
        Route::post('order', [\App\Http\Controllers\Api\MeanlyApiController::class, 'topLevelOrder']);

        Route::prefix('providers/{provider}')->group(function () {
            Route::get('unified-catalog', [\App\Http\Controllers\Api\MeanlyApiController::class, 'unifiedCatalog']);
            Route::get('exchange-rates', [\App\Http\Controllers\Api\MeanlyApiController::class, 'exchangeRates']);
            Route::get('check-availability/{sku}', [\App\Http\Controllers\Api\MeanlyApiController::class, 'checkAvailability']);
            Route::post('check-availability', [\App\Http\Controllers\Api\MeanlyApiController::class, 'checkAvailabilityFromPayload']);
            Route::post('order', [\App\Http\Controllers\Api\MeanlyApiController::class, 'placeOrder']);
            Route::get('orders/{reference}/normalized-cards', [\App\Http\Controllers\Api\MeanlyApiController::class, 'normalizedCards']);
        });

        Route::prefix('partners')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\MeanlyApiController::class, 'listPartners']);
            Route::post('sync', [\App\Http\Controllers\Api\MeanlyApiController::class, 'syncPartner']);
            Route::post('grant-credit', [\App\Http\Controllers\Api\MeanlyApiController::class, 'grantCredit']);
            Route::post('top-up', [\App\Http\Controllers\Api\MeanlyApiController::class, 'topUp']);
            Route::get('{externalId}', [\App\Http\Controllers\Api\MeanlyApiController::class, 'showPartner']);
            Route::delete('{externalId}', [\App\Http\Controllers\Api\MeanlyApiController::class, 'destroyPartner']);
        });
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

