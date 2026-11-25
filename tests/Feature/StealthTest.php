<?php

namespace Tests\Feature;

use App\Http\Middleware\ThrottleRequestsPerRoute;
use App\Models\User;
use App\Support\PublicCache;
use App\Support\Stealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Stealth launch gate (App\Support\Stealth, flovatar-kb notes/mainnet-launch-stealth.md): per-feature mode off|stealth|on;
 * stealth = visible only with the signed fl_s cookie or an admin WALLET session; everyone else (and static builds) get
 * exactly the "off" output, stealth routes 404 without any cookie, responses rendered for a gated request are private.
 */
class StealthTest extends TestCase
{
    private const SECRET = 'test-stealth-secret-0123456789abcdef-XYZ';

    private const ADMIN = '0x989eaeed9ce94cd2';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'session.driver' => 'array',
            'cache.default' => 'array',
            'headless.enabled' => false,
            'services.flow.network' => 'testnet',
            'flovatar.dustSwap.enabled' => false,
            'terrakit.check_enabled' => false,
            'stealth.secret' => self::SECRET,
            'stealth.admin_addresses' => [self::ADMIN, '0x2a0eccae942667be'],
            'stealth.features' => ['passkey' => null, 'dust_market' => null, 'quests' => null, 'swap' => null, 'terrakit' => null],
        ]);
        DB::purge();
        $this->withoutMiddleware(ThrottleRequestsPerRoute::class);
    }

    private function cookieValue(string $kind = 's', ?string $address = null, int $minutes = 60): string
    {
        return Stealth::makeCookie($kind, $address, $minutes)->getValue();
    }

    private function mode(string $feature, ?string $mode): void
    {
        config(["stealth.features.$feature" => $mode]);
    }

    public function test_mode_resolution_legacy_flags_and_fail_closed(): void
    {
        $this->assertSame('off', Stealth::mode('swap'));
        config(['flovatar.dustSwap.enabled' => true]);
        $this->assertSame('on', Stealth::mode('swap'), 'unset *_MODE = legacy *_ENABLED (testnet keeps working)');
        $this->mode('swap', 'off');
        $this->assertSame('off', Stealth::mode('swap'), 'explicit mode wins over the legacy flag');
        $this->mode('swap', 'STEALTH ');
        $this->assertSame('stealth', Stealth::mode('swap'));
        $this->mode('swap', 'public');
        $this->assertSame('off', Stealth::mode('swap'), 'invalid value fails closed');
        $this->mode('swap', 'on');
        $this->assertTrue(Stealth::visible('swap'));
    }

    public function test_cookie_signature_expiry_and_admin_removal(): void
    {
        $req = fn (string $v) => tap(\Illuminate\Http\Request::create('/'), fn ($r) => $r->cookies->set('fl_s', $v));
        $this->assertNotNull(Stealth::cookiePayload($req($this->cookieValue())));
        $good = $this->cookieValue();
        $this->assertNull(Stealth::cookiePayload($req($good.'x')), 'tampered MAC');
        [$body, $mac] = explode('.', $good);
        $forged = rtrim(strtr(base64_encode(json_encode(['k' => 's', 'e' => time() + 999999])), '+/', '-_'), '=').'.'.$mac;
        $this->assertNull(Stealth::cookiePayload($req($forged)), 'other payload, same MAC');
        $this->assertNull(Stealth::cookiePayload($req($this->cookieValue('s', null, -1))), 'expired');
        $this->assertNotNull(Stealth::cookiePayload($req($this->cookieValue('a', self::ADMIN))));
        $admin = $this->cookieValue('a', self::ADMIN);
        config(['stealth.admin_addresses' => ['0x2a0eccae942667be']]);
        $this->assertNull(Stealth::cookiePayload($req($admin)), 'address removed from the admin list');
        config(['stealth.secret' => 'another-secret-0123456789abcdef-0000000']);
        $this->assertNull(Stealth::cookiePayload($req($good)), 'rotating STEALTH_SECRET invalidates cookies');
    }

    public function test_secret_url_sets_cookie_and_otherwise_looks_like_any_404(): void
    {
        // no feature in stealth mode: even the right secret is a plain 404
        $this->get('/__stealth/'.self::SECRET)->assertNotFound();
        $this->mode('swap', 'stealth');
        $wrong = $this->get('/__stealth/wrong-secret');
        $wrong->assertNotFound();
        $this->assertCount(0, $wrong->headers->getCookies(), 'a wrong secret sets no cookie');
        $ok = $this->get('/__stealth/'.self::SECRET);
        $ok->assertRedirect('/');
        $ok->assertCookie('fl_s', null, false);
        $this->assertStringContainsString('no-store', (string) $ok->headers->get('Cache-Control'));
        $off = $this->withUnencryptedCookie('fl_s', $this->cookieValue())->get('/__stealth/'.self::SECRET.'?off=1');
        $off->assertRedirect('/');
        $off->assertCookieExpired('fl_s');
        config(['stealth.secret' => 'short']);
        $this->get('/__stealth/short')->assertNotFound();
    }

    public function test_stealth_page_parity_for_anonymous_and_visible_with_cookie(): void
    {
        $offHtml = $this->get('/dust')->assertOk()->getContent();
        $this->mode('swap', 'stealth');
        $anon = $this->get('/dust')->assertOk();
        $this->assertSame($offHtml, $anon->getContent(), 'stealth = byte-identical to off for anonymous visitors');
        $this->assertStringContainsString('s-maxage', (string) $anon->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('window.__FX', $anon->getContent());
        $this->assertStringNotContainsString('/fx/', $anon->getContent());

        $forged = $this->withUnencryptedCookie('fl_s', 'eyJrIjoicyJ9.bogus')->get('/dust')->assertOk();
        $this->assertSame($offHtml, $forged->getContent(), 'forged cookie = public page');

        $this->flushHeaders();
        $gated = $this->withUnencryptedCookie('fl_s', $this->cookieValue())->get('/dust')->assertOk();
        $html = $gated->getContent();
        $this->assertStringContainsString('window.DUST_SWAP', $html);
        $this->assertStringContainsString('window.__FX = ["s"]', $html);
        $this->assertStringContainsString('/fx/s.css', $html);
        $this->assertStringContainsString('no-store', (string) $gated->headers->get('Cache-Control'), 'stealth render never shared');
    }

    public function test_admin_wallet_session_sees_stealth_but_passkey_session_and_others_do_not(): void
    {
        $this->mode('swap', 'stealth');
        \Illuminate\Support\Facades\Schema::create('users', function ($t) {
            $t->increments('id');
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->string('password')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });
        $admin = User::forceCreate(['name' => self::ADMIN, 'email' => self::ADMIN, 'password' => 'x']);
        $html = $this->actingAs($admin)->withCookie(config('session.cookie'), 'x')->get('/dust')->assertOk()->getContent();
        $this->assertStringContainsString('window.DUST_SWAP', $html);

        $other = User::forceCreate(['name' => '0x1111111111111111', 'email' => '0x1111111111111111', 'password' => 'x']);
        $html = $this->actingAs($other)->get('/dust')->assertOk()->getContent();
        $this->assertStringNotContainsString('window.DUST_SWAP', $html);
    }

    public function test_stealth_routes_404_before_session_and_open_with_cookie(): void
    {
        $this->mode('terrakit', 'stealth');
        config(['terrakit.pack_ranges' => '13-15']);
        Route::middleware(['web', 'stealth:terrakit'])->group(base_path('routes/terrakit.php'));

        $anon = $this->get('/terrakit/check');
        $anon->assertNotFound();
        $this->assertCount(0, $anon->headers->getCookies(), 'no session cookie for anonymous probes');
        $this->withUnencryptedCookie('fl_s', 'x.y')->get('/terrakit/check')->assertNotFound();
        $this->flushHeaders();
        $ok = $this->withUnencryptedCookie('fl_s', $this->cookieValue())->get('/terrakit/check');
        $ok->assertOk();
        $this->assertStringContainsString('no-store', (string) $ok->headers->get('Cache-Control'));

        $this->mode('terrakit', 'on');
        $this->flushHeaders();
        $this->withoutCookies()->get('/terrakit/check')->assertOk();
        $this->mode('terrakit', 'off');
        $this->get('/terrakit/check')->assertNotFound();
    }

    private function withoutCookies(): static
    {
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];

        return $this;
    }

    public function test_feature_bundles_are_served_only_where_visible(): void
    {
        $dir = storage_path('app/fx');
        @mkdir($dir, 0775, true);
        $created = ! is_file($dir.'/s.js');
        if ($created) {
            file_put_contents($dir.'/s.js', '/* test bundle */');
        }
        try {
            $this->get('/fx/s.js')->assertNotFound();
            $this->mode('swap', 'stealth');
            $this->get('/fx/s.js')->assertNotFound();
            $this->get('/fx/zz.js')->assertNotFound();
            $r = $this->withUnencryptedCookie('fl_s', $this->cookieValue())->get('/fx/s.js');
            $r->assertOk();
            $this->assertStringContainsString('no-store', (string) $r->headers->get('Cache-Control'));
            $this->assertFileDoesNotExist(public_path('fx/s.js'), 'bundles never live under public/');
        } finally {
            if ($created) {
                @unlink($dir.'/s.js');
            }
        }
    }

    public function test_shared_cache_keys_are_split_for_gated_requests(): void
    {
        $this->mode('dust_market', 'stealth');
        $anon = \Illuminate\Http\Request::create('/');
        $this->assertSame('k', Stealth::cacheKey('k', $anon));
        $gated = \Illuminate\Http\Request::create('/');
        $gated->cookies->set('fl_s', $this->cookieValue());
        $this->assertSame('k:stealth', Stealth::cacheKey('k', $gated));
        $this->assertFalse(PublicCache::isStateless($gated), 'valid stealth cookie = real session (never edge cached)');
        $this->mode('dust_market', 'on');
        $this->assertSame('k', Stealth::cacheKey('k', $gated), 'no stealth feature: one shared variant');
    }

    public function test_static_builds_and_console_never_see_stealth_features(): void
    {
        $this->mode('swap', 'stealth');
        $this->mode('dust_market', 'stealth');
        $this->assertTrue(Stealth::active('dust_market'), 'indexing runs in stealth');
        $this->assertFalse(Stealth::visible('dust_market', \Illuminate\Http\Request::create('/')));
        $this->assertSame([], Stealth::bundles(\Illuminate\Http\Request::create('/')));
        $this->assertNotContains('/terrakit/check', array_keys(\App\Services\Headless\StaticSiteBuilder::shells()));
    }

    public function test_mainnet_fails_closed_without_kms_sponsor_and_rewards_server(): void
    {
        config(['services.flow.network' => 'mainnet', 'services.flow.rest' => 'https://rest-mainnet.onflow.org',
            'passkey.login_enabled' => true, 'passkey.sponsor.driver' => 'file', 'quests.payout.rewards_address' => null]);
        $this->mode('passkey', 'on');
        $this->mode('quests', 'on');
        $this->assertFalse(\App\Support\PasskeyLogin::enabled(), 'mainnet passkey needs the KMS sponsor');
        $this->assertFalse(\App\Support\DustQuests::active(), 'mainnet quests need the rewards server');
        config(['passkey.sponsor.driver' => 'kms', 'passkey.sponsor.address' => '0x1234567890abcdef',
            'passkey.sponsor.kms' => ['provider' => 'aws', 'key' => 'arn:aws:kms:eu-central-1:111122223333:key/x', 'public_key' => str_repeat('ab', 64)],
            'passkey.contracts.FlowToken' => '0x1654653399040a61', 'passkey.contracts.FungibleToken' => '0xf233dcee88fe0abe']);
        $this->assertTrue(\App\Support\PasskeyLogin::enabled());
        config(['passkey.sponsor.kms.provider' => 'mock']);
        $this->assertFalse(\App\Support\PasskeyLogin::enabled(), 'the mock KMS never counts on mainnet');
        config(['quests.payout.rewards_address' => '0xabcdefabcdefabcd', 'quests.payout.secret' => str_repeat('k', 64), 'passkey.sponsor.kms.provider' => 'aws']);
        $this->assertTrue(\App\Support\DustQuests::active());
    }
}
