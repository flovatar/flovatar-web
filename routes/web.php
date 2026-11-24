<?php

use App\Http\Controllers\JsonController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\ShowroomController;
use App\Http\Controllers\FlobotShowroomController;
use App\Http\Controllers\BuilderController;
use App\Http\Controllers\BuilderSalesController;
use App\Http\Controllers\FlobotBuilderController;
use App\Http\Controllers\AsteroidBuilderController;
use App\Http\Controllers\FlobotBuilderSalesController;
use App\Http\Controllers\PackController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\FlovatarController;
use App\Http\Controllers\FlobotController;
use App\Http\Controllers\FlovatarImageController;
use App\Http\Controllers\TemplateImageController;
use App\Http\Controllers\FlobotImageController;
use App\Http\Controllers\FlobotTemplateImageController;
use App\Http\Controllers\FlovatarComponentController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\CollectionFindController;
use App\Http\Controllers\CollectionApiController;
use App\Http\Controllers\HomeController;
use \App\Http\Controllers\DownloadController;
use \App\Http\Controllers\TwitterController;
use \App\Http\Controllers\LoginController;
use \App\Http\Controllers\SubscriberController;
use \App\Http\Controllers\GraffleController;
use \App\Http\Controllers\SearchController;
use \App\Http\Controllers\SearchTransactionController;
use \App\Http\Controllers\SearchFlovatarController;
use \App\Http\Controllers\SearchFlobotController;
use \App\Http\Controllers\RedirectController;
use \App\Http\Controllers\MemeController;
use \App\Http\Controllers\TreasureHuntingController;
use \App\Http\Controllers\FubController;
use \App\Http\Controllers\FubTestController;
use \App\Http\Controllers\DustAccessoryController;
use \App\Http\Controllers\DustCollectibleController;
use \App\Http\Controllers\DustCollectibleShowroomController;
use \App\Http\Controllers\SearchDustCollectibleController;
use \App\Http\Controllers\DustCollectibleImageController;
use \App\Http\Controllers\DustTemplateImageController;
use \App\Http\Controllers\AsteroidImageController;
use \App\Http\Controllers\AsteroidTemplateImageController;
use \App\Http\Controllers\DustBuilderController;
use \App\Http\Controllers\DustSeriesBuilderController;
use \App\Http\Controllers\DustTokenController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', HomeController::class)->name('home');
Route::get('builder', BuilderController::class)->name('builder');
Route::get('flobot/builder', FlobotBuilderController::class)->name('flobot.builder');
Route::get('builder/flobot', FlobotBuilderController::class)->name('builder.flobot');
Route::get('builder/dust', DustBuilderController::class)->name('builder.dust');
Route::get('builder/asteroid', AsteroidBuilderController::class)->name('builder.asteroid');
Route::get('builder/dust/{series}', DustSeriesBuilderController::class)->name('builder.dust.series');
Route::view('fub-sale', 'sales')->name('fub.sale');
Route::view('land-sale', 'sales')->name('land.sale');
Route::get('fub', FubController::class)->name('fub');
Route::get('fub-test', FubTestController::class)->name('fub.test');
Route::post('builder/search', BuilderSalesController::class)->name('builder.search');
Route::post('flobot/builder/search', FlobotBuilderSalesController::class)->name('flobot.builder.search');
Route::post('subscribe', [SubscriberController::class,'store'])->name('subscribe')->middleware('throttle:5,1');
// Legacy Graffle webhook: replaced by the built-in indexer (flow:catchup / flow:stream).
if(config('services.graffle.enabled')){
    Route::post('graffle', GraffleController::class)->name('grafflepost');
}
Route::get('search', SearchController::class)->name('search');
Route::post('search', SearchController::class)->name('searchpost');
Route::get('marketplace', MarketplaceController::class)->name('marketplace');
Route::view('get-started', 'getstarted')->name('getstarted');
Route::get('meme-maker', MemeController::class)->name('mememaker');

Route::get('treasure-hunting', [TreasureHuntingController::class, 'index'])->name('treasurehunting');
Route::post('treasure-hunting', [TreasureHuntingController::class, 'verify'])->name('treasurehuntingverify');

Route::get('collection/api/', [CollectionApiController::class,'latest'])
    ->where('address','0x([a-f0-9]{16})')
    ->name('collectionapi');

