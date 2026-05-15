<?php

use App\Http\Controllers\CodeController;
use App\Http\Middleware\AllowIframeForRoute;
use App\Http\Middleware\ApplyRedeemThemeFromQuery;
use App\Http\Controllers\TelemetryController;
use Illuminate\Support\Facades\Route;

Route::domain(config('app.domain'))->group(function () {
    Route::passkeys();
    Route::get('/', fn () => view('landing'))->name('home');
    
    Route::prefix('partner')->group(function () {
        Route::get('/', [\App\Http\Controllers\PartnerDashboardController::class, 'index'])->name('partner.dashboard');
        Route::post('/dashboard/sign', [\App\Http\Controllers\PartnerDashboardController::class, 'signAgreement'])->name('partner.dashboard.sign');
        Route::post('/dashboard/bank', [\App\Http\Controllers\PartnerDashboardController::class, 'updateBank'])->name('partner.dashboard.bank');
        
        // 🚀 Registration Flow
        Route::get('/register', [\App\Http\Controllers\PartnerRegistrationController::class, 'show'])->name('partner.register');
        Route::post('/register', [\App\Http\Controllers\PartnerRegistrationController::class, 'register'])->name('partner.register.submit');
        Route::post('/register/finalize', [\App\Http\Controllers\PartnerRegistrationController::class, 'finalize'])->name('partner.register.finalize');
        Route::get('/register/offer', [\App\Http\Controllers\PartnerRegistrationController::class, 'showOffer'])->name('partner.register.offer');
        Route::post('/register/offer', [\App\Http\Controllers\PartnerRegistrationController::class, 'acceptOffer'])->name('partner.register.offer.submit');
    });

    Route::post('/passkeys/register', [\App\Http\Controllers\PartnerRegistrationController::class, 'storePasskey'])->name('partner.register.passkey.store');
});

Route::get('/lang/{locale}', function (string $locale) {
    if (in_array($locale, ['ru', 'en', 'tk', 'uz', 'ka', 'hy', 'kk'])) {
        session(['locale' => $locale]);
    }
    return redirect()->back()->withHeaders(['Vary' => 'Accept-Language']);
})->name('lang.switch');

// Telemetry & Redirects (Telegram Sales Channel)
Route::get('/t/{id}', [TelemetryController::class, 'telegramClick'])->name('telegram.click');

$redeemEmailSubmitThrottle = app()->environment(['local', 'testing'])
    ? 'throttle:120,1'
    : 'throttle:25,1';
$redeemResendThrottle = app()->environment(['local', 'testing'])
    ? 'throttle:60,1'
    : 'throttle:15,1';

Route::group(['middleware' => [AllowIframeForRoute::class]], function () use ($redeemEmailSubmitThrottle, $redeemResendThrottle) {
    Route::group(['prefix' => 'redeem', 'middleware' => [ApplyRedeemThemeFromQuery::class]], function () use ($redeemEmailSubmitThrottle, $redeemResendThrottle) {

        Route::get('/', fn () => redirect()->route('redeem.code', request()->query()));
        Route::get('step1', fn () => redirect()->route('redeem.code', request()->query()));
        Route::get('step2', fn () => redirect()->route('redeem.email'));
        Route::get('step3', fn () => redirect()->route('redeem.activation'));

        Route::get('code', [CodeController::class, 'getCodeView'])->name('redeem.code');
        Route::post('code', [CodeController::class, 'checkCode'])->name('redeem.code.submit')->middleware('throttle:30,1');

        Route::get('email', [CodeController::class, 'getEmailView'])->name('redeem.email');
        Route::post('email', [CodeController::class, 'checkEmail'])->name('redeem.email.submit')->middleware($redeemEmailSubmitThrottle);

        Route::get('activation', [CodeController::class, 'getViewForm'])->name('redeem.activation');
        Route::post('activation', [CodeController::class, 'sendForm'])->name('redeem.activation.submit')->middleware('throttle:30,1');
        Route::post('resend', [CodeController::class, 'resendCode'])->name('redeem.resend')->middleware($redeemResendThrottle);

        Route::get('success', [CodeController::class, 'getFinishView'])->name('redeem.success');
        Route::get('success/status', [CodeController::class, 'redeemFinishStatus'])->name('redeem.finish-status')->middleware('throttle:120,1');
    });
});
