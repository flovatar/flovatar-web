<?php

namespace App\Services\Passkey;

use App\Models\PasskeyAccount;
use App\Models\PasskeyCredential;
use App\Models\PasskeyTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Passkey PoC orchestration (TESTNET ONLY):
 *  - sponsor-only transactions (account creation, starter gift): sponsor = proposer + payer + authorizer
 *  - user transactions: passkey key = proposer + authorizer (payload signature with FLIP-264 extension data),
 *    sponsor = payer (envelope signature) -> gasless for the user.
 */
class PasskeyPoc
{
    public const TX_TTL = 300;

    public function __construct(
        protected FlowGateway $flow,
        protected SponsorSigner $sponsor,
    ) {
    }

    public static function make(): self
    {
        return new self(new FlowGateway, SponsorSigner::fromConfig());
    }

    public static function isTestnet(): bool
    {
        return config('services.flow.network') === 'testnet'
            && ! str_contains((string) config('services.flow.rest'), 'mainnet');
    }

    public function flow(): FlowGateway
    {
        return $this->flow;
    }

    public static function sponsorAddress(): string
    {
        $a = (string) config('passkey.sponsor.address');
        if ($a === '') {
            throw new RuntimeException('Sponsor not configured (PASSKEY_SPONSOR_ADDRESS)');
        }

        return '0x'.FlowTransaction::addr($a);
    }

    /** Cadence source from cadence/passkey/<name>.cdc with the testnet contract addresses filled in. */
    public static function cadence(string $name): string
    {
        if (! preg_match('/^[a-z_]+$/', $name)) {
            throw new RuntimeException('Bad cadence name');
        }
        $code = file_get_contents(base_path("cadence/passkey/{$name}.cdc"));
        $map = config('passkey.contracts');

        return preg_replace_callback('/\b0x(Flovatar|FungibleToken|NonFungibleToken|MetadataViews|FlowToken)\b/', fn ($m) => $map[$m[1]], $code);
    }

    public function script(string $name, array $args = [])
    {
        return $this->flow->script(self::cadence($name), $args);
    }

    // ------------------------------------------------------------------ sponsor-only transactions

    /**
     * Send a transaction signed only by the sponsor and wait for it to seal. Serialised with a lock because the
     * sponsor's single key is the proposal key (sequence number). Returns [txId, result].
     */
    public function sendAsSponsor(string $cadence, array $args, string $kind, ?int $accountId = null): array
    {
        $sponsor = self::sponsorAddress();
        $keyIndex = (int) config('passkey.sponsor.key_index');

        return Cache::lock('passkey:sponsor-proposer', 90)->block(60, function () use ($sponsor, $keyIndex, $cadence, $args, $kind, $accountId) {
            $tx = new FlowTransaction(
                self::cadence($cadence), $args, $this->flow->latestSealedBlockId(), (int) config('passkey.compute_limit'),
                $sponsor, $keyIndex, $this->flow->sequenceNumber($sponsor, $keyIndex), $sponsor, [$sponsor],
            );
            $tx->addEnvelopeSignature($sponsor, $keyIndex, $this->sponsor->sign($tx->envelopeMessage()));
            $id = $this->flow->send($tx);
            $log = PasskeyTransaction::create(['passkey_account_id' => $accountId, 'kind' => $kind, 'tx_id' => $id, 'signed_by' => 'sponsor']);
            $result = $this->flow->wait($id, 45);
            $log->update(['status' => $result['error'] !== '' ? 'Failed' : $result['status'], 'error' => $result['error'] ?: null]);

            return [$id, $result];
        });
    }

    /** Create a Flow account whose only key is $publicKeyHex. Returns [address, txId]. */
    public function createAccount(string $publicKeyHex, ?int $accountId = null): array
    {
        [$id, $r] = $this->sendAsSponsor('create_account', [
            ['type' => 'String', 'value' => $publicKeyHex],
            ['type' => 'UFix64', 'value' => self::ufix((string) config('passkey.account_fund'))],
        ], 'create_account', $accountId);
        if ($r['error'] !== '') {
            throw new RuntimeException('Account creation failed: '.substr($r['error'], 0, 300));
        }
        foreach ($r['events'] as $e) {
            if ($e['type'] === 'flow.AccountCreated') {
                return ['0x'.FlowTransaction::addr((string) ($e['values']['address'] ?? '')), $id];
            }
        }
        throw new RuntimeException('Account creation sealed without an AccountCreated event (tx '.$id.')');
    }

