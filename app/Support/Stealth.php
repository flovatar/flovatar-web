<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;
use Throwable;

/**
 * Stealth launch gate (config/stealth.php, flovatar-kb notes/mainnet-launch-stealth.md).
 *
 *  mode($f)      off | stealth | on (per feature; unset = legacy *_ENABLED flag)
 *  active($f)    mode != off: routes registered, background work (indexing) runs — config only, never request-based
 *  visible($f)   on, or stealth + gated(request): what Blade/controllers/public queries use
 *  gated($req)   valid signed fl_s cookie, or a WALLET session of an address in STEALTH_ADMIN_ADDRESSES
 *
 * Console / static (headless) builds have no stealth cookie and no session → stealth features render exactly as "off",
 * so nothing reaches R2, the sitemap or the edge cache. Responses rendered for a gated request are marked private
 * (PublicCacheHeaders / PublicApiCache call markRendered()/wasRendered()).
 *
 * Cookie fl_s = base64url(json {k: "s"|"a", a?: address, e: expiry}) . "." . base64url(HMAC-SHA256), key = STEALTH_SECRET
 * (or a key derived from APP_KEY when no secret is set). HttpOnly, Secure, SameSite=Lax; excluded from EncryptCookies.
 * The headless Worker only checks that the cookie EXISTS (to send HTML to the origin instead of R2); the origin
 * verifies it. A forged cookie just gets the public page from the origin.
 */
class Stealth
{
    public const FEATURES = ['passkey', 'dust_market', 'quests', 'swap', 'terrakit', 'islands', 'fub', 'rewind'];

    public const MODES = ['off', 'stealth', 'on'];

    /** Legacy boolean flags (testnet .env) used when *_MODE is unset. */
    public const LEGACY = [
        'passkey' => 'passkey.enabled',
        'dust_market' => 'flovatar.dustMarket.enabled',
        'quests' => 'quests.enabled',
        'swap' => 'flovatar.dustSwap.enabled',
        'terrakit' => 'terrakit.check_enabled',
        'islands' => 'islands.legacy_enabled', // never set: ISLANDS_MODE only
        'fub' => 'fub.legacy_enabled',         // never set: FUB_MODE only
        'rewind' => 'pack_rewind.legacy_enabled', // never set: PACK_REWIND_MODE only
    ];

    /** Lazy JS/CSS bundle per feature (webpack chunk public/fx/<code>.js → storage/app/fx, served by FxAssetController). */
    public const BUNDLES = ['passkey' => 'p', 'dust_market' => 'm', 'swap' => 's', 'quests' => 'q', 'terrakit' => 't', 'islands' => 'i', 'fub' => 'u', 'rewind' => 'r'];

    /** Secondary chunks a feature bundle imports on demand (same gate, never listed in window.__FX): code => feature.
     *  b = the WebGL belt of /islands (three.js scene, loaded by the islands bundle only on that page). */
    public const LAZY_CHUNKS = ['b' => 'islands'];

    /** Paths owned by a feature: in stealth mode anonymous requests without a stealth cookie get a plain 404 before any
     *  session/throttle middleware runs (StealthRoutes). Request::is() patterns. */
    public const PATHS = [
        'passkey' => ['passkey', 'passkey/*', '.well-known/webauthn'],
        'quests' => ['quests', 'quests/*'],
        'terrakit' => ['terrakit/check', 'terrakit/check/*'],
        'islands' => ['islands', 'islands/*', 'api/islands', 'api/islands/*'],
        'fub' => ['fub/*', 'api/fub', 'api/fub/*', 'fub-content/*'], // not the bare /fub (the 2022 page stays public)
        'rewind' => ['pack-rewind', 'pack-rewind/*', 'api/pack-rewind', 'api/pack-rewind/*'],
    ];

    /** Server-to-server paths that authenticate themselves (HMAC) and must work in stealth mode. */
    public const SELF_AUTH_PATHS = ['quests/payout/*', 'api/fub-tools/*'];

    private const ATTR_GATED = 'stealth.gated';

    private const ATTR_RENDERED = 'stealth.rendered';

    public static function mode(string $feature): string
    {
        // Pack Rewind re-sells testnet copies of every pack: never on any other network, whatever the env says.
        if ($feature === 'rewind' && (string) config('services.flow.network') !== 'testnet') {
            return 'off';
        }
        $raw = config("stealth.features.$feature");
        if ($raw === null || $raw === '') {
            $legacy = self::LEGACY[$feature] ?? null;

            return $legacy && config($legacy) ? 'on' : 'off';
        }
        $m = strtolower(trim((string) $raw));

        return in_array($m, self::MODES, true) ? $m : 'off';
    }

    public static function active(string $feature): bool
    {
        return self::mode($feature) !== 'off';
    }

    public static function isStealth(string $feature): bool
    {
        return self::mode($feature) === 'stealth';
    }

    public static function anyStealth(): bool
    {
        foreach (self::FEATURES as $f) {
            if (self::isStealth($f)) {
                return true;
            }
        }

        return false;
    }

    public static function visible(string $feature, ?Request $request = null): bool
    {
        $mode = self::mode($feature);
        if ($mode === 'on') {
            return true;
        }
        if ($mode !== 'stealth') {
            return false;
        }
        $request ??= self::currentRequest();
        if (! $request || ! self::gated($request)) {
            return false;
        }
        $request->attributes->set(self::ATTR_RENDERED, true);

        return true;
    }

