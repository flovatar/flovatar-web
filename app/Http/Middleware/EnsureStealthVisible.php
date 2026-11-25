<?php

namespace App\Http\Middleware;

use App\Support\Stealth;
use Closure;
use Illuminate\Http\Request;

/** Route middleware `stealth:<feature>`: 404 unless the feature is visible to this request (on, or stealth + gate). */
class EnsureStealthVisible
{
    public function handle(Request $request, Closure $next, string $feature)
    {
        if ($request->is(...Stealth::SELF_AUTH_PATHS)) {
            return $next($request);
        }
        abort_unless(Stealth::visible($feature, $request), 404);

        return $next($request);
    }
}
