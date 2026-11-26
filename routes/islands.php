<?php

use App\Http\Controllers\IslandsController;
use Illuminate\Support\Facades\Route;

/*
 * Asteroid Islands (KB notes/islands-build.md). Registered from routes/web.php only while App\Support\Islands::active()
 * (ISLANDS_MODE stealth|on + island contract configured); the `stealth:islands` middleware 404s invisible requests.
 * Everything here is read-only: redeeming (terraform) and resident changes are signed in the wallet (FCL) and reach the
 * DB through the event indexer.
 */
Route::get('islands', [IslandsController::class, 'index'])->name('islands.index');
Route::get('islands/build', [IslandsController::class, 'build'])->name('islands.build');
Route::get('islands/{id}', [IslandsController::class, 'show'])->whereNumber('id')->name('islands.show');
Route::get('islands/{id}/full.svg', [IslandsController::class, 'fullSvg'])->whereNumber('id')->name('islands.full');
Route::get('islands/{id}/scene.svg', [IslandsController::class, 'sceneSvg'])->whereNumber('id')->name('islands.scene');

Route::get('api/islands', [IslandsController::class, 'apiList'])->name('api.islands.list');
Route::get('api/islands/config', [IslandsController::class, 'apiConfig'])->name('api.islands.config');
Route::get('api/islands/templates/{id}', [IslandsController::class, 'apiTemplate'])->whereNumber('id')->name('api.islands.template');
Route::get('api/islands/{id}', [IslandsController::class, 'apiShow'])->whereNumber('id')->name('api.islands.show');
Route::post('api/islands/{id}/refresh', [IslandsController::class, 'apiRefresh'])->whereNumber('id')
    ->middleware('throttle:20,1')->name('api.islands.refresh');
