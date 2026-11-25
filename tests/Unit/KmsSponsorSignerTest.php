<?php

namespace Tests\Unit;

use App\Services\Passkey\KmsSponsorSigner;
use App\Services\Passkey\PasskeyPoc;
use App\Services\Passkey\SponsorSigner;
use Aws\CommandInterface;
use Aws\Kms\KmsClient;
use GuzzleHttp\Promise\Create;
use RuntimeException;
use Tests\TestCase;

/**
 * AWS KMS sponsor signer (mainnet passkey sponsor): DER → r||s, SPKI → X||Y, DIGEST signing through the real AWS SDK
 * client with a local P-256 "KMS" (mock handler), credentials file rules, mainnet fail-closed rules.
 */
class KmsSponsorSignerTest extends TestCase
{
    private const ARN = 'arn:aws:kms:eu-central-1:111122223333:key/0b0b0b0b-1111-2222-3333-444455556666';

    /** Fresh local P-256 key: [private hex (64), public X||Y hex (128), SPKI DER]. */
    private function keypair(): array
    {
        $k = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $d = openssl_pkey_get_details($k);
        $pad = fn ($b) => str_pad($b, 32, "\0", STR_PAD_LEFT);
        $spki = base64_decode(preg_replace('/-----[A-Z ]+-----|\s/', '', $d['key']));

        return [bin2hex($pad($d['ec']['d'])), bin2hex($pad($d['ec']['x']).$pad($d['ec']['y'])), $spki];
    }

    private function signer(string $priv, string $pub, ?callable $spy = null): KmsSponsorSigner
    {
        $handler = KmsSponsorSigner::localHandler($priv);
        if ($spy) {
            $inner = $handler;
            $handler = function (CommandInterface $cmd) use ($inner, $spy) {
                $spy($cmd);

                return $inner($cmd);
            };
        }
        $client = new KmsClient(['version' => '2014-11-01', 'region' => 'eu-central-1', 'handler' => $handler,
            'credentials' => ['key' => 'UNITTESTKEYID0000000', 'secret' => 'unit-test-secret-not-real-00000000']]);

        return new KmsSponsorSigner('aws', self::ARN, $pub, 'eu-central-1', $client);
    }

