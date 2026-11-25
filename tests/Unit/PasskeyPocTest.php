<?php

namespace Tests\Unit;

use App\Services\Passkey\FlowTransaction;
use App\Services\Passkey\PasskeyCrypto;
use App\Services\Passkey\Rlp;
use App\Services\Passkey\SponsorSigner;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Passkey PoC (FLIP-264) encoding + crypto. Vectors for the transaction encodings come from @onflow/sdk 1.x
 * (encodeTransactionPayload / encodeTransactionEnvelope, which supports signature extension data) and
 * @onflow/rlp; the assertion test runs a software "authenticator" with openssl.
 */
class PasskeyPocTest extends TestCase
{
    private const PAYLOAD = '464c4f572d56302e302d7472616e73616374696f6e0000000000000000000000f8e4b8537472616e73616374696f6e28613a20537472696e672c206e3a2055496e74363429207b207072657061726528733a20617574682853746f726167652920264163636f756e7429207b206c6f67286129207d207df84aaa7b2274797065223a22537472696e67222c2276616c7565223a2268656c6c6f2f666c6f7661746172227d9e7b2274797065223a2255496e743634222c2276616c7565223a223432227da0f0e4c2f76c58916ec258f246851bea091d14d4247a2fc3e18694461b1816e13b82270f8801cf0e2f2f7154500381818832097235c152f4f6c98801cf0e2f2f715450';
    private const ENVELOPE_EXT = '464c4f572d56302e302d7472616e73616374696f6e0000000000000000000000f90177f8e4b8537472616e73616374696f6e28613a20537472696e672c206e3a2055496e74363429207b207072657061726528733a20617574682853746f726167652920264163636f756e7429207b206c6f67286129207d207df84aaa7b2274797065223a22537472696e67222c2276616c7565223a2268656c6c6f2f666c6f7661746172227d9e7b2274797065223a2255496e743634222c2276616c7565223a223432227da0f0e4c2f76c58916ec258f246851bea091d14d4247a2fc3e18694461b1816e13b82270f8801cf0e2f2f7154500381818832097235c152f4f6c98801cf0e2f2f715450f88ff88d8003b840aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaab84701cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';
    private const ENVELOPE_PLAIN = '464c4f572d56302e302d7472616e73616374696f6e0000000000000000000000f9012ef8e4b8537472616e73616374696f6e28613a20537472696e672c206e3a2055496e74363429207b207072657061726528733a20617574682853746f726167652920264163636f756e7429207b206c6f67286129207d207df84aaa7b2274797065223a22537472696e67222c2276616c7565223a2268656c6c6f2f666c6f7661746172227d9e7b2274797065223a2255496e743634222c2276616c7565223a223432227da0f0e4c2f76c58916ec258f246851bea091d14d4247a2fc3e18694461b1816e13b82270f8801cf0e2f2f7154500381818832097235c152f4f6c98801cf0e2f2f715450f846f8448003b840aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private function tx(): FlowTransaction
    {
        return new FlowTransaction(
            'transaction(a: String, n: UInt64) { prepare(s: auth(Storage) &Account) { log(a) } }',
            [['type' => 'String', 'value' => 'hello/flovatar'], ['type' => 'UInt64', 'value' => '42']],
            'f0e4c2f76c58916ec258f246851bea091d14d4247a2fc3e18694461b1816e13b',
            9999, '0x01cf0e2f2f715450', 3, 129, '0x32097235c152f4f6', ['0x01cf0e2f2f715450'],
        );
    }

    public function test_rlp_basics(): void
    {
        $this->assertSame("\x80", Rlp::encode(''));
        $this->assertSame("\x80", Rlp::encode(0));
        $this->assertSame("\x0f", Rlp::encode(15));
        $this->assertSame("\x82\x04\x00", Rlp::encode(1024));
        $this->assertSame("\x83dog", Rlp::encode('dog'));
        $this->assertSame("\xc8\x83cat\x83dog", Rlp::encode(['cat', 'dog']));
        $this->assertSame("\xc0", Rlp::encode([]));
        $long = str_repeat('a', 56);
        $this->assertSame("\xb8\x38".$long, Rlp::encode($long));
    }

