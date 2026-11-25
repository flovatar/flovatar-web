<?php

namespace App\Support;

use App\Models\PasskeyAccount;
use App\Models\User;
use App\Services\Passkey\PasskeyPoc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Site-wide passkey sign-in (testnet): "Sign in" offers Email + passkey or Wallet.
 *
 * enabled() = PASSKEY_LOGIN_ENABLED + PASSKEY_POC_ENABLED + testnet. When it is false nothing in the HTML, the JS
 * behaviour or the server changes (Blade prints the exact previous markup, the JS never sees window.PASSKEY_LOGIN and
 * resources/js/flow/fcl-tx.js forwards every mutate() to FCL).
 *
 * A passkey sign-in also logs the Laravel session in exactly like a wallet login (users.email = address, the fl_auth
 * marker cookie), so every server feature that checks Auth keeps working, and /login/nonce reports auth_type=passkey.
 */
class PasskeyLogin
{
    public const SESSION_ACCOUNT = 'passkey_poc.account';

    public const SESSION_CREDENTIAL = 'passkey_poc.credential';

    /**
     * Visible to this request: PASSKEY_LOGIN_ENABLED + PASSKEY_MODE on (or stealth + stealth gate; legacy
     * PASSKEY_POC_ENABLED) + a network the sponsor is ready for (testnet, or mainnet with the KMS sponsor configured:
     * PasskeyPoc::networkReady(); fail closed otherwise).
     */
    public static function enabled(): bool
    {
        return (bool) config('passkey.login_enabled') && Stealth::visible('passkey') && PasskeyPoc::networkReady();
    }

    /** Server side ready (mode stealth/on + network ready). Config only. */
    public static function active(): bool
    {
        return (bool) config('passkey.login_enabled') && Stealth::active('passkey') && PasskeyPoc::networkReady();
    }

    /** Alpine click expression for "Connect Wallet" buttons: the sign-in choice when enabled, FCL otherwise. */
    public static function connectAction(string $fallback = 'fcl.authenticate()'): string
    {
        return self::enabled() ? "\$dispatch('open-signin')" : $fallback;
    }

    /** window.PASSKEY_LOGIN (static-safe: no user data, same for every visitor). */
    public static function bootstrap(): array
    {
        return [
            'enabled' => true,
            'rpId' => (string) (config('passkey.rp_id') ?: request()->getHost()),
            'sponsor' => (string) config('passkey.sponsor.address'),
            'flowscan' => (string) config('passkey.flowscan'),
            'faucet' => (string) config('passkey.faucet'),
            'network' => (string) config('services.flow.network'),
        ];
    }

    /** Laravel login for a passkey address (same user row + marker cookie as a wallet login). */
    public static function loginSite(Request $request, string $address): void
    {
        $address = strtolower($address);
        $user = User::firstOrCreate(['email' => $address], ['password' => Hash::make(Str::random(32)), 'name' => $address]);
        if (Schema::hasColumn('users', 'auth_type') && $user->auth_type !== 'passkey') {
            $user->forceFill(['auth_type' => 'passkey'])->save();
        }
        Auth::login($user);
        $minutes = config('session.expire_on_close') ? 0 : (int) config('session.lifetime', 120);
        Cookie::queue(Cookie::make('fl_auth', '1', $minutes, '/', config('session.domain'), config('session.secure'), false, false, 'lax'));
    }

    public static function logoutSite(Request $request): void
    {
        Auth::logout();
        Cookie::queue(Cookie::forget('fl_auth', '/', config('session.domain')));
    }

    /** 'passkey' when the session's passkey account is the logged-in address, else 'wallet' (null when logged out). */
    public static function authType(?Request $request, ?string $address): ?string
    {
        if ($address === null) {
            return null;
        }
        if ($request && $request->hasSession() && ($id = $request->session()->get(self::SESSION_ACCOUNT))) {
            $account = PasskeyAccount::find($id);
            if ($account && $account->status === 'active' && strtolower((string) $account->address) === strtolower($address)) {
                return 'passkey';
            }
        }

        return 'wallet';
    }

    /** Is this address a passkey account created by us? (ĐUST quests eligibility marker.) */
    public static function isPasskeyAddress(string $address): bool
    {
        return PasskeyAccount::where('address', strtolower($address))->where('status', 'active')->exists();
    }
}
