<?php

namespace App\Services\Passkey;

use App\Services\Flow\UserMessageSigner;
use RuntimeException;
use SensitiveParameter;

/**
 * Signs Flow transaction messages with the sponsor's account key (ECDSA_P256 + SHA2_256).
 * The key file holds 64 hex chars, must be chmod 600 and live outside the application directory.
 * The key never appears in exceptions or logs.
 */
final class SponsorSigner
{
    private const P256_N = 'ffffffff00000000ffffffffffffffffbce6faada7179e84f3b9cac2fc632551';

    public function __construct(
        private readonly ?string $keyFile,
        private readonly ?string $appBasePath = null,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(config('passkey.sponsor.key_file'), base_path());
    }

    /** Raw 64-byte r||s over SHA2-256(message). */
    public function sign(string $message): string
    {
        $key = openssl_pkey_get_private(self::privatePem($this->readKeyHex()));
        if ($key === false) {
            throw new RuntimeException('Sponsor key could not be loaded');
        }
        if (! openssl_sign($message, $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Sponsor signing failed');
        }

        return UserMessageSigner::normalizeLowS(UserMessageSigner::derToRaw($der));
    }

    public function publicKeyHex(): string
    {
        $key = openssl_pkey_get_private(self::privatePem($this->readKeyHex()));
        $d = openssl_pkey_get_details($key);

        return bin2hex(str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT));
    }

    private function readKeyHex(): string
    {
        $path = $this->keyFile;
        if (! $path) {
            throw new RuntimeException('Sponsor key not configured (PASSKEY_SPONSOR_KEY_FILE)');
        }
        clearstatcache(true, $path);
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Sponsor key file is missing or not readable');
        }
        $real = realpath($path);
        if ($this->appBasePath) {
            $base = rtrim((string) realpath($this->appBasePath), '/').'/';
            if ($base !== '/' && str_starts_with($real, $base)) {
                throw new RuntimeException('Sponsor key file must be outside the application directory');
            }
        }
        if ((fileperms($real) & 0077) !== 0) {
            throw new RuntimeException('Sponsor key file must not be accessible by group/others (chmod 600)');
        }
        $hex = strtolower(preg_replace('/^0x/', '', trim((string) file_get_contents($real))));
        if (! preg_match('/^[0-9a-f]{64}$/', $hex)) {
            throw new RuntimeException('Sponsor key file must contain exactly 64 hex characters');
        }
        $d = gmp_init($hex, 16);
        if (gmp_cmp($d, 0) <= 0 || gmp_cmp($d, gmp_init(self::P256_N, 16)) >= 0) {
            throw new RuntimeException('Sponsor key is out of range for P-256');
        }

        return $hex;
    }

    private static function privatePem(#[SensitiveParameter] string $hex): string
    {
        $der = hex2bin('30310201010420'.$hex.'a00a06082a8648ce3d030107');

        return "-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END EC PRIVATE KEY-----\n";
    }
}
