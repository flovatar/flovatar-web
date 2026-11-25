<?php

namespace Tests\Unit;

use App\Services\Flow\FlowRestClient;
use App\Services\Flow\UserMessageSigner;
use Tests\TestCase;

/**
 * On-chain check (testnet, read-only): a UserMessageSigner signature is accepted by Cadence
 * Crypto.KeyList.verify(..., domainSeparationTag: "FLOW-V0.0-user") against an account key, exactly like
 * FlovatarPack v1 checks pack signatures. Skipped unless these are set:
 *   FLOW_TESTNET_SIGNER_ADDRESS   testnet account (e.g. 0xa046baa4d52a53bc)
 *   FLOW_TESTNET_SIGNER_KEY_FILE  private key of one of its keys (chmod 600, outside the repo)
 *   FLOW_TESTNET_SIGNER_KEY_INDEX key index (default 0)
 * Run: php artisan test --group=testnet
 *
 * @group testnet
 */
class UserMessageSignerTestnetTest extends TestCase
{
    private const SCRIPT = <<<'CDC'
import Crypto
access(all) fun main(signer: Address, keyIndex: Int, message: String, signature: String): Bool {
    let accountKey = getAccount(signer).keys.get(keyIndex: keyIndex)!.publicKey
    let keyList = Crypto.KeyList()
    keyList.add(PublicKey(publicKey: accountKey.publicKey, signatureAlgorithm: accountKey.signatureAlgorithm), hashAlgorithm: HashAlgorithm.SHA3_256, weight: 1.0)
    let sigs: [Crypto.KeyListSignature] = [Crypto.KeyListSignature(keyIndex: 0, signature: signature.decodeHex())]
    return keyList.verify(signatureSet: sigs, signedData: message.utf8, domainSeparationTag: "FLOW-V0.0-user")
}
CDC;

    public function test_signature_verifies_on_testnet_against_an_account_key(): void
    {
        $address = getenv('FLOW_TESTNET_SIGNER_ADDRESS');
        $keyFile = getenv('FLOW_TESTNET_SIGNER_KEY_FILE');
        if (! $address || ! $keyFile) {
            $this->markTestSkipped('FLOW_TESTNET_SIGNER_ADDRESS / FLOW_TESTNET_SIGNER_KEY_FILE not set');
        }
        $index = (int) (getenv('FLOW_TESTNET_SIGNER_KEY_INDEX') ?: 0);
        $signer = new UserMessageSigner($keyFile, base_path());
        $flow = new FlowRestClient('https://rest-testnet.onflow.org');
        $run = fn (string $m, string $s) => $flow->script(self::SCRIPT, [
            ['type' => 'Address', 'value' => $address],
            ['type' => 'Int', 'value' => (string) $index],
            ['type' => 'String', 'value' => $m],
            ['type' => 'String', 'value' => $s],
        ]);

        foreach (['aB3dE5gH7jK9mN1pQ3sT5vW7yZ9bC1dF', 'ünïcødé ✓', str_repeat('x', 300)] as $message) {
            $sig = $signer->sign($message);
            $this->assertTrue($run($message, $sig), 'on-chain verify: '.$message);
            $this->assertFalse($run($message.'!', $sig), 'altered message must fail');
            $this->assertFalse($run($message, $signer->sign($message, UserMessageSigner::NO_DOMAIN_TAG)), 'untagged signature must fail');
        }
    }
}
