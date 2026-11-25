<?php

namespace App\Services\Passkey;

use App\Models\PasskeyAccount;
use App\Models\PasskeyCredential;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * DB-loss recovery: sign-in with a passkey the database doesn't know.
 *
 * The site DB only maps credential id -> Flow address/key; the keys live on chain. From one sign-in assertion:
 *  1. recover the P-256 public key candidates from the ECDSA signature over authenticatorData || SHA-256(clientDataJSON)
 *  2. look each candidate up in the Flow key indexer of the configured network
 *  3. confirm on chain: the account really holds that key, not revoked, weight >= 1000, ECDSA_P256 / SHA2_256
 *  4. verify the full WebAuthn assertion (challenge, origin, rpIdHash, UV, signature) with that key ($verify callback)
 *  5. re-create the passkey_accounts / passkey_credentials rows (email unknown -> the user is asked to verify it again).
 *
 * Proof = a fresh signature over OUR challenge by a passkey for OUR rp_id whose key controls the account. Nothing is
 * trusted from the client except the assertion itself.
 */
class AccountRecovery
{
    public function __construct(
        protected FlowGateway $flow,
        protected KeyIndexer $indexer,
    ) {
    }

    public static function make(): self
    {
        return new self(new FlowGateway, new KeyIndexer);
    }

    /**
     * Find the account + key for an unknown credential. Returns ['address', 'key_index', 'public_key'] or null.
     *
     * @param  callable(string $publicKeyHex): void  $verify  full WebAuthn check with this key; throws when invalid
     */
    public function locate(string $authenticatorData, string $clientDataJSON, string $derSignature, callable $verify): ?array
    {
        $message = $authenticatorData.hash('sha256', $clientDataJSON, true);
        $raw = PasskeyCrypto::derToRaw($derSignature);
        $hits = [];
        foreach (P256::recoverPublicKeys($message, $raw) as $candidate) {
            foreach ($this->indexer->accounts($candidate) as $a) {
                if (! $a['revoked'] && $a['weight'] >= 1000 && $a['signing'] === 'ECDSA_P256' && $a['hashing'] === 'SHA2_256') {
                    $hits[] = ['address' => strtolower($a['address']), 'public_key' => $candidate];
                }
            }
        }
        if (! $hits) {
            return null;
        }
        // an address we already know (partial loss: account row survived) wins; otherwise the first indexed account
        usort($hits, fn ($x, $y) => (int) PasskeyAccount::where('address', $y['address'])->exists() <=> (int) PasskeyAccount::where('address', $x['address'])->exists());
        foreach ($hits as $hit) {
            try {
                $chain = $this->flow->account($hit['address']);
            } catch (Throwable $e) {
                Log::warning('passkey-recovery: chain read failed for '.$hit['address'].': '.$e->getMessage());

                continue;
            }
            foreach ($chain['keys'] as $k) {
                if ($k['public_key'] === $hit['public_key'] && ! $k['revoked'] && $k['weight'] >= 1000
                    && $k['signing_algorithm'] === 'ECDSA_P256' && $k['hashing_algorithm'] === 'SHA2_256') {
                    $verify($hit['public_key']);

                    return ['address' => $hit['address'], 'key_index' => $k['index'], 'public_key' => $hit['public_key']];
                }
            }
        }

        return null;
    }

    /** Re-create (or complete) the account + credential rows for a located key. */
    public function restore(array $found, string $credentialId, ?string $userHandleHex, string $label, string $ip): PasskeyCredential
    {
        return DB::transaction(function () use ($found, $credentialId, $userHandleHex, $label, $ip) {
            if (PasskeyCredential::where('credential_id', $credentialId)->exists()) {
                throw new RuntimeException('This passkey is already registered');
            }
            $account = PasskeyAccount::where('address', $found['address'])->first();
            if (! $account) {
                $account = PasskeyAccount::create([
                    'address' => $found['address'], 'email' => null, 'status' => 'active', 'created_ip' => $ip, 'recovered_at' => now(),
                ]);
            } elseif ($account->status !== 'active') {
                $account->update(['status' => 'active', 'recovered_at' => now()]);
            }
            // a stale row for the same key (e.g. old credential id) is retired, the new mapping is authoritative
            PasskeyCredential::where('passkey_account_id', $account->id)->where('public_key', $found['public_key'])->update(['status' => 'replaced']);
            $cred = PasskeyCredential::create([
                'passkey_account_id' => $account->id, 'credential_id' => $credentialId, 'public_key' => $found['public_key'],
                'key_index' => $found['key_index'], 'user_handle' => (string) ($userHandleHex ?? ''), 'aaguid' => null,
                'label' => mb_substr($label, 0, 120), 'backed_up' => false, 'status' => 'active', 'last_used_at' => now(),
            ]);
            Log::info('passkey-recovery: re-created mapping from chain', ['address' => $found['address'], 'key_index' => $found['key_index'], 'new_account_row' => $account->wasRecentlyCreated]);

            return $cred;
        });
    }
}
