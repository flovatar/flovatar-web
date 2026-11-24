<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\Api\SearchController;
use \App\Http\Controllers\Api\SearchNoSvgController;
use \App\Http\Controllers\Api\TemplatesController;
use \App\Http\Controllers\Api\FlovatarsController;
use \App\Http\Controllers\Api\ComponentsController;
use \App\Http\Controllers\Api\SearchTransactionController;
use App\Http\Controllers\Api\MarketplaceStatsController;
use App\Http\Controllers\Api\CollectionApiController;
use App\Http\Controllers\Api\GameScoreController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/



Route::post('marketplace/search', SearchController::class)->name('api.search.marketplace');
Route::post('marketplace/search/nosvg', SearchNoSvgController::class)->name('api.search.marketplace.nosvg');
Route::post('transactions/search', SearchTransactionController::class)->name('api.search.transaction');
Route::get('marketplace/stats', MarketplaceStatsController::class)->name('api.stats');
Route::get('templates', TemplatesController::class)->name('api.templates');

Route::get('components/{id}', ComponentsController::class)->whereNumber('id')->name('api.components');


Route::get('flovatars/', [CollectionApiController::class,'latest'])
    ->name('api.flovatars');

Route::get('flovatars/{address}', [CollectionApiController::class,'address'])
    ->where('address','0x([a-f0-9]{16})')
    ->name('api.flovatars.address');

Route::get('flovatars/{id}', [FlovatarsController::class, 'byId'])->whereNumber('id')->name('api.flovatars.byId');
Route::get('flovatars/all', [FlovatarsController::class, 'all'])->name('api.flovatars.all');


Route::post('flovatars/search', [FlovatarsController::class,'search'])
    ->name('api.flovatars.search');



Route::get('leaderboard/game/{gameId}', [GameScoreController::class, 'byGame'])
    ->whereNumber('templateId')
    ->name('api.leaderboard.game');

Route::get('leaderboard/address/{gameId}', [GameScoreController::class, 'byAddress'])
    ->where('address','0x([a-f0-9]{16})')
    ->name('api.leaderboard.address');

Route::post('leaderboard', [GameScoreController::class, 'store'])->name('api.leaderboard.store')->middleware(['auth:sanctum', 'throttle:10,1']);

// Browser render worker (agent box): bearer RENDER_WORKER_TOKEN, stateless, never cached. See RenderJobController.
Route::middleware([\App\Http\Middleware\AuthenticateRenderWorker::class, 'throttle:240,1'])->prefix('render-jobs')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\RenderJobController::class, 'index'])->name('api.renderjobs.index');
    Route::post('claim', [\App\Http\Controllers\Api\RenderJobController::class, 'claim'])->name('api.renderjobs.claim');
    Route::get('{job}/svg/{variant}', [\App\Http\Controllers\Api\RenderJobController::class, 'svg'])
        ->whereNumber('job')->where('variant', 'full|nobg')->name('api.renderjobs.svg');
    Route::post('{job}/complete', [\App\Http\Controllers\Api\RenderJobController::class, 'complete'])->whereNumber('job')->name('api.renderjobs.complete');
    Route::post('{job}/fail', [\App\Http\Controllers\Api\RenderJobController::class, 'fail'])->whereNumber('job')->name('api.renderjobs.fail');
});

/*
| Headless frontend read API v2 (public, GET, edge-cacheable; docs/HEADLESS.md). No api-group IP throttle:
| (the origin sees Cloudflare edge/Worker IPs, not clients, so a per-IP limit would throttle many users at once);
| behind Cloudflare/the Worker the client IP is an edge IP, and the edge cache absorbs repeat traffic.
*/
Route::prefix('v2')
    ->withoutMiddleware('throttle:api')
    ->middleware([\App\Http\Middleware\PublicApiCache::class, 'throttle:6000,1'])
    ->group(function () {
        $p = \App\Http\Controllers\Api\V2\PublicController::class;
        Route::get('meta', [$p, 'meta'])->name('api.v2.meta');
        Route::get('marketplace', [$p, 'marketplace'])->name('api.v2.marketplace');
        Route::get('marketplace/stats', [$p, 'stats'])->name('api.v2.stats');
        Route::get('transactions', [$p, 'transactions'])->name('api.v2.transactions');
        Route::get('showroom/flovatars', [$p, 'showroomFlovatars'])->name('api.v2.showroom.flovatars');
        Route::get('showroom/flobots', [$p, 'showroomFlobots'])->name('api.v2.showroom.flobots');
        Route::get('showroom/collectibles', [$p, 'showroomCollectibles'])->name('api.v2.showroom.collectibles');
        Route::get('templates', [$p, 'templates'])->name('api.v2.templates');
        Route::get('templates/{flowId}', [$p, 'template'])->whereNumber('flowId')->name('api.v2.template');
        Route::get('packs', [$p, 'packs'])->name('api.v2.packs');
        Route::get('rankings', [$p, 'rankings'])->name('api.v2.rankings');
        Route::get('{segment}/{id}', [\App\Http\Controllers\Api\V2\ItemController::class, 'showSegment'])
            ->where('segment', 'flovatars|flobots|collectibles|components')->whereNumber('id')->name('api.v2.item');
    });