    // ------------------------------------------------------------------ user (passkey-signed) transactions

    /**
     * Build a transaction for the passkey user and return what the browser needs for navigator.credentials.get():
     * ['token', 'challenge' (base64url of SHA2-256(payload message)), 'credentialId'].
     */
    public function prepareUserTx(PasskeyAccount $account, PasskeyCredential $signer, string $kind, string $cadence, array $args, array $meta = []): array
    {
        if ($signer->status !== 'active' || $signer->key_index === null || (int) $signer->passkey_account_id !== (int) $account->id) {
            throw new RuntimeException('This passkey cannot sign for the account yet');
        }
        $address = $account->address;
        $tx = new FlowTransaction(
            self::cadence($cadence), $args, $this->flow->latestSealedBlockId(), (int) config('passkey.compute_limit'),
            $address, (int) $signer->key_index, $this->flow->sequenceNumber($address, (int) $signer->key_index),
            self::sponsorAddress(), [$address],
        );
        $token = Str::random(40);
        Cache::put('passkey:tx:'.$token, [
            'tx' => $tx->toArray(), 'account' => $account->id, 'credential' => $signer->id, 'kind' => $kind, 'meta' => $meta,
        ], self::TX_TTL);

        return [
            'token' => $token,
            'challenge' => PasskeyCrypto::base64UrlEncode(PasskeyCrypto::challengeFor($tx->payloadMessage())),
            'credentialId' => $signer->credential_id,
            'kind' => $kind,
        ];
    }

    /**
     * Attach the passkey assertion (payload signature + FLIP-264 extension data), add the sponsor's envelope
     * signature and submit. Returns ['txId', 'pending' => cached entry].
     */
    public function submitUserTx(PasskeyAccount $account, string $token, array $assertion, string $rpId): array
    {
        $entry = Cache::pull('passkey:tx:'.$token);
        if (! $entry || (int) $entry['account'] !== (int) $account->id) {
            throw new RuntimeException('This transaction expired, please try again');
        }
        $cred = PasskeyCredential::find($entry['credential']);
        $credId = (string) ($assertion['id'] ?? '');
        if (! $cred || ! hash_equals($cred->credential_id, $credId)) {
            throw new RuntimeException('Signed with a different passkey than the one this transaction was built for');
        }
        $tx = FlowTransaction::fromArray($entry['tx']);
        $authData = PasskeyCrypto::base64UrlDecode((string) $assertion['authenticatorData']);
        $clientData = PasskeyCrypto::base64UrlDecode((string) $assertion['clientDataJSON']);
        $der = PasskeyCrypto::base64UrlDecode((string) $assertion['signature']);
        $raw = PasskeyCrypto::checkTransactionAssertion($cred->public_key, $tx->payloadMessage(), $authData, $clientData, $der, $rpId);
        $tx->addPayloadSignature($tx->proposerAddress, (int) $cred->key_index, $raw, PasskeyCrypto::extensionData($authData, $clientData));
        $sponsor = self::sponsorAddress();
        $tx->addEnvelopeSignature($sponsor, (int) config('passkey.sponsor.key_index'), $this->sponsor->sign($tx->envelopeMessage()));
        $id = $this->flow->send($tx);
        $cred->update(['last_used_at' => now()]);
        PasskeyTransaction::create([
            'passkey_account_id' => $account->id, 'kind' => $entry['kind'], 'tx_id' => $id, 'signed_by' => 'passkey#'.$cred->key_index,
        ]);

        return ['txId' => $id, 'entry' => $entry];
    }

    public static function ufix(string $v): string
    {
        $f = number_format((float) $v, 8, '.', '');

        return $f;
    }
}
