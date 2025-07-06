<?php

use App\Http\Controllers\CodeController;
use Illuminate\Support\Facades\Route;

//Route::get('/', function () {
//    return view('welcome');
//});

Route::get('redeem', [CodeController::class, 'getCodeView'])->name('redeem');
Route::post('redeem', [CodeController::class, 'checkCode'])->name('redeem-send')->middleware('throttle:5,1');

Route::get('form', [CodeController::class, 'getViewForm'])->name('form');
Route::post('form', [CodeController::class, 'sendForm'])->name('form-send')->middleware('throttle:5,1');

Route::get('finish', [CodeController::class, 'getFinishView'])->name('finish');
