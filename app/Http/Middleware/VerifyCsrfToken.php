<?php

namespace App\Http\Middleware;

use App\Support\PublicCache;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     * The read-only search endpoints (PublicCache::READONLY_POST_PATHS) change nothing,
     * so they need no token and anonymous visitors can use them without a session.
     *
     * @var array
     */
    protected $except = [
        '/graffle',
        'search',
        'showroom/search',
        'showroom/flobot/search',
        'showroom/dust-collectible/search',
        'transactions/search',
        'builder/search',
        'flobot/builder/search',
        // islands: re-read an island from the chain (no user state; the front end treats /api/* as CSRF-exempt)
        'api/islands/*/refresh',
    ];

    /**
     * Stateless requests (anonymous public pages) must not set the XSRF-TOKEN cookie:
     * the front end fetches a token from /csrf-token before its first state-changing request.
     */
    protected function addCookieToResponse($request, $response)
    {
        if (PublicCache::isStateless($request)) {
            return $response;
        }

        return parent::addCookieToResponse($request, $response);
    }
}