    public function test_payload_matches_fcl(): void
    {
        $this->assertSame(self::PAYLOAD, bin2hex($this->tx()->payloadMessage()));
    }

    public function test_envelope_with_webauthn_extension_matches_fcl(): void
    {
        $tx = $this->tx();
        $tx->addPayloadSignature('0x01cf0e2f2f715450', 3, str_repeat("\xaa", 64), "\x01".str_repeat("\xcc", 70));
        $this->assertSame(self::ENVELOPE_EXT, bin2hex($tx->envelopeMessage()));
    }

    public function test_envelope_plain_signature_matches_fcl(): void
    {
        $tx = $this->tx();
        $tx->addPayloadSignature('0x01cf0e2f2f715450', 3, str_repeat("\xaa", 64));
        $this->assertSame(self::ENVELOPE_PLAIN, bin2hex($tx->envelopeMessage()));
    }

    public function test_signer_index_and_rest_body(): void
    {
        $tx = $this->tx();
        $this->assertSame(['01cf0e2f2f715450' => 0, '32097235c152f4f6' => 1], $tx->signerIndex());
        $tx->addPayloadSignature('0x01cf0e2f2f715450', 3, str_repeat("\xaa", 64), "\x01abc");
        $tx->addEnvelopeSignature('0x32097235c152f4f6', 0, str_repeat("\xbb", 64));
        $b = $tx->toRestBody();
        $this->assertSame('9999', $b['gas_limit']);
        $this->assertSame(['address' => '01cf0e2f2f715450', 'key_index' => '3', 'sequence_number' => '129'], $b['proposal_key']);
        $this->assertSame(base64_encode("\x01abc"), $b['payload_signatures'][0]['extension_data']);
        $this->assertArrayNotHasKey('extension_data', $b['envelope_signatures'][0]);
        $this->assertSame('{"type":"String","value":"hello/flovatar"}', base64_decode($b['arguments'][0]));
        $copy = FlowTransaction::fromArray($this->tx()->toArray());
        $this->assertSame(bin2hex($this->tx()->payloadMessage()), bin2hex($copy->payloadMessage()));
    }

    public function test_extension_data_matches_onflow_rlp(): void
    {
        $ext = PasskeyCrypto::extensionData(hex2bin('49960de5880e8c687434170f6476605b8fe4aeb9a28632c7995cf3ba831d97630500000001'), '{"type":"webauthn.get","challenge":"3q2-7w","origin":"https://testnet.flovatar.com","crossOrigin":false}');
        $this->assertSame('01f890a549960de5880e8c687434170f6476605b8fe4aeb9a28632c7995cf3ba831d97630500000001b8687b2274797065223a22776562617574686e2e676574222c226368616c6c656e6765223a223371322d3777222c226f726967696e223a2268747470733a2f2f746573746e65742e666c6f76617461722e636f6d222c2263726f73734f726967696e223a66616c73657d', bin2hex($ext));
        $this->assertSame("\x01", $ext[0]);
    }

    public function test_der_to_raw(): void
    {
        // r with a leading 0x00 (high bit set), s short (31 bytes) -> both left-padded to 32 bytes
        $r = "\x00".str_repeat("\x81", 32);
        $s = str_repeat("\x11", 31);
        $der = "\x30".chr(4 + 33 + 31)."\x02\x21".$r."\x02\x1f".$s;
        $raw = PasskeyCrypto::derToRaw($der);
        $this->assertSame(64, strlen($raw));
        $this->assertSame(str_repeat('81', 32).'00'.str_repeat('11', 31), bin2hex($raw));
        $this->expectException(RuntimeException::class);
        PasskeyCrypto::derToRaw("\x31\x00");
    }

