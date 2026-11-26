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
// WebGL belt: compact manifest of every island + same-origin textures (atlas pages, near-LOD sprites)
Route::get('api/islands/belt', [IslandsController::class, 'apiBelt'])->name('api.islands.belt');
Route::get('api/islands/belt/atlas/{page}-{hash}.webp', [IslandsController::class, 'beltAtlas'])
    ->where(['page' => '[0-9]{1,5}', 'hash' => '[a-f0-9]{8,16}'])->name('api.islands.belt.atlas');
Route::get('api/islands/belt/sprite/{id}-{key}.webp', [IslandsController::class, 'beltSprite'])
    ->where(['id' => '[0-9]{1,10}', 'key' => '[a-z0-9]{6,20}'])->name('api.islands.belt.sprite');
Route::get('api/islands/config', [IslandsController::class, 'apiConfig'])->name('api.islands.config');
Route::get('api/islands/templates/{id}', [IslandsController::class, 'apiTemplate'])->whereNumber('id')->name('api.islands.template');
Route::get('api/islands/{id}', [IslandsController::class, 'apiShow'])->whereNumber('id')->name('api.islands.show');
Route::post('api/islands/{id}/refresh', [IslandsController::class, 'apiRefresh'])->whereNumber('id')
    ->middleware('throttle:20,1')->name('api.islands.refresh');