    /** Did this request use a stealth-only feature? Then its response must never be shared (edge cache). */
    public static function wasRendered(Request $request): bool
    {
        return (bool) $request->attributes->get(self::ATTR_RENDERED, false)
            || (self::anyStealth() && self::gated($request));
    }

    /** Lazy bundle codes for the features visible to this request (layout prints them as window.__FX). */
    public static function bundles(?Request $request = null): array
    {
        $out = [];
        foreach (self::BUNDLES as $feature => $code) {
            if (self::visible($feature, $request)) {
                $out[] = $code;
            }
        }

        return $out;
    }

    /**
     * Shared (Redis) cache key for data that may differ for stealth requests (ĐUST listings in API/snapshots): gated
     * requests read/write their own variant, so a stealth answer can never be served to the public from the cache.
     */
    public static function cacheKey(string $key, ?Request $request = null): string
    {
        $request ??= self::currentRequest();

        return ($request && self::anyStealth() && self::gated($request)) ? $key.':stealth' : $key;
    }

    /** Cache-busting id of a lazy bundle file in storage/app/fx ('' when it is missing). */
    public static function assetVersion(string $file): string
    {
        $path = storage_path('app/fx/'.$file);

        return is_file($path) ? substr((string) md5_file($path), 0, 12) : '';
    }

    public static function featureForBundle(string $code): ?string
    {
        $f = array_search($code, self::BUNDLES, true);

        return $f === false ? (self::LAZY_CHUNKS[$code] ?? null) : $f;
    }

    // ------------------------------------------------------------------ gate

    public static function gated(Request $request): bool
    {
        if ($request->attributes->has(self::ATTR_GATED)) {
            return (bool) $request->attributes->get(self::ATTR_GATED);
        }
        $ok = self::cookiePayload($request) !== null || self::adminWalletSession($request) !== null;
        if ($ok || $request->hasSession()) {
            // (a "no" before the session started is not final: the admin wallet session may come later)
            $request->attributes->set(self::ATTR_GATED, $ok);
        }

        return $ok;
    }

    /** Address of a signed-in admin WALLET session (account-proof login), never a passkey session. */
    public static function adminWalletSession(Request $request): ?string
    {
        try {
            if (! $request->hasSession()) {
                return null;
            }
            $user = Auth::user();
        } catch (Throwable) {
            return null;
        }
        $address = $user ? strtolower((string) $user->email) : '';
        if ($address === '' || ! self::isAdminAddress($address)) {
            return null;
        }
        if (PasskeyLogin::authType($request, $address) === 'passkey') {
            return null;
        }

        return $address;
    }

    public static function isAdminAddress(?string $address): bool
    {
        $a = strtolower(trim((string) $address));

        return $a !== '' && in_array($a, (array) config('stealth.admin_addresses', []), true);
    }

    /** Does the request carry ANY fl_s cookie (valid or not)? Used before the session exists. */
    public static function hasCookie(Request $request): bool
    {
        return $request->cookies->has(self::cookieName());
    }

    public static function cookieName(): string
    {
        return (string) config('stealth.cookie', 'fl_s');
    }

    /** Verified cookie payload or null. */
    public static function cookiePayload(Request $request): ?array
    {
        $raw = (string) $request->cookies->get(self::cookieName(), '');
        if ($raw === '' || strlen($raw) > 512 || substr_count($raw, '.') !== 1) {
            return null;
        }
        [$body, $mac] = explode('.', $raw, 2);
        $expected = self::b64u(hash_hmac('sha256', $body, self::key(), true));
        if (! hash_equals($expected, $mac)) {
            return null;
        }
        $data = json_decode((string) self::b64uDecode($body), true);
        if (! is_array($data) || ! in_array($data['k'] ?? null, ['s', 'a'], true) || (int) ($data['e'] ?? 0) < time()) {
            return null;
        }
        if ($data['k'] === 'a' && ! self::isAdminAddress($data['a'] ?? null)) {
            return null; // address removed from the admin list since the cookie was issued
        }

        return $data;
    }

    public static function makeCookie(string $kind, ?string $address, int $minutes): SymfonyCookie
    {
        $payload = ['k' => $kind, 'e' => time() + $minutes * 60];
        if ($address) {
            $payload['a'] = strtolower($address);
        }
        $body = self::b64u(json_encode($payload));
        $value = $body.'.'.self::b64u(hash_hmac('sha256', $body, self::key(), true));

        return cookie(self::cookieName(), $value, $minutes, '/', config('session.domain'), (bool) (config('session.secure') ?? true), true, false, 'lax');
    }

    public static function forgetCookie(): SymfonyCookie
    {
        return cookie()->forget(self::cookieName(), '/', config('session.domain'));
    }

    /** Secret-URL check (constant time). Empty/short secret = disabled. */
    public static function secretMatches(string $candidate): bool
    {
        $secret = (string) config('stealth.secret', '');

        return strlen($secret) >= 32 && hash_equals($secret, $candidate);
    }

    private static function key(): string
    {
        $secret = (string) config('stealth.secret', '');
        if (strlen($secret) >= 32) {
            return hash_hmac('sha256', 'flovatar-stealth-cookie', $secret, true);
        }

        return hash_hmac('sha256', 'flovatar-stealth-cookie', (string) config('app.key'), true);
    }

    private static function currentRequest(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }
        $r = app('request');

        return $r instanceof Request ? $r : null;
    }

    private static function b64u(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private static function b64uDecode(string $s): string|false
    {
        return base64_decode(strtr($s, '-_', '+/'), true);
    }
}
