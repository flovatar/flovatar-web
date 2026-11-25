<?php

namespace App\Support;

use App\Services\Passkey\PasskeyPoc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ĐUST Quests switch + static-safe bootstrap (KB notes/dust-quests.md).
 *
 * enabled() = DUST_QUESTS_ENABLED + the passkey sign-in (PasskeyLogin::enabled(): PASSKEY_POC_ENABLED +
 * PASSKEY_LOGIN_ENABLED + testnet). Off: routes/quests.php is not loaded, Blade prints nothing, window.DUST_QUESTS is
 * absent (resources/js/quests.js stays inert) → production unchanged.
 */
class DustQuests
{
    /** Visible to this request: DUST_QUESTS_MODE on (or stealth + stealth gate) + passkey sign-in visible + payouts ready. */
    public static function enabled(): bool
    {
        return Stealth::visible('quests') && PasskeyLogin::enabled() && self::networkReady();
    }

    /** Server side ready (routes, payout worker API): mode stealth/on + passkey active + payouts ready. Config only. */
    public static function active(): bool
    {
        return Stealth::active('quests') && PasskeyLogin::active() && self::networkReady();
    }

    /**
     * Testnet, or mainnet once the separate rewards server exists (rewards wallet address + payout HMAC secret set).
     * Fail closed: without them quests stay off on mainnet whatever the mode says.
     */
    public static function networkReady(): bool
    {
        if (PasskeyPoc::isTestnet()) {
            return true;
        }

        return PasskeyPoc::isMainnet()
            && preg_match('/^(0x)?[0-9a-f]{16}$/i', (string) config('quests.payout.rewards_address'))
            && strlen((string) config('quests.payout.secret')) >= 32;
    }

    /** window.DUST_QUESTS (same for every visitor: no user data, safe for static/headless shells). */
    public static function bootstrap(): array
    {
        return [
            'enabled' => true,
            'cap' => (float) config('quests.season.per_user_cap'),
            'honorSeconds' => (int) config('quests.limits.honor_seconds'),
        ];
    }

    /** Wallet address of the signed-in admin (ADMIN_ADDRESSES) or null. Passkey sessions are never admins. */
    public static function adminAddress(?Request $request = null): ?string
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }
        $address = strtolower((string) $user->email);
        if (! in_array($address, config('quests.admin_addresses', []), true)) {
            return null;
        }
        if (PasskeyLogin::authType($request ?? request(), $address) === 'passkey') {
            return null;
        }

        return $address;
    }
}
