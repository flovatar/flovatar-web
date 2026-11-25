<?php

namespace Tests\Unit;

use App\Services\Flow\UserMessageSigner;
use App\Services\Passkey\AccountRecovery;
use App\Services\Passkey\FlowGateway;
use App\Services\Passkey\KeyIndexer;
use App\Services\Passkey\P256;
use App\Services\Passkey\PasskeyCrypto;
use RuntimeException;
use Tests\TestCase;

/**
 * DB-loss recovery with a software authenticator: P-256 public key recovery from a WebAuthn assertion signature,
 * key indexer + chain confirmation (fakes), and the rejections (revoked / low weight / wrong algorithm / bad assertion).
 */
class PasskeyRecoveryTest extends TestCase
{
    /** @return array{0: \OpenSSLAsymmetricKey, 1: string} [private key, X||Y hex] */
    private function key(): array
    {
        $k = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $d = openssl_pkey_get_details($k);

        return [$k, bin2hex(str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT))];
    }

    /** Software authenticator: [authData, clientDataJSON, DER signature] for a sign-in on testnet.flovatar.com. */
    private function assertion($key, string $challenge = 'challenge-bytes-123', string $origin = 'https://testnet.flovatar.com', int $flags = 0x05): array
    {
        $authData = hash('sha256', 'testnet.flovatar.com', true).chr($flags).pack('N', 7);
        $client = json_encode(['type' => 'webauthn.get', 'challenge' => PasskeyCrypto::base64UrlEncode($challenge), 'origin' => $origin, 'crossOrigin' => false]);
        openssl_sign($authData.hash('sha256', $client, true), $der, $key, OPENSSL_ALGO_SHA256);

        return [$authData, $client, $der];
    }

    private function recovery(array $indexed, array $chainKeys): AccountRecovery
    {
        $indexer = new class($indexed) extends KeyIndexer
        {
            public function __construct(private array $rows)
            {
                parent::__construct('https://example.invalid');
            }

            public function accounts(string $publicKeyHex): array
            {
                return $this->rows[strtolower($publicKeyHex)] ?? [];
            }
        };
        $flow = new class($chainKeys) extends FlowGateway
        {
            public function __construct(private array $keys)
            {
                parent::__construct('https://example.invalid');
            }

            public function account(string $address): array
            {
                return ['address' => $address, 'balance' => 0.06, 'keys' => $this->keys[strtolower($address)] ?? []];
            }
        };

        return new AccountRecovery($flow, $indexer);
    }

    private static function chainKey(int $index, string $pub, int $weight = 1000, bool $revoked = false, string $sig = 'ECDSA_P256', string $hash = 'SHA2_256'): array
    {
        return ['index' => $index, 'public_key' => $pub, 'sequence_number' => 3, 'weight' => $weight, 'revoked' => $revoked, 'signing_algorithm' => $sig, 'hashing_algorithm' => $hash];
    }

    private static function indexRow(string $address, int $keyId, int $weight = 1000, bool $revoked = false): array
    {
        return ['address' => $address, 'keyId' => $keyId, 'weight' => $weight, 'revoked' => $revoked, 'signing' => 'ECDSA_P256', 'hashing' => 'SHA2_256'];
    }

    public function test_public_key_recovered_from_webauthn_assertion(): void
    {
        [$k, $pub] = $this->key();
        [$authData, $client, $der] = $this->assertion($k);
        $candidates = P256::recoverPublicKeys($authData.hash('sha256', $client, true), UserMessageSigner::derToRaw($der));
        $this->assertContains($pub, $candidates);
        $this->assertLessThanOrEqual(4, count($candidates));
        // every candidate is a valid curve point that verifies this exact signature (that's why the indexer decides)
        foreach ($candidates as $c) {
            $this->assertSame(1, openssl_verify($authData.hash('sha256', $client, true), $der, PasskeyCrypto::pemFromPublicKeyHex($c), OPENSSL_ALGO_SHA256));
        }
        // a different message gives different keys
        $this->assertNotContains($pub, P256::recoverPublicKeys('other message', UserMessageSigner::derToRaw($der)));
    }

    public function test_recovery_many_keys(): void
    {
        for ($i = 0; $i < 8; $i++) {
            [$k, $pub] = $this->key();
            [$authData, $client, $der] = $this->assertion($k, random_bytes(32));
            $this->assertContains($pub, P256::recoverPublicKeys($authData.hash('sha256', $client, true), UserMessageSigner::derToRaw($der)));
        }
    }

    public function test_out_of_range_signature_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        P256::recoverPublicKeys('m', str_repeat("\0", 64));
    }

    public function test_locate_finds_account_and_key_index(): void
    {
        [$k, $pub] = $this->key();
        [$authData, $client, $der] = $this->assertion($k);
        $addr = '0x1234567890abcdef';
        $verified = null;
        $found = $this->recovery([$pub => [self::indexRow($addr, 1)]], [$addr => [self::chainKey(0, str_repeat('ab', 64)), self::chainKey(1, $pub)]])
            ->locate($authData, $client, $der, function ($key) use (&$verified) { $verified = $key; });
        $this->assertSame(['address' => $addr, 'key_index' => 1, 'public_key' => $pub], $found);
        $this->assertSame($pub, $verified);
    }

    public function test_locate_rejects_unusable_keys(): void
    {
        [$k, $pub] = $this->key();
        [$authData, $client, $der] = $this->assertion($k);
        $addr = '0x1234567890abcdef';
        $noop = function () {};
        // not indexed
        $this->assertNull($this->recovery([], [$addr => [self::chainKey(0, $pub)]])->locate($authData, $client, $der, $noop));
        // indexer says revoked / low weight
        $this->assertNull($this->recovery([$pub => [self::indexRow($addr, 0, 1000, true)]], [$addr => [self::chainKey(0, $pub)]])->locate($authData, $client, $der, $noop));
        $this->assertNull($this->recovery([$pub => [self::indexRow($addr, 0, 500)]], [$addr => [self::chainKey(0, $pub)]])->locate($authData, $client, $der, $noop));
        // the chain disagrees with the indexer (revoked meanwhile, low weight, other algorithm, key gone)
        $idx = [$pub => [self::indexRow($addr, 0)]];
        $this->assertNull($this->recovery($idx, [$addr => [self::chainKey(0, $pub, 1000, true)]])->locate($authData, $client, $der, $noop));
        $this->assertNull($this->recovery($idx, [$addr => [self::chainKey(0, $pub, 999)]])->locate($authData, $client, $der, $noop));
        $this->assertNull($this->recovery($idx, [$addr => [self::chainKey(0, $pub, 1000, false, 'ECDSA_secp256k1')]])->locate($authData, $client, $der, $noop));
        $this->assertNull($this->recovery($idx, [$addr => [self::chainKey(0, $pub, 1000, false, 'ECDSA_P256', 'SHA3_256')]])->locate($authData, $client, $der, $noop));
        $this->assertNull($this->recovery($idx, [$addr => []])->locate($authData, $client, $der, $noop));
    }

    public function test_locate_propagates_assertion_failure(): void
    {
        [$k, $pub] = $this->key();
        [$authData, $client, $der] = $this->assertion($k);
        $addr = '0x1234567890abcdef';
        $this->expectException(RuntimeException::class);
        $this->recovery([$pub => [self::indexRow($addr, 0)]], [$addr => [self::chainKey(0, $pub)]])
            ->locate($authData, $client, $der, function () { throw new RuntimeException('invalid challenge'); });
    }

    public function test_full_webauthn_check_with_recovered_key(): void
    {
        // the controller's verify callback = lbuchs processGet with the recovered key: wrong challenge must fail
        [$k, $pub] = $this->key();
        [$authData, $client, $der] = $this->assertion($k, 'the-real-challenge');
        $wa = new \lbuchs\WebAuthn\WebAuthn('t', 'testnet.flovatar.com', ['none']);
        $wa->processGet($client, $authData, $der, PasskeyCrypto::pemFromPublicKeyHex($pub), 'the-real-challenge', null, true, true);
        $this->expectException(\lbuchs\WebAuthn\WebAuthnException::class);
        $wa->processGet($client, $authData, $der, PasskeyCrypto::pemFromPublicKeyHex($pub), 'another-challenge', null, true, true);
    }
}
