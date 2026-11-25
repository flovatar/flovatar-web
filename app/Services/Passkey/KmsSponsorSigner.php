<?php

namespace App\Services\Passkey;

use App\Services\Flow\UserMessageSigner;
use Aws\CommandInterface;
use Aws\Kms\KmsClient;
use Aws\Result;
use GuzzleHttp\Promise\Create;
use RuntimeException;
use SensitiveParameter;

/**
 * Sponsor key held in AWS KMS (the private key never leaves the HSM). This is the MAINNET sponsor signer
 * (flovatar-kb notes/mainnet-launch-stealth.md); the key-file signer is refused on mainnet.
 *
 *  - KMS key: spec ECC_NIST_P256, usage SIGN_VERIFY. On chain the sponsor account holds the same public key at
 *    N indices (proposal-key pool, PASSKEY_SPONSOR_PROPOSAL_KEYS: one sequence number per index, one lock per index
 *    in PasskeyPoc::withProposalKey), ECDSA_P256 + SHA2_256, weight 1000.
 *  - sign($message): SHA-256(message) is computed here and sent with MessageType DIGEST + ECDSA_SHA_256 (RAW caps the
 *    message at 4 KB; an envelope with a long Cadence template is bigger). KMS returns an ASN.1 DER signature → Flow's
 *    64-byte r||s (each left-padded to 32 bytes; low-S normalised — Flow accepts both), verified against the configured
 *    public key before it is ever submitted.
 *  - publicKeyFromKms(): GetPublicKey (DER SubjectPublicKeyInfo) → 128 hex X||Y; `php artisan passkey:kms-check`
 *    compares it with PASSKEY_SPONSOR_KMS_PUBLIC_KEY and the keys on the sponsor account.
 *  - Credentials: a file OUTSIDE the app dir (PASSKEY_SPONSOR_KMS_CREDENTIALS_FILE, chmod 600) with label lines each
 *    followed by its value line ("access key id" / <value> / "secret access key" / <value>); without it the default
 *    AWS chain (env / instance role). IAM policy: kms:Sign + kms:GetPublicKey on this key ARN only.
 *  - provider 'mock' (TESTNET ONLY): the same SDK client with a local handler that answers Sign/GetPublicKey with a
 *    local P-256 key in KMS's wire format — end-to-end test of this code path without AWS.
 */
final class KmsSponsorSigner implements SponsorKey
{
    private const N = 'ffffffff00000000ffffffffffffffffbce6faada7179e84f3b9cac2fc632551';

    private ?KmsClient $client;

    public function __construct(
        private readonly string $provider,
        private readonly string $keyId,
        private readonly string $publicKey,
        private readonly ?string $region = null,
        ?KmsClient $client = null,
        private readonly ?string $credentialsFile = null,
        private readonly ?string $mockKeyFile = null,
    ) {
        if (! preg_match('/^[0-9a-f]{128}$/', strtolower($publicKey))) {
            throw new RuntimeException('PASSKEY_SPONSOR_KMS_PUBLIC_KEY must be 128 hex chars (X||Y)');
        }
        if ($keyId === '') {
            throw new RuntimeException('PASSKEY_SPONSOR_KMS_KEY (key ARN) is not set');
        }
        $this->client = $client;
    }

    public static function fromConfig(): self
    {
        $c = (array) config('passkey.sponsor.kms');

        return new self(
            (string) ($c['provider'] ?? ''),
            (string) ($c['key'] ?? ''),
            (string) ($c['public_key'] ?? ''),
            ($c['region'] ?? null) ?: null,
            null,
            ($c['credentials_file'] ?? null) ?: null,
            ($c['mock_key_file'] ?? null) ?: null,
        );
    }

    public function publicKeyHex(): string
    {
        return strtolower($this->publicKey);
    }

    public function sign(string $message): string
    {
        $res = $this->client()->sign([
            'KeyId' => $this->keyId,
            'Message' => hash('sha256', $message, true),
            'MessageType' => 'DIGEST',
            'SigningAlgorithm' => 'ECDSA_SHA_256',
        ]);
        $raw = UserMessageSigner::normalizeLowS(self::derSignatureToRaw((string) $res['Signature']));
        // never submit a signature the account key would reject
        if (! self::verifyRaw($message, $raw, $this->publicKey)) {
            throw new RuntimeException('KMS signature does not match the configured sponsor public key');
        }

        return $raw;
    }

    /** X||Y (128 hex) of the KMS key, read with GetPublicKey (checks key spec + usage). */
    public function publicKeyFromKms(): string
    {
        $res = $this->client()->getPublicKey(['KeyId' => $this->keyId]);
        $spec = (string) ($res['KeySpec'] ?? $res['CustomerMasterKeySpec'] ?? '');
        if ($spec !== '' && $spec !== 'ECC_NIST_P256') {
            throw new RuntimeException('KMS key spec is '.$spec.', expected ECC_NIST_P256');
        }
        if (($res['KeyUsage'] ?? 'SIGN_VERIFY') !== 'SIGN_VERIFY') {
            throw new RuntimeException('KMS key usage must be SIGN_VERIFY');
        }

        return self::spkiToPublicKeyHex((string) $res['PublicKey']);
    }

