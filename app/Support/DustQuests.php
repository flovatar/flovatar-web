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
    public static function enabled(): bool
    {
        return (bool) config('quests.enabled') && PasskeyLogin::enabled() && PasskeyPoc::isTestnet();
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