Route::get('collection/api/{address}', [CollectionApiController::class,'address'])
    ->where('address','0x([a-f0-9]{16})')
    ->name('collectionapiaddress');

Route::get('templates/svg/{templateId}.svg', [DownloadController::class,'templateSvg'])
    ->whereNumber('templateId')
    ->name('templatesvg');

// Template list with SVGs as a versioned, long-cacheable script (builders, Flovatar/Flobot pages)
Route::get('templates/data/{set}.js', \App\Http\Controllers\TemplateDataController::class)
    ->where('set', 'series1|flobot|dust|dust2')
    ->name('templates.data');


Route::get('api/image/{flovatarId}', [FlovatarImageController::class,'svg'])
    ->whereNumber('flovatarId')
    ->name('flovatarimageapi');
Route::get('api/image/nobg/{flovatarId}', [FlovatarImageController::class,'svgNoBg'])
    ->whereNumber('flovatarId')
    ->name('flovatarimageapinobg');

Route::get('api/image/template/{templateId}', TemplateImageController::class)
    ->whereNumber('templateId')
    ->name('templateimageapi');


Route::get('api/image/flobot/{flobotId}', [FlobotImageController::class,'svg'])
    ->whereNumber('flobotId')
    ->name('flobotimageapi');
Route::get('api/image/flobot/nobg/{flobotId}', [FlobotImageController::class,'svgNoBg'])
    ->whereNumber('flobotId')
    ->name('flobotimageapinobg');
Route::get('api/image/flobot/template/{templateId}', FlobotTemplateImageController::class)
    ->whereNumber('templateId')
    ->name('flobottemplateimageapi');


Route::get('api/image/collectible/{collectibleId}', [DustCollectibleImageController::class,'svg'])
    ->whereNumber('collectibleId')
    ->name('collectibleimageapi');
Route::get('api/image/collectible/template/{templateId}', DustTemplateImageController::class)
    ->whereNumber('templateId')
    ->name('collectibletemplateimageapi');

Route::get('api/image/asteroid/{asteroidId}', [AsteroidImageController::class,'svg'])
    ->whereNumber('asteroidId')
    ->name('asteroidimageapi');
Route::get('api/image/asteroid/template/{templateId}', AsteroidTemplateImageController::class)
    ->whereNumber('templateId')
    ->name('asteroidtemplateimageapi');

Route::get('api/image/gif/{flovatarId}', [FlovatarImageController::class,'gif'])
    ->whereNumber('flovatarId')
    ->name('flovatarimageapigif');
Route::get('api/image/gif/nobg/{flovatarId}', [FlovatarImageController::class,'gifNoBg'])
    ->whereNumber('flovatarId')
    ->name('flovatarimageapigifnobg');

Route::get('api/image/png/{flovatarId}', [FlovatarImageController::class,'png'])
    ->whereNumber('flovatarId')
    ->name('flovatarimageapipng');
Route::get('api/image/png/nobg/{flovatarId}', [FlovatarImageController::class,'pngNoBg'])
    ->whereNumber('flovatarId')
    ->name('flovatarimageapipngnobg');

Route::get('api/image/jpg/{flovatarId}', [FlovatarImageController::class,'jpg'])
    ->whereNumber('flovatarId')
    ->name('flovatarimageapijpg');
// Static renders (resvg -> R2): full.png | full.jpg | nobg.png | thumb.jpg | og.jpg
Route::get('api/image/r/{type}/{flowId}/{variant}', \App\Http\Controllers\RenderedImageController::class)
    ->where('type', 'flovatar|flobot|collectible|template')
    ->whereNumber('flowId')
    ->where('variant', 'full\.png|full\.jpg|nobg\.png|thumb\.jpg|og\.jpg|anim\.gif|anim\.mp4')
    ->name('renderedimage');

Route::get('api/image/mp4/{flovatarId}', [FlovatarImageController::class,'mp4'])
    ->whereNumber('flovatarId')
    ->name('flovatarimageapimp4');
Route::get('api/image/mp4/nobg/{flovatarId}', [FlovatarImageController::class,'mp4NoBg'])
    ->whereNumber('flovatarId')
    ->name('flovatarimageapimp4nobg');



