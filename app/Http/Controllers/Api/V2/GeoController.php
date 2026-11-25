<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\Geo\Geocoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GEO Location editor (Flovatar / Psyche Likee setPosition): OpenStreetMap geocoding proxy (Photon, Nominatim fallback).
 * Public GET under /api/v2 (edge-cacheable, CORS *). Off with GEO_LOCATION_ENABLED=false (404). See App\Services\Geo\Geocoder.
 *
 *   GET /api/v2/geo/search?q=milan[&submit=1]   -> {ok, source, results: [{label, lat, lon}], attribution}
 *   GET /api/v2/geo/reverse?lat=45.46&lon=9.19  -> {ok, source, label, attribution}
 */
class GeoController extends Controller
{
    public function search(Request $request, Geocoder $geo): JsonResponse
    {
        abort_unless(config('services.geo.enabled'), 404);
        $v = $request->validate(['q' => 'required|string|max:200', 'submit' => 'nullable|in:0,1']);
        $r = $geo->search((string) $v['q'], ($v['submit'] ?? '0') === '1');

        return response()->json($r + ['attribution' => Geocoder::ATTRIBUTION], $r['ok'] ? 200 : 503);
    }

    public function reverse(Request $request, Geocoder $geo): JsonResponse
    {
        abort_unless(config('services.geo.enabled'), 404);
        $v = $request->validate(['lat' => 'required|numeric|between:-90,90', 'lon' => 'required|numeric|between:-180,180']);
        $r = $geo->reverse((float) $v['lat'], (float) $v['lon']);

        return response()->json($r + ['attribution' => Geocoder::ATTRIBUTION], $r['ok'] ? 200 : 503);
    }
}
