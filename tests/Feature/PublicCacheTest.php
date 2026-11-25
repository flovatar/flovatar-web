<?php

namespace Tests\Feature;

use App\Http\Middleware\ThrottleRequestsPerRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Edge cache contract (App\Support\PublicCache): anonymous public GETs carry no cookies and a
 * shared-cache header; session requests are private; CSRF comes from /csrf-token.
 * No database needed: public pages are stubbed with closures on their real paths.
 */
class PublicCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'array', 'cache.default' => 'array']);
        $this->withoutMiddleware(ThrottleRequestsPerRoute::class);
        Route::middleware('web')->get('explore', fn () => response('public page'));
        Route::middleware('web')->post('showroom/search', fn () => response()->json(['data' => []]));
    }

    private function sessionCookieName(): string
    {
        return config('session.cookie');
    }

    public function test_anonymous_public_get_has_no_cookies_and_public_cache_header(): void
    {
        $r = $this->get('/explore');
        $r->assertOk();
        $this->assertSame([], $r->headers->getCookies());
        $this->assertStringContainsString('public', $r->headers->get('Cache-Control'));
        $this->assertStringContainsString('s-maxage=60', $r->headers->get('Cache-Control'));
        $this->assertStringContainsString('stale-while-revalidate=300', $r->headers->get('Cache-Control'));
    }

    public function test_request_with_session_cookie_is_private(): void
    {
        $r = $this->withUnencryptedCookie($this->sessionCookieName(), 'abc')->get('/explore');
        $r->assertOk();
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $r->headers->get('Cache-Control'));
    }

    public function test_readonly_search_post_needs_no_csrf_and_sets_no_cookie(): void
    {
        $r = $this->postJson('/showroom/search', ['page' => 1]);
        $r->assertOk();
        $this->assertSame([], $r->headers->getCookies());
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
    }

    public function test_csrf_token_endpoint_starts_session(): void
    {
        $r = $this->getJson('/csrf-token');
        $r->assertOk()->assertJsonStructure(['token']);
        $names = array_map(fn ($c) => $c->getName(), $r->headers->getCookies());
        $this->assertContains($this->sessionCookieName(), $names);
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
    }

    // (VerifyCsrfToken is skipped while running unit tests; the 419 path is checked on testnet.)
    public function test_login_with_token_rejects_bad_proof(): void
    {
        $token = $this->getJson('/csrf-token')->json('token');
        $r = $this->postJson('/login', [
            'proofType' => 'account-proof',
            'address' => '0x2a0eccae942667be',
            'nonce' => str_repeat('a', 64),
            'signatures' => [['keyId' => 0, 'signature' => 'zz']],
        ], ['X-CSRF-TOKEN' => $token]);
        $r->assertOk()->assertJson(['result' => false]);
    }

    public function test_login_nonce_is_sessionless_and_stable_per_browser(): void
    {
        $r = $this->getJson('/login/nonce');
        $r->assertOk()->assertJson(['authenticated' => false]);
        $nonce = $r->json('nonce');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $nonce);
        $names = array_map(fn ($c) => $c->getName(), $r->headers->getCookies());
        $this->assertNotContains($this->sessionCookieName(), $names);
        $this->assertContains('flovatar_login', $names);

        $cookie = collect($r->headers->getCookies())->first(fn ($c) => $c->getName() === 'flovatar_login');
        $again = $this->withCookie('flovatar_login', $this->decryptCookie($cookie->getValue()))->getJson('/login/nonce');
        $this->assertSame($nonce, $again->json('nonce'));
    }

    public function test_login_marker_cookie_follows_server_login(): void
    {
        $marker = fn ($r) => collect($r->headers->getCookies())->first(fn ($c) => $c->getName() === 'fl_auth');

        // anonymous: no marker (the front end then never calls /login/nonce on page load)
        $this->assertNull($marker($this->getJson('/login/nonce')));

        // stale marker without a server login: cleared
        $stale = $marker($this->withCredentials()->withUnencryptedCookie('fl_auth', '1')->getJson('/login/nonce'));
        $this->assertNotNull($stale);
        $this->assertTrue($stale->isCleared());

        // logged in: marker '1', readable by JS (not HttpOnly, not encrypted)
        $user = new \App\Models\User(['email' => '0x00000000000000aa', 'name' => '0x00000000000000aa']);
        $set = $marker($this->actingAs($user)->getJson('/login/nonce')->assertJson(['authenticated' => true]));
        $this->assertNotNull($set);
        $this->assertSame('1', $set->getValue());
        $this->assertFalse($set->isHttpOnly());
        $this->assertFalse($set->isCleared());
    }

    private function decryptCookie(string $value): string
    {
        $plain = decrypt($value, false);

        return \Illuminate\Cookie\CookieValuePrefix::remove($plain);
    }
}
