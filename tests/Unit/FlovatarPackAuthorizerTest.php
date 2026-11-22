<?php

namespace Tests\Unit;

use App\Services\Flow\FlovatarPackAuthorizer;
use App\Services\Flow\UserMessageSigner;
use Tests\TestCase;
use RuntimeException;

/** Packs v2 authorisations (FlovatarPack verifier message format, no domain tag). */
class FlovatarPackAuthorizerTest extends TestCase
{
    private ?string $keyFile = null;

    protected function tearDown(): void
    {
        if ($this->keyFile) {
            @unlink($this->keyFile);
        }
        parent::tearDown();
    }

    private function signer(): UserMessageSigner
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $hex = str_pad(bin2hex(openssl_pkey_get_details($key)['ec']['d']), 64, '0', STR_PAD_LEFT);
        $this->keyFile = tempnam(sys_get_temp_dir(), 'packsigner');
        file_put_contents($this->keyFile, $hex);
        chmod($this->keyFile, 0600);
        return new UserMessageSigner($this->keyFile, dirname(__DIR__, 2));
    }

    public function test_message_matches_the_on_chain_format(): void
    {
        // Same string FlovatarPack.buildVerifierMessage returned on testnet (owner-kit packs_v2_checks).
        $this->assertSame(
            'FLOVATAR-PACK-V2|dust|7|0x44210379c4d05363|1795000000|1',
            FlovatarPackAuthorizer::message('dust', 7, '44210379C4D05363', 1795000000, 1)
        );
        $this->assertSame('0x0000000000000abc', FlovatarPackAuthorizer::normalizeAddress('0xabc'));
    }

    public function test_rejects_bad_input(): void
    {
        foreach ([['free', 1, '0x1', 1, 1], ['dust', 1, 'zz', 1, 1], ['dust', 1, '0x1', 1, 0]] as $args) {
            try {
                FlovatarPackAuthorizer::message(...$args);
                $this->fail('expected exception for ' . json_encode($args));
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_kind_for_pack(): void
    {
        $this->assertSame('dust', FlovatarPackAuthorizer::kindFor('Dust Flobit Pack', 'dapper'));
        $this->assertSame('dapper', FlovatarPackAuthorizer::kindFor('Flobit Pack', 'dapper'));
        $this->assertSame('flow', FlovatarPackAuthorizer::kindFor('Flobit Pack', null));
    }

    public function test_authorisation_is_signed_without_domain_tag(): void
    {
        $signer = $this->signer();
        config(['flovatar.packsV2Epoch' => 3]);
        $a = (new FlovatarPackAuthorizer($signer))->authorize('dust', 9, '0x5275664a5dd45902', 900, 1795000000);
        $this->assertSame(1795000900, $a['expiry']);
        $this->assertSame(3, $a['epoch']);
        $this->assertSame('0x5275664a5dd45902', $a['recipient']);
        $msg = FlovatarPackAuthorizer::message('dust', 9, '0x5275664a5dd45902', $a['expiry'], $a['epoch']);
        $pub = $signer->publicKeyHex();
        $this->assertTrue(UserMessageSigner::verify($pub, $msg, $a['signature'], ''));
        $this->assertFalse(UserMessageSigner::verify($pub, $msg, $a['signature']));
        $this->assertFalse(UserMessageSigner::verify($pub, str_replace('0x5275664a5dd45902', '0x44210379c4d05363', $msg), $a['signature'], ''));
    }
}
