<?php

namespace App\Http\Controllers;

use App\Support\Stealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * GET /__stealth/{secret}: sets the signed stealth cookie (fl_s, 30 days) and goes to the home page; ?off=1 clears it.
 * Anything else answers the normal 404 (wrong secret, no STEALTH_SECRET configured, no feature in stealth mode,
 * more than 10 tries per IP per 10 minutes) — never a 403/429 that would reveal the route.
 * The secret is never logged by the app. (Owner: bookmark the URL; rotate by changing STEALTH_SECRET.)
 */
class StealthController extends Controller
{
    public function enter(Request $request, string $token)
    {
        $limiterKey = 'stealth-enter:'.sha1((string) $request->ip());
        if (RateLimiter::tooManyAttempts($limiterKey, 10)) {
            abort(404);
        }
        if (! Stealth::anyStealth() || ! Stealth::secretMatches($token)) {
            RateLimiter::hit($limiterKey, 600);
            abort(404);
        }
        if ($request->boolean('off')) {
            return redirect('/')->withCookie(Stealth::forgetCookie())->header('Cache-Control', 'private, no-store');
        }
        $minutes = max(1, (int) config('stealth.secret_cookie_days', 30)) * 24 * 60;

        return redirect('/')
            ->withCookie(Stealth::makeCookie('s', null, $minutes))
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
