<?php

namespace App\Services\Passkey;

use App\Services\Flow\UserMessageSigner;
use RuntimeException;

/**
 * Sponsor key held in a cloud KMS (the private key never leaves the HSM). Intended for the MAINNET sponsor.
 *
 *  - provider 'aws': KMS key spec ECC_NIST_P256, usage SIGN_VERIFY; Sign(MessageType RAW, ECDSA_SHA_256) returns a DER
 *    signature over SHA-256(message) = exactly what Flow expects for an ECDSA_P256 / SHA2_256 account key.
 *    Credentials: the server's IAM role / AWS_* env (aws/aws-sdk-php). IAM policy: kms:Sign on this key only.
 *  - provider 'gcp': EC_SIGN_P256_SHA256 key version; not wired yet (asymmetricSign with a SHA-256 digest) — add it
 *    here when chosen; the interface stays the same.
 *
 * public_key = X||Y of the KMS key (from GetPublicKey, added to the sponsor account as ECDSA_P256 / SHA2_256).
 * Setup steps: flovatar-kb notes/passkey-ui-integration.md "Mainnet sponsor".
 */
final class KmsSponsorSigner implements SponsorKey
{
    public function __construct(
        private readonly string $provider,
        private readonly string $keyId,
        private readonly string $publicKey,
        private readonly ?string $region = null,
    ) {
        if (! preg_match('/^[0-9a-f]{128}$/', strtolower($publicKey))) {
            throw new RuntimeException('PASSKEY_SPONSOR_KMS_PUBLIC_KEY must be 128 hex chars (X||Y)');
        }
    }

    public static function fromConfig(): self
    {
        $c = (array) config('passkey.sponsor.kms');

        return new self((string) ($c['provider'] ?? ''), (string) ($c['key'] ?? ''), (string) ($c['public_key'] ?? ''), $c['region'] ?? env('AWS_DEFAULT_REGION'));
    }

    public function publicKeyHex(): string
    {
        return strtolower($this->publicKey);
    }

    public function sign(string $message): string
    {
        $der = match ($this->provider) {
            'aws' => $this->signAws($message),
            default => throw new RuntimeException('KMS provider "'.$this->provider.'" is not wired (supported: aws)'),
        };
        $raw = UserMessageSigner::normalizeLowS(UserMessageSigner::derToRaw($der));
        // never submit a signature the account key would reject
        $pub = openssl_pkey_get_public(PasskeyCrypto::pemFromPublicKeyHex($this->publicKey));
        if (openssl_verify($message, $der, $pub, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('KMS signature does not match the configured sponsor public key');
        }

        return $raw;
    }

    private function signAws(string $message): string
    {
        if (! class_exists(\Aws\Kms\KmsClient::class)) {
            throw new RuntimeException('aws/aws-sdk-php is not installed');
        }
        $client = new \Aws\Kms\KmsClient(['version' => '2014-11-01', 'region' => $this->region ?: 'us-east-1']);
        $res = $client->sign([
            'KeyId' => $this->keyId,
            'Message' => $message,
            'MessageType' => 'RAW',
            'SigningAlgorithm' => 'ECDSA_SHA_256',
        ]);

        return (string) $res['Signature'];
    }
}
