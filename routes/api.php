<?php

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
        Route::post('update-price-items', [YmMainController::class, 'prepareToUpdateItems']);
        Route::post('send-stock-items', [YmMainController::class, 'prepareSendStockItems']);
    });


});
