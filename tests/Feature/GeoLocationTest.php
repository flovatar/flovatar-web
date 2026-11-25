<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * GEO Location editor on OpenStreetMap (App\Services\Geo\Geocoder, /api/v2/geo/*): Photon first, Nominatim only for
 * explicit searches / reverse (never autocomplete), shared cache, identifying User-Agent, graceful 503, flag off = 404,
 * no Google Maps anywhere.
 */
class GeoLocationTest extends TestCase
{
    private const PHOTON = [
        'features' => [
            ['geometry' => ['coordinates' => [9.1900, 45.4642]], 'properties' => ['name' => 'Milan', 'state' => 'Lombardy', 'country' => 'Italy']],
            ['geometry' => ['coordinates' => [-89.0, 37.0]], 'properties' => ['name' => 'Milan', 'state' => 'Illinois', 'country' => 'United States']],
            ['geometry' => ['coordinates' => ['x', 1]], 'properties' => ['name' => 'broken']],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'session.driver' => 'array', 'services.geo.enabled' => true,
            'services.geo.photon_url' => 'https://photon.test', 'services.geo.nominatim_url' => 'https://nominatim.test',
            'app.url' => 'https://testnet.flovatar.com']);
        Cache::flush();
        RateLimiter::clear('geo:photon');
        RateLimiter::clear('geo:nominatim');
    }

    public function test_search_uses_photon_and_caches(): void
    {
        Http::fake(['photon.test/*' => Http::response(self::PHOTON), 'nominatim.test/*' => Http::response([], 500)]);

        $r = $this->getJson('/api/v2/geo/search?q=milan')->assertOk()->json();
        $this->assertTrue($r['ok']);
        $this->assertSame('photon', $r['source']);
        $this->assertSame('© OpenStreetMap contributors', $r['attribution']);
        $this->assertCount(2, $r['results'], 'broken features are skipped');
        $this->assertSame(['label' => 'Milan, Lombardy, Italy', 'lat' => 45.4642, 'lon' => 9.19], $r['results'][0]);

        $this->getJson('/api/v2/geo/search?q=%20MILAN%20%20')->assertOk();
        Http::assertSentCount(1); // second (normalised) query served from cache
        Http::assertSent(fn ($req) => str_starts_with($req->url(), 'https://photon.test/api/')
            && str_contains($req->header('User-Agent')[0], 'Flovatar/1.0 (+https://testnet.flovatar.com')
            && $req->header('Referer')[0] === 'https://testnet.flovatar.com/');
    }

    public function test_autocomplete_never_falls_back_to_nominatim(): void
    {
        Http::fake(['photon.test/*' => Http::response('down', 502), 'nominatim.test/*' => Http::response([['lat' => '1', 'lon' => '2', 'display_name' => 'X']])]);

        $this->getJson('/api/v2/geo/search?q=milan')->assertStatus(503)->assertJson(['ok' => false, 'results' => []]);
        Http::assertNotSent(fn ($req) => str_contains($req->url(), 'nominatim.test'));
    }

    public function test_explicit_search_falls_back_to_nominatim_once_per_second(): void
    {
        Http::fake(['photon.test/*' => Http::response('down', 502), 'nominatim.test/*' => Http::response([
            ['lat' => '45.4641943', 'lon' => '9.1896346', 'display_name' => 'Milano, Lombardia, Italia'],
        ])]);

        $r = $this->getJson('/api/v2/geo/search?q=milano&submit=1')->assertOk()->json();
        $this->assertSame('nominatim', $r['source']);
        $this->assertSame(45.464194, $r['results'][0]['lat']);
        Http::assertSent(fn ($req) => str_starts_with($req->url(), 'https://nominatim.test/search')
            && str_contains($req->url(), 'format=jsonv2') && str_contains($req->header('User-Agent')[0], 'Flovatar/1.0'));

        // a different explicit query in the same second: the 1 req/s slot is taken -> graceful 503, no 2nd request
        $this->getJson('/api/v2/geo/search?q=rome&submit=1')->assertStatus(503);
        $this->assertSame(1, collect(Http::recorded())->filter(fn ($p) => str_contains($p[0]->url(), 'nominatim.test'))->count());
    }

    public function test_reverse_and_validation(): void
    {
        Http::fake(['photon.test/reverse*' => Http::response(['features' => [
            ['geometry' => ['coordinates' => [9.19, 45.46]], 'properties' => ['name' => 'Duomo', 'city' => 'Milan', 'country' => 'Italy']],
        ]])]);

        $this->getJson('/api/v2/geo/reverse?lat=45.46&lon=9.19')->assertOk()->assertJson(['ok' => true, 'label' => 'Milan, Italy', 'source' => 'photon']);
        $this->getJson('/api/v2/geo/reverse?lat=95&lon=9.19')->assertStatus(422);
        $this->getJson('/api/v2/geo/search')->assertStatus(422);
        $this->getJson('/api/v2/geo/search?q=a')->assertOk()->assertJson(['ok' => true, 'results' => []]);
    }

    public function test_flag_off_is_404(): void
    {
        config(['services.geo.enabled' => false]);
        Http::fake();
        $this->getJson('/api/v2/geo/search?q=milan')->assertNotFound();
        $this->getJson('/api/v2/geo/reverse?lat=1&lon=1')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_views_have_the_osm_editor_and_no_google_maps(): void
    {
        foreach (['flovatar', 'dust-collectible'] as $view) {
            $src = file_get_contents(resource_path("views/{$view}.blade.php"));
            $this->assertStringNotContainsStringIgnoringCase('google', $src);
            $this->assertStringContainsString("config('services.geo.enabled')", $src);
            $this->assertStringContainsString('geocodeAddress(true)', $src, 'Enter = explicit search');
            $this->assertStringContainsString('x-ref="geoMap"', $src);
            $this->assertStringContainsString('OpenStreetMap</a> contributors', $src);
        }
        $js = file_get_contents(resource_path('js/geo.js'));
        $this->assertStringContainsString('/api/v2/geo/search', $js);
        $this->assertStringNotContainsString('nominatim.openstreetmap.org', $js, 'the browser never calls Nominatim directly');
        $this->assertFileExists(public_path('vendor/leaflet-1.9.4/leaflet.js'));
        $this->assertFileExists(public_path('vendor/leaflet-1.9.4/images/marker-icon.png'));
    }
}