Route::get('showroom', ShowroomController::class)->name('showroom');
Route::post('showroom/search', SearchFlovatarController::class)->name('searchflovatar');
Route::get('showroom/flobot', FlobotShowroomController::class)->name('showroom.flobot');
Route::post('showroom/flobot/search', SearchFlobotController::class)->name('searchflobot');
Route::get('showroom/dust-collectible', DustCollectibleShowroomController::class)->name('showroom.dustcollectible');
Route::post('showroom/dust-collectible/search', SearchDustCollectibleController::class)->name('searchdustcollectible');

Route::view('privacy', 'privacy')->name('privacy');


Route::get('pack/login', [PackController::class,'login'])->name('pack.login');

// Pack sales and reservations are disabled until they reopen with packs v2 (docs/PACK-SIGNING.md)
// (FLOVATAR_PACK_SALES_ENABLED); the 'packsales' middleware answers with a notice.
Route::middleware('packsales')->group(function () {
if(config('flovatar.isTest')){
    Route::get('pack', [PackController::class,'buyAll'])->name('pack.buy');
    Route::post('pack/reserve', [PackController::class,'reserve'])->name('pack.reserve')->middleware(['auth:sanctum', 'throttle:20,1']);
    Route::post('pack/purchased', [PackController::class,'purchased'])->name('pack.purchased');
    Route::get('pack/dust/buy', [PackController::class,'dustBuy'])->name('pack.dustbuy');

} else {
    Route::get('pack', [PackController::class,'home'])->name('pack');
    Route::get('pack/buy', [PackController::class,'buy'])->name('pack.buy')->middleware(['auth:sanctum', 'verified', 'queueit']);
    Route::get('pack/dust/buy', [PackController::class,'dustBuy'])->name('pack.dustbuy');
    Route::post('pack/reserve', [PackController::class,'reserve'])->name('pack.reserve')->middleware(['auth:sanctum', 'throttle:20,1']);
    Route::post('pack/purchased', [PackController::class,'purchased'])->name('pack.purchased')->middleware(['auth:sanctum', 'verified', 'queueit']);
    Route::post('sportpacks', [PackController::class,'sport'])->name('pack.sport')->middleware(['auth:sanctum', 'verified']);

}
});


Route::get('flovatars/json/{flovatarId}', [JsonController::class, 'flovatar']);
Route::get('flobots/json/{flobotId}', [JsonController::class, 'flobot']);
Route::get('components/json/{componentId}',  [JsonController::class, 'component']);
Route::get('collectibles/json/{collectibleId}', [JsonController::class, 'collectible']);

Route::get('flovatars/{flovatarId}', [RedirectController::class, 'flovatar']);
Route::get('flobots/{flobotId}', [RedirectController::class, 'flobot']);
Route::get('components/{componentId}',  [RedirectController::class, 'component']);
Route::get('collectibles/{collectibleId}', [RedirectController::class, 'collectible']);


Route::get('transactions/{address?}', TransactionController::class)
    ->where('address','0x([a-f0-9]{16})')
    ->name('transactions');

Route::post('transactions/search', SearchTransactionController::class)->name('searchtransaction');

Route::get('flovatars/{flovatarId}/{address}', FlovatarController::class)
    ->whereNumber('flovatarId')
    ->where('address','0x([a-f0-9]{16})')
    ->name('flovatar');

Route::get('flobots/{flobotId}/{address}', FlobotController::class)
    ->whereNumber('flobotId')
    ->where('address','0x([a-f0-9]{16})')
    ->name('flobot');


Route::get('collectibles/{collectibleId}/{address}', DustCollectibleController::class)
    ->whereNumber('flovatarId')
    ->where('address','0x([a-f0-9]{16})')
    ->name('collectible');

Route::get('flovatars/{flovatarId}/{address}/download/svg', [DownloadController::class,'svg'])
    ->whereNumber('flovatarId')
    ->where('address','0x([a-f0-9]{16})')
    ->name('flovatar-download-svg');

Route::get('flovatars/{flovatarId}/{address}/download/png', [DownloadController::class,'png'])
    ->whereNumber('flovatarId')
    ->where('address','0x([a-f0-9]{16})')
    ->name('flovatar-download-png');

// Flobit TYPE page (one per template; prebuilt by headless:build, docs/HEADLESS.md)
Route::get('flobits/{templateId}', \App\Http\Controllers\FlobitTypeController::class)
    ->whereNumber('templateId')
    ->name('flobit-type');

Route::get('components/{componentId}/{address}', FlovatarComponentController::class)
    ->whereNumber('componentId')
    ->where('address','0x([a-f0-9]{16})')
    ->name('flovatarcomponent');

