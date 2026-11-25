<?php

namespace Tests\Feature;

use App\Services\Headless\StaticSiteBuilder;
use App\Support\TerrakitCheck;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * TerraKit wallet check (KB notes/terrakit-check.md): flag off = no route / 404 / no static page; flag on = static,
 * user-agnostic shell with the runtime config; pack range parsing.
 */
class TerrakitCheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'session.driver' => 'array']);
    }

    public function test_off_by_default(): void
    {
        $this->assertFalse((bool) (require base_path('config/terrakit.php'))['check_enabled']);
        $this->assertFalse(TerrakitCheck::enabled());
        $this->assertFalse(Route::has('terrakit.check'));
        $this->get('/terrakit/check')->assertNotFound();
    }

    public function test_controller_404s_when_flag_off_even_if_routes_are_loaded(): void
    {
        Route::middleware('web')->group(base_path('routes/terrakit.php'));
        config(['terrakit.check_enabled' => false]);
        $this->get('/terrakit/check')->assertNotFound();
    }

    public function test_page_when_enabled(): void
    {
        config(['terrakit.check_enabled' => true, 'terrakit.pack_ranges' => '13-15']);
        Route::middleware('web')->group(base_path('routes/terrakit.php'));
        $r = $this->get('/terrakit/check');
        $r->assertOk();
        $r->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $html = $r->getContent();
        $this->assertStringContainsString('x-data="terrakitCheck"', $html);
        $this->assertStringContainsString('window.TERRAKIT_CHECK = {"enabled":true,"packRanges":[[13,15]],"categories":["land","fub","flobotkit"]}', $html);
        $this->assertStringContainsString('Blocto Recovery Kit', $html);
        $this->assertStringContainsString('flow.com/post/action-required-for-blocto-wallet-users', $html);
        // brand: ĐUST with Đ, no dates, no "finally"
        $this->assertStringContainsString('ĐUST', $html);
        $page = file_get_contents(resource_path('views/terrakit/check.blade.php'));
        $this->assertDoesNotMatchRegularExpression('/\bfinally\b|\b20\d\d\b|\b(January|February|March|April|May|June|July|August|September|October|November|December)\b/i', $page);
        // static-safe: no session cookie for an anonymous visitor
        $this->assertEmpty(array_filter($r->headers->getCookies(), fn ($c) => str_contains($c->getName(), 'session')));
    }

    public function test_pack_ranges(): void
    {
        $this->assertSame([[10800, 15799]], TerrakitCheck::packRanges('10800-15799'));
        $this->assertSame([[13, 15], [20, 20]], TerrakitCheck::packRanges(' 13-15 , 20,x, 9-1'));
        $this->assertSame([], TerrakitCheck::packRanges(''));
    }

    public function test_headless_build_includes_the_page_only_when_enabled(): void
    {
        config(['terrakit.check_enabled' => false]);
        $this->assertArrayNotHasKey('/terrakit/check', StaticSiteBuilder::shells());
        config(['terrakit.check_enabled' => true]);
        $this->assertSame(['terrakit-check', 'terrakit/check/index.html', false], StaticSiteBuilder::shells()['/terrakit/check']);
        $this->assertArrayNotHasKey('/terrakit/check', StaticSiteBuilder::SHELLS, 'hidden page: never in the sitemap list');
    }
}