    // ------------------------------------------------------------------ encodings (public for tests)

    /** ASN.1 DER ECDSA-Sig-Value (SEQUENCE { INTEGER r, INTEGER s }) → 64 bytes r||s, each left-padded to 32. */
    public static function derSignatureToRaw(string $der): string
    {
        $len = strlen($der);
        if ($len < 8 || ord($der[0]) !== 0x30) {
            throw new RuntimeException('Bad DER signature');
        }
        $pos = 1;
        $seqLen = ord($der[$pos++]);
        if ($seqLen & 0x80) {
            if (($seqLen & 0x7f) !== 1) {
                throw new RuntimeException('Bad DER signature length');
            }
            $seqLen = ord($der[$pos++]);
        }
        if ($pos + $seqLen !== $len) {
            throw new RuntimeException('DER signature length mismatch');
        }
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            if ($pos + 2 > $len || ord($der[$pos++]) !== 0x02) {
                throw new RuntimeException('Bad DER integer');
            }
            $l = ord($der[$pos++]);
            if ($l < 1 || $l > 33 || $pos + $l > $len) {
                throw new RuntimeException('Bad DER integer length');
            }
            $int = substr($der, $pos, $l);
            $pos += $l;
            if (ord($int[0]) & 0x80) {
                throw new RuntimeException('Negative DER integer');
            }
            $int = ltrim($int, "\0");
            if ($int === '' || strlen($int) > 32) {
                throw new RuntimeException('DER integer out of range');
            }
            $out .= str_pad($int, 32, "\0", STR_PAD_LEFT);
        }
        if ($pos !== $len) {
            throw new RuntimeException('Trailing bytes after DER signature');
        }

