<?php

namespace App\Services\Flow;

use RuntimeException;
use SensitiveParameter;

/**
 * Pure-PHP replacement for the legacy Go pack signer (go/examples/user_signature, a flow-go-sdk fork).
 *
 * Legacy behaviour reproduced exactly:
 *   signature = ECDSA_P256( SHA3_256( pad32("FLOW-V0.0-user") || message ) )   i.e. flow.SignUserMessage()
 *   encoded as hex( r || s ), each left-padded to 32 bytes (64 bytes, 128 hex chars).
 * This is what Cadence checks with `KeyList.verify(..., domainSeparationTag: "FLOW-V0.0-user")`
 * (FlovatarPack v1) or `PublicKey.verify(..., domainSeparationTag: <tag>, hashAlgorithm: SHA3_256)`.
 *
 * ECDSA uses a random nonce (the Go SDK too: crypto/ecdsa.Sign with crypto/rand), so two signatures of the same
 * message never match byte for byte. What is identical to the Go tool, and covered by tests/Unit/UserMessageSignerTest:
 * the signed pre-image, its SHA3-256 digest, the r||s encoding, and mutual verification (Go verifies PHP signatures
 * and PHP verifies Go signatures). Signatures here are normalised to low-S (s <= n/2); the Go SDK does not normalise,
 * Flow accepts both forms.
 *
 * Key handling: the private key (64 hex chars, `flow keys generate` format) is read from PACK_SIGNER_KEY_FILE on each
 * call. The file must be outside the application directory and must not be readable by group/others (chmod 600).
 * The key, its path contents and the PEM never appear in exceptions or logs.
 */
final class UserMessageSigner
{
    public const USER_DOMAIN_TAG = 'FLOW-V0.0-user';
    public const NO_DOMAIN_TAG = '';

    /** Order of the P-256 group. */
    private const P256_N = 'ffffffff00000000ffffffffffffffffbce6faada7179e84f3b9cac2fc632551';

    /** DER prefix of a P-256 SubjectPublicKeyInfo (followed by 04 || X || Y). */
    private const SPKI_P256_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    public function __construct(
        private readonly ?string $keyFile,
        private readonly ?string $appBasePath = null,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(config('flovatar.packSignerKeyFile'), base_path());
    }

    /** UTF-8 tag right-padded with zero bytes to 32 bytes; the empty tag means "no prefix". */
    public static function paddedTag(string $tag): string
    {
        if ($tag === '') {
            return '';
        }
        if (strlen($tag) > 32) {
            throw new RuntimeException('Domain tag longer than 32 bytes');
        }
        return str_pad($tag, 32, "\0", STR_PAD_RIGHT);
    }

    /** The exact bytes that are hashed and signed. */
    public static function preimage(string $message, string $tag = self::USER_DOMAIN_TAG): string
    {
        return self::paddedTag($tag) . $message;
    }

    /** Hex SHA3-256 digest of the pre-image (diagnostics / tests). */
    public static function digestHex(string $message, string $tag = self::USER_DOMAIN_TAG): string
    {
        return hash('sha3-256', self::preimage($message, $tag));
    }

    /**
     * Sign a user message. Returns hex r||s (128 chars, low-S).
     */
    public function sign(string $message, string $tag = self::USER_DOMAIN_TAG): string
    {
        $key = openssl_pkey_get_private(self::privatePem($this->readKeyHex()));
        if ($key === false) {
            throw new RuntimeException('Pack signer key could not be loaded');
        }
        $der = '';
        if (!openssl_sign(self::preimage($message, $tag), $der, $key, 'sha3-256')) {
            throw new RuntimeException('openssl_sign failed (OpenSSL >= 1.1.1 with sha3-256 is required)');
        }
        return bin2hex(self::normalizeLowS(self::derToRaw($der)));
    }

