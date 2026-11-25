<?php

use App\Http\Controllers\QuestsAdminController;
use App\Http\Controllers\QuestsController;
use App\Http\Controllers\QuestsPayoutController;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

/*
 * ĐUST Quests (testnet). Registered from routes/web.php only when App\Support\DustQuests::enabled()
 * (DUST_QUESTS_ENABLED + passkey sign-in + testnet). KB notes/dust-quests.md.
 */
Route::prefix('quests')->name('quests.')->group(function () {
    Route::get('/', [QuestsController::class, 'page'])->name('page');
    Route::get('state', [QuestsController::class, 'state'])->name('state')->middleware('throttle:60,1');
    Route::post('seen', [QuestsController::class, 'seen'])->name('seen')->middleware('throttle:30,1');
    Route::post('{key}/start', [QuestsController::class, 'start'])->name('start')->where('key', '[a-z0-9_]+')->middleware('throttle:30,1');
    Route::post('{key}/claim', [QuestsController::class, 'claim'])->name('claim')->where('key', '[a-z0-9_]+')->middleware('throttle:20,1');
    Route::get('discord/connect', [QuestsController::class, 'discordConnect'])->name('discord.connect')->middleware('throttle:10,1');
    Route::get('discord/callback', [QuestsController::class, 'discordCallback'])->name('discord.callback')->middleware('throttle:10,1');

    // Admin (ADMIN_ADDRESSES wallet session)
    Route::get('admin', [QuestsAdminController::class, 'page'])->name('admin');
    Route::get('admin/data', [QuestsAdminController::class, 'data'])->name('admin.data')->middleware('throttle:60,1');
    Route::post('admin/claims/{claim}/{action}', [QuestsAdminController::class, 'claimAction'])->name('admin.claim')->whereNumber('claim')->whereIn('action', ['approve', 'reject'])->middleware('throttle:60,1');
    Route::post('admin/accounts/{address}', [QuestsAdminController::class, 'account'])->name('admin.account')->where('address', '0x[0-9a-fA-F]{16}')->middleware('throttle:60,1');
    Route::post('admin/challenges/{challenge}', [QuestsAdminController::class, 'challenge'])->name('admin.challenge')->whereNumber('challenge')->middleware('throttle:60,1');
    Route::post('admin/settings', [QuestsAdminController::class, 'settings'])->name('admin.settings')->middleware('throttle:30,1');
    Route::post('admin/batches/{batch}/release', [QuestsAdminController::class, 'releaseBatch'])->name('admin.batch.release')->middleware('throttle:10,1');

    // Payout worker API (HMAC-signed, no session/CSRF): the rewards key lives on the worker box, never here
    Route::withoutMiddleware([VerifyCsrfToken::class])->group(function () {
        Route::post('payout/reserve', [QuestsPayoutController::class, 'reserve'])->name('payout.reserve')->middleware('throttle:30,1');
        Route::post('payout/report', [QuestsPayoutController::class, 'report'])->name('payout.report')->middleware('throttle:60,1');
        Route::post('payout/status', [QuestsPayoutController::class, 'status'])->name('payout.status')->middleware('throttle:30,1');
    });
});
