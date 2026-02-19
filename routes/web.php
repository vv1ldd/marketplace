<?php

use App\Http\Controllers\CodeController;
use App\Http\Middleware\AllowIframeForRoute;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => [AllowIframeForRoute::class]], function () {
    Route::group(['prefix' => 'redeem'], function () {

        Route::get('/', fn() => redirect()->route('redeem.code'));

        Route::get('code', [CodeController::class, 'getCodeView'])->name('redeem.code');
        Route::post('code', [CodeController::class, 'checkCode'])->name('redeem.code.submit')->middleware('throttle:30,1');

        Route::get('email', [CodeController::class, 'getEmailView'])->name('redeem.email');
        Route::post('email', [CodeController::class, 'checkEmail'])->name('redeem.email.submit')->middleware('throttle:5,1');

        Route::get('activation', [CodeController::class, 'getViewForm'])->name('redeem.activation');
        Route::post('activation', [CodeController::class, 'sendForm'])->name('redeem.activation.submit')->middleware('throttle:30,1');
        Route::post('resend', [CodeController::class, 'resendCode'])->name('redeem.resend')->middleware('throttle:5,1');

        Route::get('success', [CodeController::class, 'getFinishView'])->name('redeem.success');
    });
});