    public function test_der_to_raw_handles_padding_and_short_integers(): void
    {
        $r = str_repeat("\x81", 32); // high bit set → DER adds a 0x00 byte (33 bytes)
        $s = "\x00\x00".str_repeat("\x12", 30); // short integer (30 bytes in DER)
        $der = KmsSponsorSigner::rawToDer($r.$s);
        $this->assertSame(0x30, ord($der[0]));
        $this->assertSame($r.$s, KmsSponsorSigner::derSignatureToRaw($der));
        $this->assertSame(64, strlen(KmsSponsorSigner::derSignatureToRaw($der)));
        foreach (['', "\x31\x06\x02\x01\x01\x02\x01\x01", $der.'x', "\x30\x06\x02\x01\x81\x02\x01\x01", "\x30\x26\x02\x21".str_repeat("\x01", 33)."\x02\x01\x01"] as $bad) {
            try {
                KmsSponsorSigner::derSignatureToRaw($bad);
                $this->fail('accepted bad DER '.bin2hex($bad));
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_spki_to_public_key_hex(): void
    {
        [, $pub, $spki] = $this->keypair();
        $this->assertSame($pub, KmsSponsorSigner::spkiToPublicKeyHex($spki));
        $rsa = openssl_pkey_get_details(openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]))['key'];
        $this->expectException(RuntimeException::class);
        KmsSponsorSigner::spkiToPublicKeyHex(base64_decode(preg_replace('/-----[A-Z ]+-----|\s/', '', $rsa)));
    }

    public function test_signs_sha256_digest_through_the_sdk_and_verifies(): void
    {
        [$priv, $pub] = $this->keypair();
        $seen = [];
        $signer = $this->signer($priv, $pub, function (CommandInterface $cmd) use (&$seen) {
            $seen[] = [$cmd->getName(), $cmd['MessageType'] ?? null, strlen((string) ($cmd['Message'] ?? '')), $cmd['SigningAlgorithm'] ?? null, $cmd['KeyId']];
        });
        $this->assertSame($pub, $signer->publicKeyFromKms());
        // a Flow envelope with a long Cadence template is far above KMS's 4 KB RAW limit → DIGEST
        $message = str_repeat('FLOW-v0.0-transaction', 400);
        for ($i = 0; $i < 5; $i++) {
            $raw = $signer->sign($message);
            $this->assertSame(64, strlen($raw));
            $this->assertTrue(KmsSponsorSigner::verifyRaw($message, $raw, $pub));
            $s = gmp_import(substr($raw, 32));
            $this->assertLessThanOrEqual(0, gmp_cmp($s, gmp_div_q(gmp_init('ffffffff00000000ffffffffffffffffbce6faada7179e84f3b9cac2fc632551', 16), 2)), 'low-S');
        }
        $this->assertSame(['Sign', 'DIGEST', 32, 'ECDSA_SHA_256', self::ARN], $seen[1]);
        $this->assertSame('GetPublicKey', $seen[0][0]);
    }

    public function test_refuses_a_signature_from_another_key(): void
    {
        [$priv] = $this->keypair();
        [, $otherPub] = $this->keypair();
        $this->expectExceptionMessage('does not match the configured sponsor public key');
        $this->signer($priv, $otherPub)->sign('hello');
    }

    public function test_wrong_key_spec_is_refused(): void
    {
        $client = new KmsClient(['version' => '2014-11-01', 'region' => 'eu-central-1',
            'credentials' => ['key' => 'UNITTESTKEYID0000000', 'secret' => 'unit-test-secret-not-real-00000000'],
            'handler' => fn () => Create::promiseFor(new \Aws\Result(['PublicKey' => 'x', 'KeySpec' => 'ECC_SECG_P256K1', 'KeyUsage' => 'SIGN_VERIFY']))]);
        $this->expectExceptionMessage('ECC_NIST_P256');
        (new KmsSponsorSigner('aws', self::ARN, str_repeat('ab', 64), 'eu-central-1', $client))->publicKeyFromKms();
    }

    public function test_credentials_file_label_value_lines_outside_app_and_private(): void
    {
        $dir = sys_get_temp_dir().'/kms-cred-'.uniqid();
        mkdir($dir);
        $f = $dir.'/aws-kms-sponsor.txt';
        file_put_contents($f, "AWS KMS sponsor (Flovatar)\n\nAccess key ID\nTESTKEYID0EXAMPLE0001\n\nSecret access key\nunit-test-secret-value-not-real-000000000\n");
        chmod($f, 0644);
        try {
            KmsSponsorSigner::readCredentialsFile($f);
            $this->fail('group/world-readable file accepted');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('chmod 600', $e->getMessage());
        }
        chmod($f, 0600);
        $c = KmsSponsorSigner::readCredentialsFile($f);
        $this->assertSame('TESTKEYID0EXAMPLE0001', $c['key']);
        $this->assertSame('unit-test-secret-value-not-real-000000000', $c['secret']);
        try {
            KmsSponsorSigner::readCredentialsFile($f, $dir);
            $this->fail('file inside the app dir accepted');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('outside the application', $e->getMessage());
            $this->assertStringNotContainsString('TESTKEYID', $e->getMessage(), 'never echoes values');
        }
        file_put_contents($f, "access key id\nTESTKEYID0EXAMPLE0001\n");
        try {
            KmsSponsorSigner::readCredentialsFile($f);
            $this->fail('missing secret accepted');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('TESTKEYID', $e->getMessage());
        }
        @unlink($f);
        @rmdir($dir);
    }

    public function test_mock_provider_is_testnet_only_and_mainnet_refuses_key_files(): void
    {
        [$priv, $pub] = $this->keypair();
        config(['services.flow.network' => 'testnet', 'services.flow.rest' => 'https://rest-testnet.onflow.org']);
        $mock = new KmsSponsorSigner('mock', self::ARN, $pub, null, null, null, $priv);
        $this->assertTrue(KmsSponsorSigner::verifyRaw('m', $mock->sign('m'), $pub));

        config(['services.flow.network' => 'mainnet', 'services.flow.rest' => 'https://rest-mainnet.onflow.org']);
        try {
            (new KmsSponsorSigner('mock', self::ARN, $pub, null, null, null, $priv))->sign('m');
            $this->fail('mock KMS used on mainnet');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('testnet only', $e->getMessage());
        }
        config(['passkey.sponsor.driver' => 'file']);
        $this->expectExceptionMessage('PASSKEY_SPONSOR_DRIVER=kms');
        SponsorSigner::fromConfig();
    }

    public function test_mainnet_sponsor_ready_needs_full_aws_config(): void
    {
        config(['services.flow.network' => 'mainnet', 'services.flow.rest' => 'https://rest-mainnet.onflow.org',
            'passkey.sponsor.driver' => 'kms', 'passkey.sponsor.address' => '0x1234567890abcdef',
            'passkey.sponsor.kms' => ['provider' => 'aws', 'key' => self::ARN, 'public_key' => str_repeat('ab', 64)],
            'passkey.contracts.FlowToken' => '0x1654653399040a61', 'passkey.contracts.FungibleToken' => '0xf233dcee88fe0abe']);
        $this->assertTrue(PasskeyPoc::networkReady());
        config(['passkey.contracts.FlowToken' => '0x7e60df042a9c0868']);
        $this->assertFalse(PasskeyPoc::networkReady(), 'testnet contract aliases on mainnet');
        config(['passkey.contracts.FlowToken' => '0x1654653399040a61', 'passkey.sponsor.kms.public_key' => '']);
        $this->assertFalse(PasskeyPoc::networkReady());
    }
}
