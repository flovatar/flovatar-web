<?php

namespace App\Services\Passkey;

use App\Mail\PasskeyOtpMail;
use App\Models\PasskeyEmailCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Email one-time codes for passkey accounts (sign-up and "verify your email").
 *
 *  - 6 random digits, stored only as HMAC-SHA256(app key, email|purpose|code), single use, TTL 10 min (config).
 *  - max 5 wrong attempts per code, then it is burnt; only the newest code of an address is valid.
 *  - resend cooldown 60 s per address, max 5 sends per address per hour, max 20 per IP (IPv6: per /64) per hour,
 *    verify attempts per IP limited too.
 *  - Responses are generic: the same "we sent a code" for every valid address (also when it already has an account),
 *    so the endpoint can't be used to find out who has an account. Account existence is only revealed AFTER the code
 *    was entered (= the caller controls the mailbox).
 *  - TESTNET AUTOMATION: with passkey.email.test_read on testnet, the plaintext code of @resend.dev sink addresses is
 *    kept 10 min (encrypted, cache) for `php artisan passkey:test-otp` — never on mainnet or for other domains.
 */
class EmailVerification
{
    public const PURPOSES = ['signup', 'verify'];

    public static function normalise(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    /** Null when valid, else a user-facing message. */
    public static function validationError(string $email): ?string
    {
        if ($email === '') {
            return 'Please enter your email address to create your account.';
        }
        if (strlen($email) > 190 || ! filter_var($email, FILTER_VALIDATE_EMAIL) || validator(['e' => $email], ['e' => 'email:rfc'])->fails()) {
            return 'That doesn\'t look like a valid email address. Please check it and try again.';
        }

        return null;
    }

    /** Rate-limit bucket for an IP: IPv4 as is, IPv6 by /64 (one customer usually owns a whole /64). */
    public static function ipBucket(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $bin = inet_pton($ip);

            return bin2hex(substr($bin, 0, 8)).'::/64';
        }

        return $ip;
    }

    protected static function hash(string $email, string $purpose, string $code): string
    {
        return hash_hmac('sha256', $email.'|'.$purpose.'|'.$code, (string) config('app.key'));
    }

    protected static function key(string $kind, string $value): string
    {
        return 'passkey-otp:'.$kind.':'.hash('sha256', $value);
    }

    public static function testReadEnabled(string $email): bool
    {
        return (bool) config('passkey.email.test_read') && PasskeyPoc::isTestnet() && str_ends_with($email, '@resend.dev');
    }

    /**
     * Send a new code. Returns ['ok' => true, 'cooldown' => s, 'expires_in' => s] or
     * ['ok' => false, 'status' => 429|502, 'error' => msg, 'retry_after' => s].
     */
    public function start(string $email, string $purpose, string $ip, ?int $accountId = null): array
    {
        $cfg = (array) config('passkey.email');
        $cooldownKey = self::key('cooldown', $email);
        $emailKey = self::key('email', $email);
        $ipKey = self::key('ip', self::ipBucket($ip));
        foreach ([[$cooldownKey, 1, (int) $cfg['resend_cooldown']], [$emailKey, (int) $cfg['sends_per_email_per_hour'], 3600], [$ipKey, (int) $cfg['sends_per_ip_per_hour'], 3600]] as [$key, $max, $decay]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $wait = RateLimiter::availableIn($key);

                return ['ok' => false, 'status' => 429, 'retry_after' => $wait, 'error' => $wait <= 120
                    ? 'Please wait '.$wait.' seconds before asking for another code.'
                    : 'Too many codes requested. Please try again in '.ceil($wait / 60).' minutes.'];
            }
        }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        PasskeyEmailCode::where('email', $email)->where('purpose', $purpose)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        $row = PasskeyEmailCode::create([
            'email' => $email, 'purpose' => $purpose, 'passkey_account_id' => $accountId, 'code_hash' => self::hash($email, $purpose, $code),
            'attempts' => 0, 'ip' => $ip, 'expires_at' => now()->addSeconds((int) $cfg['code_ttl']),
        ]);
        try {
            Mail::to($email)->send(new PasskeyOtpMail($code, $purpose, (int) ceil(((int) $cfg['code_ttl']) / 60)));
        } catch (Throwable $e) {
            $row->update(['consumed_at' => now()]);
            Log::warning('passkey-otp: send failed: '.$e->getMessage());

            return ['ok' => false, 'status' => 502, 'retry_after' => 30, 'error' => 'We couldn\'t send the email right now. Please try again in a minute.'];
        }
        RateLimiter::hit($cooldownKey, (int) $cfg['resend_cooldown']);
        RateLimiter::hit($emailKey, 3600);
        RateLimiter::hit($ipKey, 3600);
        if (self::testReadEnabled($email)) {
            Cache::put('passkey:test-otp:'.hash('sha256', $email), encrypt($code), (int) $cfg['code_ttl']);
        }
        Log::info('passkey-otp: code sent', ['purpose' => $purpose, 'email_sha' => substr(hash('sha256', $email), 0, 12), 'ip' => self::ipBucket($ip)]);

        return ['ok' => true, 'cooldown' => (int) $cfg['resend_cooldown'], 'expires_in' => (int) $cfg['code_ttl']];
    }

    /** Check a code. Returns ['ok' => true] or ['ok' => false, 'status' => 422|429, 'error' => msg]. */
    public function verify(string $email, string $purpose, string $code, string $ip): array
    {
        $cfg = (array) config('passkey.email');
        $ipKey = self::key('verify-ip', self::ipBucket($ip));
        if (RateLimiter::tooManyAttempts($ipKey, (int) $cfg['verifies_per_ip_per_hour'])) {
            return ['ok' => false, 'status' => 429, 'error' => 'Too many attempts. Please try again later.'];
        }
        RateLimiter::hit($ipKey, 3600);
        $code = preg_replace('/\D/', '', $code);
        $row = PasskeyEmailCode::where('email', $email)->where('purpose', $purpose)->whereNull('consumed_at')->latest('id')->first();
        $generic = 'This code is not valid any more. Please ask for a new one.';
        if (! $row || $row->expires_at->isPast()) {
            return ['ok' => false, 'status' => 422, 'error' => $generic, 'expired' => true];
        }
        if (strlen($code) !== 6 || ! hash_equals($row->code_hash, self::hash($email, $purpose, $code))) {
            $row->increment('attempts');
            $left = (int) $cfg['max_attempts'] - $row->attempts;
            if ($left <= 0) {
                $row->update(['consumed_at' => now()]);

                return ['ok' => false, 'status' => 422, 'error' => 'Too many wrong codes. Please ask for a new one.', 'expired' => true];
            }

            return ['ok' => false, 'status' => 422, 'error' => 'That code is not right. '.$left.' '.($left === 1 ? 'try' : 'tries').' left.'];
        }
        $row->update(['verified_at' => now(), 'consumed_at' => now()]);
        Cache::forget('passkey:test-otp:'.hash('sha256', $email));

        return ['ok' => true];
    }

    /** TESTNET AUTOMATION ONLY (see class doc): last code for a @resend.dev sink address, or null. */
    public static function peekTestCode(string $email): ?string
    {
        $email = self::normalise($email);
        if (! self::testReadEnabled($email)) {
            return null;
        }
        $v = Cache::get('passkey:test-otp:'.hash('sha256', $email));

        return $v ? decrypt($v) : null;
    }
}
