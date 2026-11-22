<?php

namespace Tests\Unit;

use App\Services\Flow\UserMessageSigner;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * UserMessageSigner vs the legacy Go pack signer (flow-go-sdk fork, examples/user_signature).
 *
 * tests/Fixtures/flow-user-signature-go-vectors.json was produced by building the Go tool locally with a throwaway
 * key (only its public key is stored). ECDSA nonces are random in both implementations, so signatures can't be
 * compared byte for byte; instead we check that the signed bytes and their digest are identical, that Go signatures
 * verify here, and that PHP signatures verified with the Go SDK (recorded in the fixture).
 */
class UserMessageSignerTest extends TestCase
{
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private static function vectors(): array
    {
        return json_decode(file_get_contents(__DIR__ . '/../Fixtures/flow-user-signature-go-vectors.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    /** Fresh P-256 key in a chmod-600 temp file (outside the repo). Returns [path, keyHex]. */
    private function freshKeyFile(int $mode = 0600, ?string $contents = null): array
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $hex = str_pad(bin2hex(openssl_pkey_get_details($key)['ec']['d']), 64, '0', STR_PAD_LEFT);
        $path = tempnam(sys_get_temp_dir(), 'packsigner');
        file_put_contents($path, $contents ?? ($hex . "\n"));
        chmod($path, $mode);
        $this->tmpFiles[] = $path;
        return [$path, $hex];
    }

    private function appDir(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_domain_tag_is_right_padded_to_32_bytes_like_the_go_sdk(): void
    {
        $tag = UserMessageSigner::paddedTag('FLOW-V0.0-user');
        $this->assertSame(32, strlen($tag));
        $this->assertSame('464c4f572d56302e302d75736572' . str_repeat('00', 18), bin2hex($tag));
        $this->assertSame('', UserMessageSigner::paddedTag(''));
    }

    public function test_preimage_and_digest_equal_the_go_sdk_for_every_vector(): void
    {
        $v = self::vectors();
        $this->assertGreaterThanOrEqual(8, count($v['vectors']));
        foreach ($v['vectors'] as $vec) {
            $this->assertSame($vec['preimageHex'], bin2hex(UserMessageSigner::preimage($vec['message'])), 'pre-image: ' . $vec['message']);
            $this->assertSame($vec['digestHex'], UserMessageSigner::digestHex($vec['message']), 'digest: ' . $vec['message']);
        }
    }

    public function test_go_tool_signatures_verify_with_php(): void
    {
        $v = self::vectors();
        foreach ($v['vectors'] as $vec) {
            foreach ($vec['goSignatures'] as $i => $sig) {
                // legacy SignPack parsed the Go stdout as JSON: ["<hex>"]
                $this->assertSame($sig, json_decode($vec['goStdout'][$i])[0]);
                $this->assertSame(128, strlen($sig));
                $this->assertTrue(UserMessageSigner::verify($v['publicKey'], $vec['message'], $sig), 'go sig: ' . $vec['message']);
                $this->assertFalse(UserMessageSigner::verify($v['publicKey'], $vec['message'] . ' ', $sig), 'tampered: ' . $vec['message']);
                $this->assertFalse(UserMessageSigner::verify($v['publicKey'], $vec['message'], $sig, ''), 'no tag: ' . $vec['message']);
            }
        }
    }

    public function test_php_signatures_were_accepted_by_the_go_sdk(): void
    {
        $v = self::vectors();
        foreach ($v['vectors'] as $vec) {
            $this->assertTrue($vec['phpSignatureVerifiedByGoSdk'], $vec['message']);
            $this->assertTrue(UserMessageSigner::verify($v['publicKey'], $vec['message'], $vec['phpSignature']));
            $this->assertTrue(UserMessageSigner::isLowS($vec['phpSignature']));
        }
    }

    public function test_low_s_normalisation_keeps_go_signatures_valid(): void
    {
        $v = self::vectors();
        $seenHigh = 0;
        foreach ($v['vectors'] as $vec) {
            foreach ($vec['goSignatures'] as $sig) {
                $norm = bin2hex(UserMessageSigner::normalizeLowS(hex2bin($sig)));
                $this->assertTrue(UserMessageSigner::isLowS($norm));
                $this->assertSame(substr($sig, 0, 64), substr($norm, 0, 64));
                $this->assertTrue(UserMessageSigner::verify($v['publicKey'], $vec['message'], $norm));
                if (!UserMessageSigner::isLowS($sig)) {
                    $seenHigh++;
                    $this->assertNotSame($sig, $norm);
                }
            }
        }
        $this->assertGreaterThan(0, $seenHigh, 'fixture should contain high-S Go signatures');
    }

    public function test_der_raw_round_trip(): void
    {
        $v = self::vectors();
        foreach ($v['vectors'] as $vec) {
            foreach ($vec['goSignatures'] as $sig) {
                $raw = hex2bin($sig);
                $this->assertSame($raw, UserMessageSigner::derToRaw(UserMessageSigner::rawToDer($raw)));
            }
        }
    }

    public function test_sign_and_verify_with_a_fresh_key(): void
    {
        [$path] = $this->freshKeyFile();
        $signer = new UserMessageSigner($path, $this->appDir());
        $pub = $signer->publicKeyHex();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{128}$/', $pub);
        foreach (array_column(self::vectors()['vectors'], 'message') as $m) {
            $sig = $signer->sign($m);
            $this->assertMatchesRegularExpression('/^[0-9a-f]{128}$/', $sig);
            $this->assertTrue(UserMessageSigner::isLowS($sig));
            $this->assertTrue(UserMessageSigner::verify($pub, $m, $sig));
            $this->assertFalse(UserMessageSigner::verify($pub, $m . 'x', $sig));
            $this->assertFalse(UserMessageSigner::verify($pub, $m, $sig, ''));
            // v2 authorisations: no domain tag
            $raw = $signer->sign($m, UserMessageSigner::NO_DOMAIN_TAG);
            $this->assertTrue(UserMessageSigner::verify($pub, $m, $raw, ''));
            $this->assertFalse(UserMessageSigner::verify($pub, $m, $raw));
        }
    }

    public function test_key_file_must_be_configured(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PACK_SIGNER_KEY_FILE');
        (new UserMessageSigner(null, $this->appDir()))->sign('x');
    }

    public function test_key_file_readable_by_others_is_refused(): void
    {
        [$path, $hex] = $this->freshKeyFile(0644);
        try {
            (new UserMessageSigner($path, $this->appDir()))->sign('x');
            $this->fail('expected exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('chmod 600', $e->getMessage());
            $this->assertStringNotContainsString($hex, $e->getMessage());
        }
    }

    public function test_key_file_inside_the_application_is_refused(): void
    {
        [$tmp, $hex] = $this->freshKeyFile();
        $inside = $this->appDir() . '/storage/framework/testing-pack-signer-' . getmypid() . '.pkey';
        is_dir(dirname($inside)) || mkdir(dirname($inside), 0775, true);
        copy($tmp, $inside);
        chmod($inside, 0600);
        $this->tmpFiles[] = $inside;
        try {
            (new UserMessageSigner($inside, $this->appDir()))->sign('x');
            $this->fail('expected exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('outside the application', $e->getMessage());
            $this->assertStringNotContainsString($hex, $e->getMessage());
        }
    }

    public function test_malformed_key_file_is_refused_without_echoing_it(): void
    {
        $secretish = str_repeat('zz', 32);
        [$path] = $this->freshKeyFile(0600, $secretish);
        try {
            (new UserMessageSigner($path, $this->appDir()))->sign('x');
            $this->fail('expected exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('64 hex characters', $e->getMessage());
            $this->assertStringNotContainsString($secretish, $e->getMessage());
        }
    }
}
