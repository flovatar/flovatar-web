<?php

use App\Http\Controllers\PasskeyPocController;
use Illuminate\Support\Facades\Route;

/*
 * Passkey PoC routes (testnet only). Registered from routes/web.php only when config('passkey.enabled').
 * The controller also refuses to run unless services.flow.network is testnet.
 */
Route::prefix('passkey')->name('passkey.')->group(function () {
    Route::get('/', [PasskeyPocController::class, 'page'])->name('page');
    Route::get('me', [PasskeyPocController::class, 'me'])->name('me')->middleware('throttle:60,1');
    Route::get('templates', [PasskeyPocController::class, 'templates'])->name('templates')->middleware('throttle:30,1');
    Route::post('combination', [PasskeyPocController::class, 'combination'])->name('combination')->middleware('throttle:60,1');

    Route::post('register/options', [PasskeyPocController::class, 'registerOptions'])->name('register.options')->middleware('throttle:10,1');
    Route::post('register/verify', [PasskeyPocController::class, 'registerVerify'])->name('register.verify')->middleware('throttle:10,1');
    Route::post('login/options', [PasskeyPocController::class, 'loginOptions'])->name('login.options')->middleware('throttle:30,1');
    Route::post('login/verify', [PasskeyPocController::class, 'loginVerify'])->name('login.verify')->middleware('throttle:30,1');
    Route::post('logout', [PasskeyPocController::class, 'logout'])->name('logout');

    Route::post('device/options', [PasskeyPocController::class, 'deviceOptions'])->name('device.options')->middleware('throttle:10,1');
    Route::post('device/verify', [PasskeyPocController::class, 'deviceVerify'])->name('device.verify')->middleware('throttle:10,1');

    Route::post('tx/prepare', [PasskeyPocController::class, 'txPrepare'])->name('tx.prepare')->middleware('throttle:30,1');
    // Site integration (PASSKEY_LOGIN_ENABLED): allowlisted site transactions + account panel
    Route::post('tx/template', [PasskeyPocController::class, 'txTemplate'])->name('tx.template')->middleware('throttle:30,1');
    Route::get('account', [PasskeyPocController::class, 'account'])->name('account')->middleware('throttle:60,1');
    Route::post('tx/submit', [PasskeyPocController::class, 'txSubmit'])->name('tx.submit')->middleware('throttle:30,1');
    Route::get('tx/{txId}', [PasskeyPocController::class, 'txStatus'])->name('tx.status')->middleware('throttle:120,1');
    Route::post('gift', [PasskeyPocController::class, 'gift'])->name('gift')->middleware('throttle:5,1');
});
