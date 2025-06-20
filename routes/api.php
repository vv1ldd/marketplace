<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PlayStation\MainController;

Route::group(['prefix' => 'ps'], function () {
    Route::post('all-from-region', [MainController::class, 'allFromRegion']);
    Route::post('detail-from-region', [MainController::class, 'detailFromRegion']);
    Route::get('regions', [MainController::class, 'regions']);
    Route::get('categories', [MainController::class, 'categories']);

    Route::post('send-to-market', [MainController::class, 'sendToMarket']);
});
