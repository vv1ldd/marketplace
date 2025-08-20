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

    Route::get('prices', [MainController::class, 'prices']);
});

Route::group(['prefix' => 'ym'], function () {
    Route::any('{token}/notification', [YmMainController::class, 'notification'])->where('token', \App\Models\Settings::get('YM_NOTIFICATION_TOKEN', config('services.ym.notification_token')));
});

Route::get('test', function (Request $request) {
    $data = \DB::connection('ps_plus')
        ->table('wp_posts')
        ->get();

    return response()->json($data);
});
