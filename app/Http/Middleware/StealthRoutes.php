<?php

namespace App\Http\Middleware;

use App\Support\Stealth;
use Closure;
use Illuminate\Http\Request;

/**
 * Global (runs before sessions and throttles): a stealth-mode feature's routes answer a plain 404 to requests that
 * carry neither a stealth cookie nor a session cookie, so anonymous visitors and crawlers can't tell the route exists
 * (no session cookie, no 429). Requests with a session go on to the controllers, which check Stealth::visible() and
 * 404 for non-admins. Self-authenticating worker endpoints (HMAC) are exempt.
 */
class StealthRoutes
{
    public function handle(Request $request, Closure $next)
    {
        foreach (Stealth::PATHS as $feature => $patterns) {
            if (! $request->is(...$patterns) || ! Stealth::isStealth($feature)) {
                continue;
            }
            if ($request->is(...Stealth::SELF_AUTH_PATHS)) {
                break;
            }
            if (Stealth::cookiePayload($request) === null && ! \App\Support\PublicCache::hasSessionCookie($request)) {
                abort(404);
            }
            break;
        }

        return $next($request);
    }
}
