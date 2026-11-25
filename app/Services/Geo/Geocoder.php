<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Free OpenStreetMap geocoding for the "GEO Location" editor (Flovatar / Psyche Likee: setPosition(lat, lon), Đ10).
 * Replaces the Google Maps Geocoder (no key needed).
 *
 *  - Photon (photon.komoot.io, OSM data): search-as-you-type + reverse, within its fair-use policy.
 *  - Nominatim (nominatim.openstreetmap.org): fallback ONLY for explicit searches (Enter / Search button) and reverse
 *    lookups, never for autocomplete (its usage policy forbids that), max 1 request/second for the whole site, with an
 *    identifying User-Agent + Referer.
 *
 * Server-side on purpose: one shared cache for every user, a proper User-Agent (browsers can't set one), a global rate
 * limit, and the user's IP never reaches the geocoder. Results are cached (30 days, empty/failed lookups 10 minutes).
 * Only lat/lon ever go on chain; the labels are display-only. Data © OpenStreetMap contributors (ODbL).
 */
class Geocoder
{
    public const ATTRIBUTION = '© OpenStreetMap contributors';

    /**
     * @return array{results: list<array{label: string, lat: float, lon: float}>, source: string|null, ok: bool}
     */
    public function search(string $q, bool $explicit = false, int $limit = 5): array
    {
        $q = $this->normalise($q);
        if (mb_strlen($q) < 2) {
            return ['results' => [], 'source' => null, 'ok' => true];
        }
        $key = 'geo:s:'.sha1(mb_strtolower($q).'|'.$limit);
        if (is_array($hit = Cache::get($key))) {
            // an empty autocomplete answer may still get the Nominatim fallback on an explicit search
            if ($hit['results'] || ! $explicit || $hit['source'] === 'nominatim') {
                return $hit;
            }
        }

        $out = ['results' => [], 'source' => null, 'ok' => false];
        $photon = $this->photonSearch($q, $limit);
        if ($photon !== null) {
            $out = ['results' => $photon, 'source' => 'photon', 'ok' => true];
        }
        if (! $out['results'] && $explicit) {
            $nom = $this->nominatimSearch($q, $limit);
            if ($nom !== null) {
                $out = ['results' => $nom, 'source' => 'nominatim', 'ok' => true];
            }
        }
        if ($out['ok']) {
            Cache::put($key, $out, $out['results'] ? now()->addDays(30) : now()->addMinutes(10));
        }

        return $out;
    }

    /** @return array{label: string|null, source: string|null, ok: bool} */
    public function reverse(float $lat, float $lon): array
    {
        $lat = round($lat, 5);
        $lon = round($lon, 5);
        $key = 'geo:r:'.$lat.','.$lon;
        if (is_array($hit = Cache::get($key))) {
            return $hit;
        }
        $out = ['label' => null, 'source' => null, 'ok' => false];
        if (($label = $this->photonReverse($lat, $lon)) !== null) {
            $out = ['label' => $label ?: null, 'source' => 'photon', 'ok' => true];
        }
        if (! $out['label'] && ($label = $this->nominatimReverse($lat, $lon)) !== null) {
            $out = ['label' => $label ?: null, 'source' => 'nominatim', 'ok' => true];
        }
        if ($out['ok']) {
            Cache::put($key, $out, $out['label'] ? now()->addDays(30) : now()->addMinutes(10));
        }

        return $out;
    }

    // --- Photon ---------------------------------------------------------------------------------------------------

    private function photonSearch(string $q, int $limit): ?array
    {
        $json = $this->photon('/api/', ['q' => $q, 'limit' => $limit, 'lang' => 'en']);

        return $json === null ? null : $this->photonFeatures($json, $limit);
    }

    private function photonReverse(float $lat, float $lon): ?string
    {
        $json = $this->photon('/reverse', ['lat' => $lat, 'lon' => $lon, 'limit' => 1, 'lang' => 'en']);
        if ($json === null) {
            return null;
        }

        // area-level label ("Milan, Lombardy, Italy"), not the nearest shop/house: it's what people expect for a pin
        $p = $json['features'][0]['properties'] ?? null;
        if (! is_array($p)) {
            return '';
        }
        $place = $p['city'] ?? ($p['town'] ?? ($p['village'] ?? ($p['district'] ?? ($p['county'] ?? null))));

        return $this->label([$place, $p['state'] ?? null, $p['country'] ?? null]) ?: $this->label([$p['name'] ?? null]);
    }

    private function photon(string $path, array $query): ?array
    {
        $per = (int) config('services.geo.photon_per_minute', 120);
        if (! RateLimiter::attempt('geo:photon', $per, fn () => true, 60)) {
            return null; // over our own fair-use budget: behave like an outage (Nominatim fallback / graceful error)
        }

        return $this->get(rtrim((string) config('services.geo.photon_url'), '/').$path, $query);
    }

    private function photonFeatures(array $json, int $limit): array
    {
        $out = [];
        foreach (($json['features'] ?? []) as $f) {
            $c = $f['geometry']['coordinates'] ?? null;
            $p = $f['properties'] ?? [];
            if (! is_array($c) || count($c) < 2 || ! is_numeric($c[0]) || ! is_numeric($c[1])) {
                continue;
            }
            $street = trim(($p['street'] ?? '').' '.($p['housenumber'] ?? ''));
            $parts = [$p['name'] ?? null, $street ?: null, $p['city'] ?? ($p['town'] ?? ($p['village'] ?? null)), $p['state'] ?? null, $p['country'] ?? null];
            $label = $this->label($parts);
            if ($label === '') {
                continue;
            }
            $row = ['label' => $label, 'lat' => round((float) $c[1], 6), 'lon' => round((float) $c[0], 6)];
            if (in_array($row, $out, true)) {
                continue;
            }
            $out[] = $row;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    // --- Nominatim (explicit searches + reverse only, 1 req/s site-wide) ------------------------------------------

    private function nominatimSearch(string $q, int $limit): ?array
    {
        $json = $this->nominatim('/search', ['q' => $q, 'format' => 'jsonv2', 'limit' => $limit, 'accept-language' => 'en']);
        if ($json === null) {
            return null;
        }
        $out = [];
        foreach ($json as $r) {
            if (! isset($r['lat'], $r['lon'], $r['display_name']) || ! is_numeric($r['lat']) || ! is_numeric($r['lon'])) {
                continue;
            }
            $out[] = ['label' => $this->label([(string) $r['display_name']]), 'lat' => round((float) $r['lat'], 6), 'lon' => round((float) $r['lon'], 6)];
        }

        return array_slice($out, 0, $limit);
    }

    private function nominatimReverse(float $lat, float $lon): ?string
    {
        $json = $this->nominatim('/reverse', ['lat' => $lat, 'lon' => $lon, 'format' => 'jsonv2', 'zoom' => 10, 'accept-language' => 'en']);

        return $json === null ? null : $this->label([(string) ($json['display_name'] ?? '')]);
    }

    private function nominatim(string $path, array $query): ?array
    {
        // Usage policy: absolute max 1 request/second. One slot per second for the whole site; if taken, give up
        // (the caller shows "try again") instead of queueing requests.
        if (! RateLimiter::attempt('geo:nominatim', 1, fn () => true, 1)) {
            return null;
        }

        return $this->get(rtrim((string) config('services.geo.nominatim_url'), '/').$path, $query);
    }

    // --- helpers ----------------------------------------------------------------------------------------------------

    private function get(string $url, array $query): ?array
    {
        try {
            $r = Http::timeout(6)->connectTimeout(3)->acceptJson()
                ->withHeaders(['User-Agent' => $this->userAgent(), 'Referer' => rtrim((string) config('app.url'), '/').'/'])
                ->get($url, $query);
            if (! $r->successful() || ! is_array($r->json())) {
                Log::info('geocoder: '.parse_url($url, PHP_URL_HOST).' answered '.$r->status());

                return null;
            }

            return $r->json();
        } catch (\Throwable $e) {
            Log::info('geocoder: '.parse_url($url, PHP_URL_HOST).' failed: '.$e->getMessage());

            return null;
        }
    }

    private function userAgent(): string
    {
        $contact = (string) config('services.geo.contact');

        return 'Flovatar/1.0 (+'.rtrim((string) config('app.url'), '/').'; set-location geocoder'.($contact !== '' ? '; '.$contact : '').')';
    }

    private function label(array $parts): string
    {
        $seen = [];
        foreach ($parts as $p) {
            $p = trim((string) $p);
            if ($p !== '' && ! in_array($p, $seen, true)) {
                $seen[] = $p;
            }
        }

        return mb_substr(implode(', ', $seen), 0, 160);
    }

    private function normalise(string $q): string
    {
        $q = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $q) ?? '';

        return mb_substr(trim(preg_replace('/\s+/u', ' ', $q) ?? ''), 0, 120);
    }
}
