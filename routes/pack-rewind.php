<?php

use App\Http\Controllers\PackRewindController;
use Illuminate\Support\Facades\Route;

/*
 * Pack Rewind (TESTNET ONLY, KB notes/pack-rewind.md). Registered from routes/web.php only while
 * App\Support\PackRewind::active() (PACK_REWIND_MODE stealth|on AND FLOW_NETWORK=testnet); `stealth:rewind` 404s
 * invisible requests. POSTs live outside /api/* so the site's axios sends the CSRF token.
 */
Route::get('pack-rewind', [PackRewindController::class, 'page'])->name('pack-rewind');
Route::get('api/pack-rewind/series', [PackRewindController::class, 'apiSeries'])->middleware('throttle:60,1')->name('pack-rewind.series');
Route::get('api/pack-rewind/packs', [PackRewindController::class, 'packs'])->middleware('throttle:60,1')->name('pack-rewind.packs');
Route::post('pack-rewind/reserve', [PackRewindController::class, 'reserve'])->middleware('throttle:20,1')->name('pack-rewind.reserve');
Route::post('pack-rewind/confirm', [PackRewindController::class, 'confirm'])->middleware('throttle:30,1')->name('pack-rewind.confirm');
Route::post('pack-rewind/opened', [PackRewindController::class, 'opened'])->middleware('throttle:30,1')->name('pack-rewind.opened');
