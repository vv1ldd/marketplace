<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PlayStation\MainController;

Route::group(['prefix' => 'ps'], function () {
    Route::post('all-from-region', [MainController::class, 'allFromRegion']);
    Route::post('detail-from-region', [MainController::class, 'detailFromRegion']);
});