Route::get('collection/{address}', CollectionController::class)
    ->where('address','0x([a-f0-9]{16})')
    ->name('collection');

Route::get('collection/{name}', CollectionFindController::class)
    ->name('collectionfind');



Route::get('dust/accessories/{accessoryId}/{address}', DustAccessoryController::class)
    ->whereNumber('accessoryId')
    ->where('address','0x([a-f0-9]{16})')
    ->name('dustaccessory');


Route::get('api/dust/total-available', DustTokenController::class)
    ->name('dustavailable');



Route::get('/login', [LoginController::class,'login'])->name('login');
Route::get('/login/nonce', [LoginController::class,'nonce'])->name('login.nonce')->middleware('throttle:60,1');
Route::get('/csrf-token', [LoginController::class,'csrfToken'])->name('csrf.token')->middleware('throttle:60,1');
Route::post('/login', [LoginController::class,'submit'])->name('login.submit')->middleware('throttle:10,1');
Route::get('/logout', [LoginController::class,'logout'])->name('logout-get');

// Headless API v2 auth (session + CSRF like /login; the Worker proxies it on the frontend's own origin).
Route::prefix('api/v2/auth')->group(function () {
    Route::get('nonce', [\App\Http\Controllers\Api\V2\AuthController::class, 'nonce'])->name('api.v2.auth.nonce')->middleware('throttle:60,1');
    Route::get('csrf', [\App\Http\Controllers\Api\V2\AuthController::class, 'csrfToken'])->name('api.v2.auth.csrf')->middleware('throttle:60,1');
    Route::get('me', [\App\Http\Controllers\Api\V2\AuthController::class, 'me'])->name('api.v2.auth.me');
    Route::post('login', [\App\Http\Controllers\Api\V2\AuthController::class, 'loginJson'])->name('api.v2.auth.login')->middleware('throttle:10,1');
    Route::post('logout', [\App\Http\Controllers\Api\V2\AuthController::class, 'logoutJson'])->name('api.v2.auth.logout');
});


Route::get('twitter/login', [TwitterController::class,'login'])->name('twitter.login');
Route::get('twitter/callback', [TwitterController::class,'callback'])->name('twitter.callback');
Route::get('twitter/error', [TwitterController::class,'error'])->name('twitter.error');
Route::get('twitter/logout', [TwitterController::class,'logout'])->name('twitter.logout');
Route::get('twitter/webhook', [TwitterController::class,'webhook'])->name('twitter.webhook');

Route::get('flovatars/{flovatarId}/{address}/twitter', [TwitterController::class,'setProfileImage'])
    ->whereNumber('flovatarId')
    ->where('address','0x([a-f0-9]{16})')
    ->name('twitter.profile');


Route::view('flovatar-detail', 'flovatar-detail')->name('flovatar-detail');
Route::view('flovatar-detail-non-owner', 'flovatar-detail-non-owner')->name('flovatar-detail-non-owner');
Route::view('flobit-detail-owner', 'flobit-detail-owner')->name('flobit-detail-owner');
Route::view('flobit-detail-owner-gift-panel', 'flobit-detail-owner-gift-panel')->name('flobit-detail-owner-gift-panel');
Route::view('builder-psyche', 'builder-psyche')->name('builder-psyche');
Route::view('my-collection', 'my-collection')->name('my-collection');
Route::view('explore', 'explore')->name('explore');
Route::view('dust', 'dust')->name('dust');
Route::view('story', 'story')->name('story');
// Not-found page of the static headless site (stored as 404.html by headless:build).
Route::view('__headless_404', 'headless.404')->name('headless.404');


// FlovaChat (OpenAI/ElevenLabs) was retired in 2026-11. Old shared chat links go to the
// Flovatar page (which shows the retirement notice); the former chat API endpoints are gone (404).
Route::redirect('chat', '/', 301);
Route::get('chat/{flovatarId}', [RedirectController::class, 'retiredChat'])
    ->where('flovatarId', '(?!(send|check|checkaddress|tts|update)$)[^/]+');

// Passkey (WebAuthn / FLIP-264) walletless-onboarding proof of concept: TESTNET ONLY, off unless PASSKEY_POC_ENABLED=true.
if (config('passkey.enabled')) {
    require __DIR__.'/passkey.php';
}
