<?php

use App\Http\Controllers\OutOrder;
use App\Http\Controllers\WooPriceUpdateController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PlayStation\MainController;
use App\Http\Controllers\Ym\MainController as YmMainController;

Route::group(['prefix' => 'ps'], function () {
    Route::post('all-from-region', [MainController::class, 'allFromRegion']);
    Route::post('detail-from-region', [MainController::class, 'detailFromRegion']);
    Route::get('regions', [MainController::class, 'regions']);
    Route::get('categories', [MainController::class, 'categories']);

    Route::group(['prefix' => 'ym'], function () {
        Route::post('send-items', [YmMainController::class, 'prepareToSendItems']);
        Route::post('update-price-items', [YmMainController::class, 'prepareToUpdatePriceItems']);
        Route::post('send-stock-items', [YmMainController::class, 'prepareSendStockItems']);
        Route::post('items-show', [YmMainController::class, 'prepareToItemsShow']);
        Route::post('delete-items', [YmMainController::class, 'prepareToDeleteItems']);
    });

    Route::get('prices', [MainController::class, 'prices']);
});

Route::group(['prefix' => 'ym'], function () {
    Route::any('{token}/notification', [YmMainController::class, 'notification']);

    Route::post('send-items-wildflow', [YmMainController::class, 'sendItemsWildflow']);
    Route::post('send-stock-items-wildflow', [YmMainController::class, 'prepareSendStockItemsWildflow']);
});

Route::get('update-woo-prices', [WooPriceUpdateController::class, 'update']);

Route::group(['prefix' => 'redeem', 'middleware' => 'api.redeem.auth'], function () {
    Route::post('verify-code', [\App\Http\Controllers\Api\RedeemApiController::class, 'verifyCode']);
    Route::post('send-verification', [\App\Http\Controllers\Api\RedeemApiController::class, 'sendVerification']);
    Route::post('activate', [\App\Http\Controllers\Api\RedeemApiController::class, 'activate']);
});

Route::get('image-generate', [YmMainController::class, 'imageGenerate']);
Route::get('description-generate', [YmMainController::class, 'descriptionGenerate']);