    /** Uncompressed public key X||Y as hex (128 chars), the format Flow uses for account and verifier keys. */
    public function publicKeyHex(): string
    {
        $key = openssl_pkey_get_private(self::privatePem($this->readKeyHex()));
        if ($key === false) {
            throw new RuntimeException('Pack signer key could not be loaded');
        }
        $d = openssl_pkey_get_details($key);
        if (($d['ec']['curve_name'] ?? '') !== 'prime256v1') {
            throw new RuntimeException('Pack signer key is not a P-256 key');
        }
        return bin2hex(str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT));
    }

    /** Verify a hex r||s signature against a hex X||Y P-256 public key (accepts high-S like Flow does). */
    public static function verify(string $publicKeyHex, string $message, string $signatureHex, string $tag = self::USER_DOMAIN_TAG): bool
    {
        $publicKeyHex = strtolower(preg_replace('/^0x/i', '', trim($publicKeyHex)));
        $signatureHex = strtolower(preg_replace('/^0x/i', '', trim($signatureHex)));
        if (!preg_match('/^[0-9a-f]{128}$/', $publicKeyHex) || !preg_match('/^[0-9a-f]{128}$/', $signatureHex)) {
            return false;
        }
        $pem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode(hex2bin(self::SPKI_P256_PREFIX . '04' . $publicKeyHex)), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
        $pub = openssl_pkey_get_public($pem);
        if ($pub === false) {
            return false;
        }
        return openssl_verify(self::preimage($message, $tag), self::rawToDer(hex2bin($signatureHex)), $pub, 'sha3-256') === 1;
    }

    /** DER ECDSA-Sig-Value -> 64-byte r||s. */
    public static function derToRaw(string $der): string
    {
        $pos = 0;
        $len = strlen($der);
        $byte = function () use ($der, &$pos, $len): int {
            if ($pos >= $len) {
                throw new RuntimeException('Truncated DER signature');
            }
            return ord($der[$pos++]);
        };
        if ($byte() !== 0x30) {
            throw new RuntimeException('Bad DER signature');
        }
        $l = $byte();
        if ($l & 0x80) {
            $pos += $l & 0x7f;
        }
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            if ($byte() !== 0x02) {
                throw new RuntimeException('Bad DER integer');
            }
            $l = $byte();
            $int = ltrim(substr($der, $pos, $l), "\0");
            $pos += $l;
            if (strlen($int) > 32) {
                throw new RuntimeException('DER integer too long');
            }
            $out .= str_pad($int, 32, "\0", STR_PAD_LEFT);
        }
        return $out;
    }

    /** 64-byte r||s -> DER ECDSA-Sig-Value. */
    public static function rawToDer(string $raw): string
    {
        if (strlen($raw) !== 64) {
            throw new RuntimeException('Raw signature must be 64 bytes');
        }
        $enc = function (string $int): string {
            $int = ltrim($int, "\0");
            if ($int === '' || (ord($int[0]) & 0x80)) {
                $int = "\0" . $int;
            }
            return "\x02" . chr(strlen($int)) . $int;
        };
        $body = $enc(substr($raw, 0, 32)) . $enc(substr($raw, 32, 32));
        return "\x30" . chr(strlen($body)) . $body;
    }

    /** Replace s by n - s when s > n/2 (both are valid ECDSA signatures; low-S is the canonical one). */
    public static function normalizeLowS(string $raw): string
    {
        $n = gmp_init(self::P256_N, 16);
        $s = gmp_init(bin2hex(substr($raw, 32, 32)), 16);
        if (gmp_cmp($s, gmp_div_q($n, 2)) > 0) {
            $s = gmp_sub($n, $s);
            $sBytes = str_pad(gmp_export($s), 32, "\0", STR_PAD_LEFT);
            return substr($raw, 0, 32) . $sBytes;
        }
        return $raw;
    }

    public static function isLowS(string $signatureHex): bool
    {
        $s = gmp_init(substr($signatureHex, 64, 64), 16);
        return gmp_cmp($s, gmp_div_q(gmp_init(self::P256_N, 16), 2)) <= 0;
    }

    private function readKeyHex(): string
    {
        $path = $this->keyFile;
        if (!$path) {
            throw new RuntimeException('Pack signer key not configured (PACK_SIGNER_KEY_FILE)');
        }
        clearstatcache(true, $path);
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Pack signer key file is missing or not readable (PACK_SIGNER_KEY_FILE)');
        }
        $real = realpath($path);
        if ($this->appBasePath) {
            $base = rtrim((string) realpath($this->appBasePath), '/') . '/';
            if ($base !== '/' && str_starts_with($real, $base)) {
                throw new RuntimeException('Pack signer key file must be outside the application directory');
            }
        }
        if ((fileperms($real) & 0077) !== 0) {
            throw new RuntimeException('Pack signer key file must not be accessible by group/others (chmod 600)');
        }
        $hex = strtolower(trim((string) file_get_contents($real)));
        $hex = preg_replace('/^0x/', '', $hex);
        if (!preg_match('/^[0-9a-f]{64}$/', $hex)) {
            throw new RuntimeException('Pack signer key file must contain exactly 64 hex characters (flow keys generate format)');
        }
        $d = gmp_init($hex, 16);
        if (gmp_cmp($d, 0) <= 0 || gmp_cmp($d, gmp_init(self::P256_N, 16)) >= 0) {
            throw new RuntimeException('Pack signer key is out of range for P-256');
        }
        return $hex;
    }

    /** SEC1 ECPrivateKey (prime256v1, no public key; OpenSSL derives it) as PEM. */
    private static function privatePem(#[SensitiveParameter] string $hex): string
    {
        $der = hex2bin('30310201010420' . $hex . 'a00a06082a8648ce3d030107');
        return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
    }
}
