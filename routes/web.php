<?php

use App\Http\Controllers\CodeController;
use Illuminate\Support\Facades\Route;

//Route::get('/', function () {
//    return view('welcome');
//});

Route::get('check-code', [CodeController::class, 'getCodeView'])->name('check-code');
Route::post('check-code', [CodeController::class, 'checkCode'])->name('check-code')->middleware('throttle:5,1');

Route::get('form', [CodeController::class, 'getViewForm'])->name('form');
Route::post('form', [CodeController::class, 'sendForm'])->name('form')->middleware('throttle:5,1');
