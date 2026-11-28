<?php

namespace App\Services\Passkey;

use App\Models\PasskeyAccount;
use App\Models\PasskeyFlowGrant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * Starting FLOW for NEW passkey accounts on TESTNET (Pack Rewind: enough FLOW to replay the packs). KB notes/pack-rewind.md.
 *
 * Guards (all in config/passkey.php 'flow_grant', env PASSKEY_FLOW_GRANT_*):
 *  - kill switch `enabled` (default false) + testnet only (never on any other network, whatever the env says)
 *  - once per passkey account (unique row in passkey_flow_grants; a failed send can be retried by the same account)
 *  - only accounts created after `since` (unix time; 0 = any) — "new" accounts
 *  - per IP bucket (IPv6 /64) per day and per device (signed long-lived cookie + user agent) per day
 *  - daily cap on the total FLOW granted (UTC day) and a sponsor reserve the grant may never dip into
 * The FLOW is sent by the sponsor with the existing allowlisted sponsor_topup_flow transaction (kind 'flow_grant').
 */
class FlowGrant
{
    public const DEVICE_COOKIE = 'fl_dev';

    /** @var callable|null fn(PasskeyAccount $a, string $amount): array{0:string,1:array} — tests inject a fake sender */
    private $sender;

    /** @var callable|null fn(): float — sponsor FLOW balance (tests) */
    private $balance;

    public function __construct(?callable $sender = null, ?callable $balance = null)
    {
        $this->sender = $sender;
        $this->balance = $balance;
    }

    public static function enabled(): bool
    {
        return (bool) config('passkey.flow_grant.enabled') && (string) config('services.flow.network') === 'testnet';
    }

    public static function amount(): string
    {
        return PasskeyPoc::ufix(number_format((float) config('passkey.flow_grant.amount', 150), 8, '.', ''));
    }

    public static function deviceHash(Request $request): ?string
    {
        $dev = (string) $request->cookie(self::DEVICE_COOKIE, '');
        if ($dev === '' && ! $request->userAgent()) {
            return null;
        }

        return hash('sha256', $dev.'|'.(string) $request->userAgent());
    }

    /** Status for the UI: ['enabled', 'amount', 'state' => none|sent|pending|failed|ineligible, 'txId'] */
    public static function status(?PasskeyAccount $account): array
    {
        $g = $account ? PasskeyFlowGrant::where('passkey_account_id', $account->id)->first() : null;
        $state = $g ? $g->status : 'none';
        if (! $g && $account && ! self::isNew($account)) {
            $state = 'ineligible';
        }

        return ['enabled' => self::enabled(), 'amount' => (float) self::amount(), 'state' => $state, 'txId' => $g?->tx_id];
    }

    public static function isNew(PasskeyAccount $account): bool
    {
        $since = (int) config('passkey.flow_grant.since', 0);

        return $since <= 0 || ($account->created_at && $account->created_at->getTimestamp() >= $since);
    }

    /**
     * Grant the starting FLOW once. Returns the grant row (status sent) or throws RuntimeException with a user-facing
     * reason. Safe to call twice (second call returns the existing sent row).
     */
    public function grant(PasskeyAccount $account, Request $request): PasskeyFlowGrant
    {
        if (! self::enabled()) {
            throw new RuntimeException('The starting FLOW is switched off right now.');
        }
        if (! $account->address || $account->status !== 'active') {
            throw new RuntimeException('Your account is not ready yet.');
        }
        if (! self::isNew($account)) {
            throw new RuntimeException('The starting FLOW is for new accounts only.');
        }
        $lock = Cache::lock('passkey:flow-grant:'.$account->id, 120);
        if (! $lock->get()) {
            throw new RuntimeException('Your starting FLOW is already on its way.');
        }
        $global = Cache::lock('passkey:flow-grant:global', 120);
        try {
            $existing = PasskeyFlowGrant::where('passkey_account_id', $account->id)->first();
            if ($existing && in_array($existing->status, ['sent', 'pending'], true)) {
                return $existing;
            }
            $amount = self::amount();
            $ip = EmailVerification::ipBucket((string) $request->ip());
            $device = self::deviceHash($request);
            $today = now()->startOfDay();
            $cfg = (array) config('passkey.flow_grant');
            $ipCount = PasskeyFlowGrant::where('ip_bucket', $ip)->whereIn('status', ['sent', 'pending'])->where('created_at', '>=', now()->subDay())->count();
            if ($ipCount >= (int) ($cfg['per_ip_per_day'] ?? 2)) {
                throw new RuntimeException('Too many new accounts from your network today: try again tomorrow.');
            }
            if ($device) {
                $devCount = PasskeyFlowGrant::where('device_hash', $device)->whereIn('status', ['sent', 'pending'])->where('created_at', '>=', now()->subDay())->count();
                if ($devCount >= (int) ($cfg['per_device_per_day'] ?? 2)) {
                    throw new RuntimeException('This device already received starting FLOW today.');
                }
            }
            if (! $global->block(30)) {
                throw new RuntimeException('Busy, please retry in a moment.');
            }
            $todayTotal = (float) PasskeyFlowGrant::whereIn('status', ['sent', 'pending'])->where('created_at', '>=', $today)->sum('amount');
            if ($todayTotal + (float) $amount > (float) ($cfg['daily_cap'] ?? 3000)) {
                throw new RuntimeException('Today\'s starting-FLOW budget is used up: try again tomorrow.');
            }
            $sponsorBalance = $this->balance ? ($this->balance)() : (float) (PasskeyPoc::make()->flow()->account(PasskeyPoc::sponsorAddress())['balance'] ?? 0);
            if ($sponsorBalance - (float) $amount < (float) ($cfg['sponsor_reserve'] ?? 200)) {
                throw new RuntimeException('The faucet is empty right now: try again later.');
            }
            $row = $existing ?? new PasskeyFlowGrant(['passkey_account_id' => $account->id]);
            $row->fill(['address' => $account->address, 'amount' => $amount, 'ip_bucket' => $ip, 'device_hash' => $device, 'status' => 'pending', 'error' => null]);
            $row->save();
            $global->release();
            try {
                [$txId, $r] = $this->sender
                    ? ($this->sender)($account, $amount)
                    : PasskeyPoc::make()->sendAsSponsor('sponsor_topup_flow', [
                        ['type' => 'Address', 'value' => $account->address],
                        ['type' => 'UFix64', 'value' => $amount],
                    ], 'flow_grant', $account->id);
            } catch (Throwable $e) {
                $row->update(['status' => 'failed', 'error' => substr($e->getMessage(), 0, 300)]);
                throw new RuntimeException('Sending your starting FLOW failed, please retry.');
            }
            if (($r['error'] ?? '') !== '') {
                $row->update(['status' => 'failed', 'tx_id' => $txId, 'error' => substr((string) $r['error'], 0, 300)]);
                throw new RuntimeException('Sending your starting FLOW failed, please retry.');
            }
            $row->update(['status' => 'sent', 'tx_id' => $txId]);

            return $row;
        } finally {
            optional($global)->release();
            $lock->release();
        }
    }
}
