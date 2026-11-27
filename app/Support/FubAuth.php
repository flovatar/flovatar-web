<?php

namespace App\Support;

use App\Models\FubDraft;
use Illuminate\Support\Facades\Auth;

/**
 * Who is acting on FUB drafts.
 *  - Studio (browser): the wallet signed in with FCL account-proof (users.email holds the address, AuthController).
 *  - Assistant (server-to-server): FUB_TOOL_TOKEN bearer + a draft grant the OWNER minted in the studio. A grant binds
 *    {address, draft, epoch, expiry} and is HMAC-signed with a key derived from APP_KEY, so the assistant server can't
 *    forge one for another wallet or draft; "Disconnect assistant" bumps the epoch and kills every grant of the draft.
 */
class FubAuth
{
    public const GRANT_TTL = 43200; // 12 h

    public static function address(): ?string
    {
        if (! Auth::check()) {
            return null;
        }
        $a = strtolower((string) Auth::user()->email);

        return FlowAccountProof::isValidAddress($a) ? Fub::normalizeAddress($a) : null;
    }

    private static function key(): string
    {
        return hash_hmac('sha256', 'fub-assistant-grant-v1', (string) config('app.key'), true);
    }

    private static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    public static function mintGrant(FubDraft $d, ?int $ttl = null): array
    {
        $exp = time() + ($ttl ?? self::GRANT_TTL);
        $payload = self::b64(json_encode(['a' => strtolower($d->owner_address), 'd' => $d->id, 'e' => $exp,
            'p' => (int) $d->setting('grant_epoch', 0), 'n' => bin2hex(random_bytes(6))]));

        return ['grant' => 'fg1.'.$payload.'.'.self::b64(hash_hmac('sha256', $payload, self::key(), true)), 'expires' => $exp];
    }

    /** -> [address, draft] or null (bad signature, expired, revoked epoch, deleted draft, owner changed). */
    public static function verifyGrant(?string $grant): ?array
    {
        if (! is_string($grant) || ! preg_match('/^fg1\.([A-Za-z0-9_-]{10,400})\.([A-Za-z0-9_-]{43})$/', $grant, $m)) {
            return null;
        }
        if (! hash_equals(self::b64(hash_hmac('sha256', $m[1], self::key(), true)), $m[2])) {
            return null;
        }
        $p = json_decode(base64_decode(strtr($m[1], '-_', '+/')), true);
        if (! is_array($p) || (int) ($p['e'] ?? 0) < time()) {
            return null;
        }
        $d = FubDraft::find((int) ($p['d'] ?? 0));
        if (! $d || $d->status === 'abandoned' || ! $d->isOwnedBy($p['a'] ?? null) || (int) $d->setting('grant_epoch', 0) !== (int) ($p['p'] ?? -1)) {
            return null;
        }

        return [Fub::normalizeAddress($p['a']), $d, (int) $p['e']];
    }

    public static function revokeGrants(FubDraft $d): void
    {
        $s = $d->settings ?? [];
        $s['grant_epoch'] = (int) ($s['grant_epoch'] ?? 0) + 1;
        $d->settings = $s;
        $d->save();
    }

    public static function toolTokenOk(?string $bearer): bool
    {
        $t = (string) config('fub.tool_token', '');

        return strlen($t) >= 32 && is_string($bearer) && hash_equals($t, $bearer);
    }
}