    public function test_base64url_roundtrip(): void
    {
        $bin = random_bytes(33);
        $this->assertSame($bin, PasskeyCrypto::base64UrlDecode(PasskeyCrypto::base64UrlEncode($bin)));
        $this->assertStringNotContainsString('=', PasskeyCrypto::base64UrlEncode("\xff\xfe"));
    }

    /** Software authenticator: P-256 key, sign authData || SHA256(clientDataJSON) exactly like WebAuthn. */
    public function test_transaction_assertion_end_to_end(): void
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $d = openssl_pkey_get_details($key);
        $pubHex = PasskeyCrypto::publicKeyHexFromPem($d['key']);
        $this->assertSame(128, strlen($pubHex));
        $this->assertSame($pubHex, PasskeyCrypto::publicKeyHexFromPem(PasskeyCrypto::pemFromPublicKeyHex($pubHex)));

        $tx = $this->tx();
        $message = $tx->payloadMessage();
        $rpId = 'testnet.flovatar.com';
        $clientData = json_encode(['type' => 'webauthn.get', 'challenge' => PasskeyCrypto::base64UrlEncode(hash('sha256', $message, true)), 'origin' => 'https://'.$rpId, 'crossOrigin' => false]);
        $authData = hash('sha256', $rpId, true)."\x05".pack('N', 7); // UP + UV
        openssl_sign($authData.hash('sha256', $clientData, true), $der, $key, OPENSSL_ALGO_SHA256);

        $raw = PasskeyCrypto::checkTransactionAssertion($pubHex, $message, $authData, $clientData, $der, $rpId);
        $this->assertSame(64, strlen($raw));

        // Flow's verifier (FLIP-264): ECDSA_P256 over SHA2-256(authData || SHA2-256(clientDataJSON)) with the raw r||s
        $this->assertSame(1, openssl_verify($authData.hash('sha256', $clientData, true), \App\Services\Flow\UserMessageSigner::rawToDer($raw), PasskeyCrypto::pemFromPublicKeyHex($pubHex), OPENSSL_ALGO_SHA256));

        foreach ([
            'wrong tx' => fn () => PasskeyCrypto::checkTransactionAssertion($pubHex, $message.'x', $authData, $clientData, $der, $rpId),
            'wrong rp' => fn () => PasskeyCrypto::checkTransactionAssertion($pubHex, $message, $authData, $clientData, $der, 'evil.example'),
            'no UV' => fn () => PasskeyCrypto::checkTransactionAssertion($pubHex, $message, substr($authData, 0, 32)."\x01".pack('N', 7), $clientData, $der, $rpId),
        ] as $case => $fn) {
            try {
                $fn();
                $this->fail("$case accepted");
            } catch (RuntimeException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function test_rejects_non_p256_keys(): void
    {
        $rsa = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $this->expectException(RuntimeException::class);
        PasskeyCrypto::publicKeyHexFromPem(openssl_pkey_get_details($rsa)['key']);
    }

    public function test_sponsor_signer_signs_sha2_256(): void
    {
        $dir = sys_get_temp_dir().'/pk-'.bin2hex(random_bytes(4));
        mkdir($dir, 0700);
        $file = $dir.'/sponsor.pkey';
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $d = openssl_pkey_get_details($key);
        file_put_contents($file, bin2hex(str_pad($d['ec']['d'], 32, "\0", STR_PAD_LEFT)));
        chmod($file, 0600);
        try {
            $signer = new SponsorSigner($file);
            $pub = $signer->publicKeyHex();
            $this->assertSame(PasskeyCrypto::publicKeyHexFromPem($d['key']), $pub);
            $msg = $this->tx()->envelopeMessage();
            $raw = $signer->sign($msg);
            $this->assertSame(1, openssl_verify($msg, \App\Services\Flow\UserMessageSigner::rawToDer($raw), PasskeyCrypto::pemFromPublicKeyHex($pub), OPENSSL_ALGO_SHA256));
            chmod($file, 0644);
            $this->expectException(RuntimeException::class);
            (new SponsorSigner($file))->sign($msg);
        } finally {
            @unlink($file);
            @rmdir($dir);
        }
    }
}
