<?php

namespace App\Services\Passkey;

use App\Services\Flow\UserMessageSigner;
use RuntimeException;

/**
 * WebAuthn <-> Flow glue (FLIP-264):
 *  - P-256 public key PEM <-> raw 64-byte X||Y (the Flow account key format, ECDSA_P256 + SHA2_256)
 *  - DER ECDSA signature -> raw r||s
 *  - signature extension data = 0x01 || RLP([authenticatorData, clientDataJSON])
 *  - local pre-check of an assertion made over a transaction message (challenge = SHA2-256(message))
 */
final class PasskeyCrypto
{
    public const WEBAUTHN_SCHEME = "\x01";

    private const SPKI_P256_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    public static function base64UrlEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $s): string
    {
        $s = strtr(trim($s), '-_', '+/');
        $pad = strlen($s) % 4;
        if ($pad) {
            $s .= str_repeat('=', 4 - $pad);
        }
        $out = base64_decode($s, true);
        if ($out === false) {
            throw new RuntimeException('Invalid base64url');
        }

        return $out;
    }

    /** PEM (SPKI) of a P-256 key -> 128 hex chars X||Y. Rejects other curves / key types. */
    public static function publicKeyHexFromPem(string $pem): string
    {
        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new RuntimeException('Unreadable public key');
        }
        $d = openssl_pkey_get_details($key);
        if (($d['type'] ?? null) !== OPENSSL_KEYTYPE_EC || ($d['ec']['curve_name'] ?? '') !== 'prime256v1') {
            throw new RuntimeException('Passkey is not a P-256 (ES256) key');
        }

        return bin2hex(str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT));
    }

    public static function pemFromPublicKeyHex(string $hex): string
    {
        $hex = strtolower(preg_replace('/^0x/i', '', $hex));
        if (! preg_match('/^[0-9a-f]{128}$/', $hex)) {
            throw new RuntimeException('Public key must be 64 bytes hex');
        }

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode(hex2bin(self::SPKI_P256_PREFIX.'04'.$hex)), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }

    public static function derToRaw(string $der): string
    {
        return UserMessageSigner::derToRaw($der);
    }

    public static function extensionData(string $authenticatorData, string $clientDataJSON): string
    {
        return self::WEBAUTHN_SCHEME.Rlp::encode([$authenticatorData, $clientDataJSON]);
    }

    /** The WebAuthn challenge for a signer of $message (payload or envelope): SHA2-256(message). */
    public static function challengeFor(string $message): string
    {
        return hash('sha256', $message, true);
    }

    /**
     * Validate an assertion made for a transaction message, the way the Flow node will (FLIP-264) plus the RP checks
     * a wallet should do. Returns the raw r||s signature. Throws with a user-safe message otherwise.
     */
    public static function checkTransactionAssertion(
        string $publicKeyHex,
        string $message,
        string $authenticatorData,
        string $clientDataJSON,
        string $derSignature,
        string $rpId,
        bool $requireUserVerification = true,
        ?array $allowedOrigins = null,
    ): string {
        $client = json_decode($clientDataJSON, true);
        if (! is_array($client) || ($client['type'] ?? null) !== 'webauthn.get') {
            throw new RuntimeException('clientDataJSON is not a webauthn.get assertion');
        }
        $challenge = self::base64UrlDecode((string) ($client['challenge'] ?? ''));
        if (! hash_equals(self::challengeFor($message), $challenge)) {
            throw new RuntimeException('Assertion challenge does not match the transaction');
        }
        if ($allowedOrigins !== null) {
            // exact allowlist (headless frontends, related origins): see WebAuthnOrigins
            if (! in_array(WebAuthnOrigins::normalise((string) ($client['origin'] ?? '')), $allowedOrigins, true)) {
                throw new RuntimeException('Assertion origin does not match');
            }
        } else {
            $host = parse_url((string) ($client['origin'] ?? ''), PHP_URL_HOST);
            if ($host !== $rpId && ! str_ends_with((string) $host, '.'.$rpId)) {
                throw new RuntimeException('Assertion origin does not match');
            }
        }
        if (strlen($authenticatorData) < 37) {
            throw new RuntimeException('authenticatorData too short');
        }
        if (! hash_equals(hash('sha256', $rpId, true), substr($authenticatorData, 0, 32))) {
            throw new RuntimeException('rpIdHash mismatch');
        }
        $flags = ord($authenticatorData[32]);
        if (! ($flags & 0x01)) {
            throw new RuntimeException('User presence flag not set');
        }
        if ($requireUserVerification && ! ($flags & 0x04)) {
            throw new RuntimeException('User verification flag not set');
        }
        $pub = openssl_pkey_get_public(self::pemFromPublicKeyHex($publicKeyHex));
        $signed = $authenticatorData.hash('sha256', $clientDataJSON, true);
        if (openssl_verify($signed, $derSignature, $pub, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('Passkey signature does not verify');
        }

        return self::derToRaw($derSignature);
    }
}