        return $out;
    }

    /** DER SubjectPublicKeyInfo of a P-256 key → 128 hex X||Y (uncompressed point without the 0x04 prefix). */
    public static function spkiToPublicKeyHex(string $spkiDer): string
    {
        $pem = '-----BEGIN PUBLIC KEY-----'."\n".chunk_split(base64_encode($spkiDer), 64, "\n").'-----END PUBLIC KEY-----'."\n";
        $key = @openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new RuntimeException('KMS public key is not a valid SubjectPublicKeyInfo');
        }
        $d = openssl_pkey_get_details($key);
        if (($d['type'] ?? null) !== OPENSSL_KEYTYPE_EC || ($d['ec']['curve_name'] ?? '') !== 'prime256v1') {
            throw new RuntimeException('KMS public key is not a P-256 (prime256v1) key');
        }

        return bin2hex(str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT));
    }

    /** Does raw r||s verify over SHA-256($message) with X||Y $publicKeyHex? */
    public static function verifyRaw(string $message, string $raw, string $publicKeyHex): bool
    {
        if (strlen($raw) !== 64) {
            return false;
        }
        $pub = @openssl_pkey_get_public(PasskeyCrypto::pemFromPublicKeyHex($publicKeyHex));

        return $pub !== false && openssl_verify($message, self::rawToDer($raw), $pub, OPENSSL_ALGO_SHA256) === 1;
    }

    public static function rawToDer(string $raw): string
    {
        $int = function (string $b): string {
            $b = ltrim($b, "\0");
            if ($b === '' || (ord($b[0]) & 0x80)) {
                $b = "\0".$b;
            }

            return "\x02".chr(strlen($b)).$b;
        };
        $body = $int(substr($raw, 0, 32)).$int(substr($raw, 32, 32));

        return "\x30".chr(strlen($body)).$body;
    }

    /** Label line → value line credentials file (values never logged). Returns ['key' => id, 'secret' => secret]. */
    public static function readCredentialsFile(string $path, ?string $appBasePath = null): array
    {
        clearstatcache(true, $path);
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('KMS credentials file is missing or not readable');
        }
        $real = (string) realpath($path);
        if ($appBasePath) {
            $base = rtrim((string) realpath($appBasePath), '/').'/';
            if ($base !== '/' && str_starts_with($real, $base)) {
                throw new RuntimeException('KMS credentials file must be outside the application directory');
            }
        }
        if ((fileperms($real) & 0077) !== 0) {
            throw new RuntimeException('KMS credentials file must not be accessible by group/others (chmod 600)');
        }
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) file_get_contents($real))), 'strlen'));
        $out = [];
        for ($i = 0; $i + 1 < count($lines); $i++) {
            $label = strtolower(preg_replace('/[^a-z]/i', '', $lines[$i]));
            if (in_array($label, ['accesskeyid', 'awsaccesskeyid', 'accesskey'], true)) {
                $out['key'] = $lines[++$i];
            } elseif (in_array($label, ['secretaccesskey', 'awssecretaccesskey', 'secretkey', 'secret'], true)) {
                $out['secret'] = $lines[++$i];
            }
        }
        if (! preg_match('/^[A-Z0-9]{16,128}$/', (string) ($out['key'] ?? '')) || strlen((string) ($out['secret'] ?? '')) < 20) {
            throw new RuntimeException('KMS credentials file: expected an "access key id" and a "secret access key" label line, each followed by its value line');
        }

        return $out;
    }

    // ------------------------------------------------------------------ client

    private function client(): KmsClient
    {
        if ($this->client) {
            return $this->client;
        }
        $args = ['version' => '2014-11-01', 'region' => $this->region ?: 'us-east-1'];
        if ($this->provider === 'mock') {
            if (! PasskeyPoc::isTestnet()) {
                throw new RuntimeException('The mock KMS provider is for testnet only');
            }
            $args['credentials'] = ['key' => 'MOCKKMSACCESSKEY0000', 'secret' => 'mock-secret-not-used-000000000'];
            $args['handler'] = self::localHandler((string) $this->mockKeyFile);
        } elseif ($this->provider === 'aws') {
            if (! class_exists(KmsClient::class)) {
                throw new RuntimeException('aws/aws-sdk-php is not installed');
            }
            if ($this->credentialsFile) {
                $args['credentials'] = self::readCredentialsFile($this->credentialsFile, base_path());
            }
        } else {
            throw new RuntimeException('KMS provider "'.$this->provider.'" is not wired (supported: aws; mock on testnet)');
        }

        return $this->client = new KmsClient($args);
    }

    /**
     * SDK handler answering like AWS KMS with a local P-256 key (64 hex chars, or a chmod-600 file holding them):
     * Sign (DIGEST or RAW, ECDSA_SHA_256) → DER signature; GetPublicKey → DER SPKI. Tests + testnet end-to-end check.
     */
    public static function localHandler(#[SensitiveParameter] string $keyFileOrHex): callable
    {
        $hex = preg_match('/^[0-9a-f]{64}$/i', $keyFileOrHex) ? strtolower($keyFileOrHex)
            : strtolower(preg_replace('/^0x/', '', trim((string) @file_get_contents($keyFileOrHex))));
        if (! preg_match('/^[0-9a-f]{64}$/', $hex)) {
            throw new RuntimeException('mock KMS key must be 64 hex chars');
        }

        return function (CommandInterface $cmd) use ($hex) {
            $key = openssl_pkey_get_private(self::ecPrivatePem($hex));
            if ($cmd->getName() === 'Sign') {
                if (($cmd['SigningAlgorithm'] ?? '') !== 'ECDSA_SHA_256') {
                    throw new RuntimeException('mock KMS: unsupported algorithm');
                }
                $msg = (string) $cmd['Message'];
                $digest = ($cmd['MessageType'] ?? 'RAW') === 'DIGEST' ? $msg : hash('sha256', $msg, true);
                if (strlen($digest) !== 32) {
                    throw new RuntimeException('mock KMS: DIGEST must be 32 bytes');
                }

                return Create::promiseFor(new Result(['Signature' => self::signDigest($hex, $digest), 'KeyId' => $cmd['KeyId'], 'SigningAlgorithm' => 'ECDSA_SHA_256']));
            }
            if ($cmd->getName() === 'GetPublicKey') {
                $d = openssl_pkey_get_details($key);
                $spki = base64_decode(preg_replace('/-----[A-Z ]+-----|\s/', '', $d['key']));

                return Create::promiseFor(new Result(['PublicKey' => $spki, 'KeySpec' => 'ECC_NIST_P256', 'KeyUsage' => 'SIGN_VERIFY', 'KeyId' => $cmd['KeyId']]));
            }
            throw new RuntimeException('mock KMS: unsupported operation '.$cmd->getName());
        };
    }

    /** ECDSA-P256 over a precomputed 32-byte digest (random k), DER-encoded like KMS — mock/testing only. */
    private static function signDigest(#[SensitiveParameter] string $hex, string $digest): string
    {
        $n = gmp_init(self::N, 16);
        $priv = gmp_init($hex, 16);
        $z = gmp_import($digest);
        do {
            $k = gmp_add(gmp_mod(gmp_import(random_bytes(40)), gmp_sub($n, 1)), 1);
            [$x] = P256::multiplyBase($k);
            $r = gmp_mod($x, $n);
            $s = gmp_mod(gmp_mul(gmp_invert($k, $n), gmp_add($z, gmp_mul($r, $priv))), $n);
        } while (gmp_cmp($r, 0) === 0 || gmp_cmp($s, 0) === 0);
        $pad = fn ($g) => str_pad(gmp_export($g), 32, "\0", STR_PAD_LEFT);

        return self::rawToDer($pad($r).$pad($s));
    }

    private static function ecPrivatePem(#[SensitiveParameter] string $hex): string
    {
        $der = hex2bin('30310201010420'.$hex.'a00a06082a8648ce3d030107');
        $label = 'EC PRIVATE'.' KEY';

        return '-----BEGIN '.$label.'-----'."\n".chunk_split(base64_encode($der), 64, "\n").'-----END '.$label.'-----'."\n";
    }
}
