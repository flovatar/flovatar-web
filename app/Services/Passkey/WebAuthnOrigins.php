<?php

namespace App\Services\Passkey;

use Illuminate\Http\Request;
use RuntimeException;

/**
 * Relying party id + accepted origins for the passkey endpoints.
 *
 * Headless setup: pages are served by the Cloudflare Worker on the frontend host (flovatar.com,
 * next-testnet.flovatar.com) and /passkey/* is proxied to the Laravel origin (api.flovatar.com, testnet.flovatar.com),
 * so the request host is NOT the page origin: rp_id comes from config, and every WebAuthn response is checked against
 * an exact allowlist of origins (clientDataJSON.origin), never a suffix match.
 *
 * Origins outside the rp_id's own domain tree use WebAuthn Related Origin Requests: the browser fetches
 * https://{rp_id}/.well-known/webauthn ({"origins": [...]}, served by wellKnown()) and allows the call when the page
 * origin is listed.
 */
final class WebAuthnOrigins
{
    public static function rpId(?Request $request = null): string
    {
        $rp = (string) config('passkey.rp_id');
        if ($rp !== '') {
            return strtolower($rp);
        }

        return strtolower((string) ($request ?? request())->getHost());
    }

    /** Exact allowed origins (lowercase, no trailing slash). Default: https://{rp_id}. */
    public static function allowed(?Request $request = null): array
    {
        $list = array_values(array_filter((array) config('passkey.origins', []), 'strlen'));

        return $list ?: ['https://'.self::rpId($request)];
    }

    public static function normalise(string $origin): string
    {
        return rtrim(strtolower(trim($origin)), '/');
    }

    public static function isAllowed(string $origin, ?Request $request = null): bool
    {
        return in_array(self::normalise($origin), self::allowed($request), true);
    }

    /** Throws unless clientDataJSON.origin is an allowed origin (call before lbuchs, whose check is a loose suffix match). */
    public static function assertClientData(string $clientDataJSON, ?Request $request = null): void
    {
        $client = json_decode($clientDataJSON, true);
        $origin = is_array($client) ? (string) ($client['origin'] ?? '') : '';
        if ($origin === '' || ! self::isAllowed($origin, $request)) {
            throw new RuntimeException('This passkey request came from a site we do not accept ('.substr($origin, 0, 80).')');
        }
    }

    /** Origins that need Related Origin Requests (not the rp_id host or one of its subdomains). */
    public static function related(?Request $request = null): array
    {
        $rp = self::rpId($request);

        return array_values(array_filter(self::allowed($request), function ($o) use ($rp) {
            $host = (string) parse_url($o, PHP_URL_HOST);

            return $host !== $rp && ! str_ends_with($host, '.'.$rp);
        }));
    }

    /** Body of https://{rp_id}/.well-known/webauthn. */
    public static function wellKnown(?Request $request = null): array
    {
        return ['origins' => self::allowed($request)];
    }
}
