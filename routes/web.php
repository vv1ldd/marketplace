<?php

use App\Http\Controllers\CodeController;
use App\Http\Middleware\AllowIframeForRoute;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => [AllowIframeForRoute::class]], function () {
    Route::group(['prefix' => 'redeem'], function () {

        Route::get('/', fn() => redirect()->route('redeem.step1'));

        Route::get('step1', [CodeController::class, 'getCodeView'])->name('redeem.step1');
        Route::post('step1', [CodeController::class, 'checkCode'])->name('redeem.step1')->middleware('throttle:30,1');

        Route::get('step2', [CodeController::class, 'getEmailView'])->name('redeem.step2');
        Route::post('step2', [CodeController::class, 'checkEmail'])->name('redeem.step2')->middleware('throttle:5,1');

        Route::get('step3', [CodeController::class, 'getViewForm'])->name('redeem.step3');
        Route::post('step3', [CodeController::class, 'sendForm'])->name('redeem.step3')->middleware('throttle:30,1');

        Route::get('finish', [CodeController::class, 'getFinishView'])->name('redeem.finish');
    });
});


