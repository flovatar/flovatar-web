<?php

namespace App\Services\Passkey;

use App\Models\PasskeyAccount;
use App\Support\FlowAccountProof;
use RuntimeException;

/**
 * "Link to Flow Wallet / Dapper" (graduation) for passkey accounts, with Flow's HybridCustody:
 *  1. the browser connects the user's wallet with FCL (account-proof over a nonce we issued) -> verifyAccountProof()
 *  2. the passkey account publishes a ChildAccount capability to that wallet (cadence/passkey/hc_publish_to_parent.cdc,
 *     passkey-signed, sponsor pays; factories + an allow-all filter live in the child account itself)
 *  3. the wallet claims it (hc_redeem_account.cdc, wallet-signed, wallet pays its own fee)
 *  4. the server confirms on chain (status()) and records the link (passkey_wallet_links, ĐUST quests reward it once).
 *
 * Why a child-account link and not HybridCustody "ownership": giveOwnership() seals the account (revokes every key,
 * incl. the passkeys) -> passkey sign-in would stop working. The child link gives the wallet access to every NFT
 * collection and token vault (allow-all filter, provider factories) while the keys stay untouched.
 */
class WalletLink
{
    public function __construct(protected PasskeyPoc $poc)
    {
    }

    public static function make(): self
    {
        return new self(PasskeyPoc::make());
    }

    public static function nonce(): string
    {
        return bin2hex(random_bytes(32));
    }

    /** FCL account-proof check (FCLCrypto on chain) for any of the accepted app identifiers (= page origins). */
    public function verifyAccountProof(string $address, string $nonce, array $signatures, array $appIdentifiers): bool
    {
        if (! FlowAccountProof::isValidAddress($address) || ! FlowAccountProof::isValidNonce($nonce)) {
            return false;
        }
        $keyIds = [];
        $sigs = [];
        foreach (array_slice($signatures, 0, 10) as $sig) {
            $k = $sig['keyId'] ?? null;
            $s = strtolower((string) ($sig['signature'] ?? ''));
            if (! (is_int($k) || (is_string($k) && ctype_digit($k))) || (int) $k > 1000 || ! preg_match('/^[0-9a-f]{128}$/', $s)) {
                return false;
            }
            $keyIds[] = ['type' => 'UInt64', 'value' => (string) (int) $k];
            $sigs[] = ['type' => 'String', 'value' => $s];
        }
        if (! $sigs) {
            return false;
        }
        foreach ($appIdentifiers as $app) {
            $message = FlowAccountProof::encodeMessage($app, $address, $nonce);
            $ok = $this->poc->script('verify_account_proof', [
                ['type' => 'Address', 'value' => $address],
                ['type' => 'String', 'value' => $message],
                ['type' => 'Array', 'value' => $keyIds],
                ['type' => 'Array', 'value' => $sigs],
            ]);
            if ($ok === true) {
                return true;
            }
        }

        return false;
    }

    /** ['parents' => [address => redeemed bool], 'managerHasChild' => bool] */
    public function status(string $child, ?string $parent = null): array
    {
        $r = $this->poc->script('hc_link_status', [
            ['type' => 'Address', 'value' => '0x'.FlowTransaction::addr($child)],
            ['type' => 'Optional', 'value' => $parent ? ['type' => 'Address', 'value' => '0x'.FlowTransaction::addr($parent)] : null],
        ]);
        $parents = [];
        foreach ((array) ($r['parents'] ?? []) as $addr => $redeemed) {
            $parents['0x'.FlowTransaction::addr((string) $addr)] = (bool) $redeemed;
        }

        return ['parents' => $parents, 'managerHasChild' => (bool) ($r['managerHasChild'] ?? false)];
    }

    /** Arguments of the passkey-signed publish transaction. */
    public static function publishArgs(string $parent): array
    {
        return [
            ['type' => 'Address', 'value' => '0x'.FlowTransaction::addr($parent)],
            ['type' => 'String', 'value' => (string) config('passkey.link.display_name')],
            ['type' => 'String', 'value' => (string) config('passkey.link.display_description')],
            ['type' => 'String', 'value' => (string) config('passkey.link.thumbnail')],
        ];
    }

    /** Cadence the wallet signs to claim the link (addresses resolved for the configured network). */
    public static function claimCadence(): string
    {
        return PasskeyPoc::cadence('hc_redeem_account');
    }

    public static function assertLinkable(PasskeyAccount $account, string $parent): void
    {
        $parent = '0x'.FlowTransaction::addr($parent);
        if (strtolower($parent) === strtolower((string) $account->address)) {
            throw new RuntimeException('Connect a different wallet: this is your passkey account itself.');
        }
        if (PasskeyAccount::where('address', strtolower($parent))->where('status', 'active')->exists()) {
            throw new RuntimeException('That address is a passkey account too. Connect your Flow Wallet or Dapper wallet instead.');
        }
    }
}
