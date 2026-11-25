<?php

namespace App\Http\Controllers;

use App\Support\Stealth;

/**
 * GET /fx/{code}.js|css — lazy feature bundles (webpack chunks + per-feature CSS). The build moves them from public/fx
 * to storage/app/fx (bin/fx-assets.mjs), so neither nginx nor the headless R2 mirror can serve them: a bundle is
 * returned only to requests the feature is visible to (mode on → everyone; stealth → stealth gate). Otherwise 404.
 */
class FxAssetController extends Controller
{
    public function __invoke(string $file)
    {
        [$code, $ext] = explode('.', $file, 2);
        $feature = Stealth::featureForBundle($code);
        abort_unless($feature && Stealth::visible($feature), 404);
        $path = storage_path('app/fx/'.$file);
        abort_unless(is_file($path), 404);

        $public = Stealth::mode($feature) === 'on';

        return response()->file($path, [
            'Content-Type' => $ext === 'js' ? 'application/javascript; charset=utf-8' : 'text/css; charset=utf-8',
            // public features: cacheable like any asset; stealth: never in a shared cache
            'Cache-Control' => $public ? 'public, max-age=300' : 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
