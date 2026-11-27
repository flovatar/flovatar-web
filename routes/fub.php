<?php

use App\Http\Controllers\Fub\FubAdminController;
use App\Http\Controllers\Fub\FubDraftController;
use App\Http\Controllers\Fub\FubPagesController;
use App\Http\Controllers\Fub\FubToolController;
use Illuminate\Support\Facades\Route;

/*
 * FUB v1 (KB notes/fub/FUB-BUILD.md). Registered from routes/web.php only while App\Support\Fub::active()
 * (FUB_MODE stealth|on + contract configured). /fub itself is registered there (falls back to the 2022 page).
 * Wallet transactions (commit, publish, mint, ...) are signed in the browser; the server links/verifies them.
 */
Route::middleware('stealth:fub')->group(function () {
    Route::get('fub/studio', [FubPagesController::class, 'studio'])->name('fub.studio');
    Route::get('fub/studio/{draft}', [FubPagesController::class, 'studio'])->whereNumber('draft')->name('fub.studio.draft');
    Route::get('fub/fun/{id}.png', [FubPagesController::class, 'funPng'])->whereNumber('id')->name('fub.fun.png');
    Route::get('fub/fun/{id}', [FubPagesController::class, 'fun'])->whereNumber('id')->name('fub.fun');
    Route::get('fub/admin', [FubAdminController::class, 'index'])->name('fub.admin');
    Route::post('fub/admin/{id}/hide', [FubAdminController::class, 'hide'])->whereNumber('id')->name('fub.admin.hide');
    Route::post('fub/admin/reports/{id}', [FubAdminController::class, 'report'])->whereNumber('id')->name('fub.admin.report');
    Route::get('fub/{id}', [FubPagesController::class, 'show'])->whereNumber('id')->name('fub.show');

    // user SVG: image/svg+xml + sandbox CSP, referenced only via <img>
    Route::get('fub-content/item/{id}.svg', [FubPagesController::class, 'itemSvg'])->whereNumber('id');
    Route::get('fub-content/fub/{id}.svg', [FubPagesController::class, 'composeSvg'])->whereNumber('id');
    Route::get('fub-content/fun/{id}.svg', [FubPagesController::class, 'funSvg'])->whereNumber('id');
    Route::get('fub-content/draft-item/{id}.svg', [FubPagesController::class, 'draftItemSvg'])->whereNumber('id');
    Route::get('fub-content/draft/{id}.svg', [FubPagesController::class, 'draftComposeSvg'])->whereNumber('id');

    Route::get('api/fub/config', [FubPagesController::class, 'apiConfig']);
    Route::get('api/fub/list', [FubPagesController::class, 'apiList']);
    Route::get('api/fub/funs', [FubPagesController::class, 'apiFuns']);
    Route::get('api/fub/{id}', [FubPagesController::class, 'apiShow'])->whereNumber('id');
    Route::post('api/fub/{id}/refresh', [FubPagesController::class, 'apiRefresh'])->whereNumber('id')->middleware('throttle:30,1');
    Route::post('api/fub/report', [FubPagesController::class, 'report'])->middleware('throttle:10,1');

    Route::middleware('throttle:120,1')->prefix('api/fub/drafts')->group(function () {
        Route::get('/', [FubDraftController::class, 'index']);
        Route::post('/', [FubDraftController::class, 'store']);
        Route::post('validate-svg', [FubDraftController::class, 'validateSvg']);
        Route::get('{id}', [FubDraftController::class, 'show'])->whereNumber('id');
        Route::patch('{id}', [FubDraftController::class, 'update'])->whereNumber('id');
        Route::delete('{id}', [FubDraftController::class, 'destroy'])->whereNumber('id');
        Route::post('{id}/layers', [FubDraftController::class, 'addLayer'])->whereNumber('id');
        Route::post('{id}/layers/reorder', [FubDraftController::class, 'reorderLayers'])->whereNumber('id');
        Route::patch('{id}/layers/{key}', [FubDraftController::class, 'updateLayer'])->whereNumber('id');
        Route::delete('{id}/layers/{key}', [FubDraftController::class, 'deleteLayer'])->whereNumber('id');
        Route::post('{id}/items', [FubDraftController::class, 'addItem'])->whereNumber('id');
        Route::patch('{id}/items/{item}', [FubDraftController::class, 'updateItem'])->whereNumber(['id', 'item']);
        Route::post('{id}/items/{item}/svg', [FubDraftController::class, 'replaceSvg'])->whereNumber(['id', 'item']);
        Route::delete('{id}/items/{item}', [FubDraftController::class, 'deleteItem'])->whereNumber(['id', 'item']);
        Route::post('{id}/link', [FubDraftController::class, 'link'])->whereNumber('id');
        Route::post('{id}/absorbed', [FubDraftController::class, 'absorbed'])->whereNumber('id');
        Route::post('{id}/sync', [FubDraftController::class, 'sync'])->whereNumber('id');
        Route::get('{id}/preflight', [FubDraftController::class, 'preflight'])->whereNumber('id');
        Route::post('{id}/chain-preview', [FubDraftController::class, 'chainPreview'])->whereNumber('id');
        Route::post('{id}/grant', [FubDraftController::class, 'grant'])->whereNumber('id');
        Route::delete('{id}/grant', [FubDraftController::class, 'revokeGrants'])->whereNumber('id');
        Route::get('{id}/history', [FubDraftController::class, 'history'])->whereNumber('id');
    });
});

// Assistant draft tools: token + grant auth (no session/CSRF; exempt from the stealth gate in Stealth::SELF_AUTH_PATHS).
Route::middleware('throttle:240,1')->prefix('api/fub-tools')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])->group(function () {
    Route::get('grant', [FubToolController::class, 'grant']);
    Route::get('draft', [FubToolController::class, 'draft']);
    Route::patch('draft', [FubToolController::class, 'updateDraft']);
    Route::post('validate-svg', [FubToolController::class, 'validateSvg']);
    Route::post('layers', [FubToolController::class, 'addLayer']);
    Route::post('layers/reorder', [FubToolController::class, 'reorderLayers']);
    Route::patch('layers/{key}', [FubToolController::class, 'updateLayer']);
    Route::delete('layers/{key}', [FubToolController::class, 'deleteLayer']);
    Route::post('items', [FubToolController::class, 'addItem']);
    Route::get('items/{id}/svg', [FubToolController::class, 'itemSvg'])->whereNumber('id');
    Route::patch('items/{id}', [FubToolController::class, 'updateItem'])->whereNumber('id');
    Route::put('items/{id}/svg', [FubToolController::class, 'replaceSvg'])->whereNumber('id');
    Route::delete('items/{id}', [FubToolController::class, 'deleteItem'])->whereNumber('id');
});
