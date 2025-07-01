<?php

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
    });
});

Route::group(['prefix' => 'ym'], function () {
    Route::any('callback', function (Request $request) {

        // 5.45.207.0/25
        //141.8.142.0/25
        //5.255.253.0/25

        \Log::debug("ym callback", [
            'request' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        return response()->json([
            'name' => 'marketplace.1gros.ru',
            'time' => now('UTC')->toIso8601String(),
            'version' => '0.0.1'
        ]);
    });
});
