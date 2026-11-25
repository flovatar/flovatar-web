<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Pack sales/reservations stay off until they are reopened with packs v2 (docs/PACK-SIGNING.md)
 * (config flovatar.packSalesEnabled / FLOVATAR_PACK_SALES_ENABLED).
 */
class EnsurePackSalesEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if (config('flovatar.packSalesEnabled')) {
            return $next($request);
        }

        $notice = (string) config('flovatar.packSalesNotice');

        if ($request->expectsJson() || ! $request->isMethod('GET')) {
            return response()->json(['result' => false, 'disabled' => true, 'message' => $notice], 503);
        }

        return response()->view('notice', [
            'title' => 'Pack sales paused',
            'message' => $notice,
        ], 503);
    }
}
