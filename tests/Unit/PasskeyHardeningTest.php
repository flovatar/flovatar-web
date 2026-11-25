<?php

namespace Tests\Unit;

use App\Models\PasskeyAccount;
use App\Services\Passkey\KmsSponsorSigner;
use App\Services\Passkey\PasskeyCrypto;
use App\Services\Passkey\PasskeyPoc;
use App\Services\Passkey\SponsorSpend;
use App\Services\Passkey\WalletLink;
use App\Services\Passkey\WebAuthnOrigins;
use RuntimeException;
use Tests\TestCase;

/** rp_id / origins for headless + related origins, link-to-wallet Cadence, sponsor spend + KMS signer config. */
class PasskeyHardeningTest extends TestCase
{
    public function test_origins_default_to_rp_id(): void
    {
        config(['passkey.rp_id' => 'testnet.flovatar.com', 'passkey.origins' => []]);
        $this->assertSame(['https://testnet.flovatar.com'], WebAuthnOrigins::allowed());
        $this->assertTrue(WebAuthnOrigins::isAllowed('https://testnet.flovatar.com/'));
        $this->assertFalse(WebAuthnOrigins::isAllowed('https://next-testnet.flovatar.com'));
        $this->assertSame([], WebAuthnOrigins::related());
    }

    public function test_headless_frontends_are_an_exact_allowlist(): void
    {
        config(['passkey.rp_id' => 'testnet.flovatar.com', 'passkey.origins' => ['https://testnet.flovatar.com', 'https://next-testnet.flovatar.com']]);
        $ok = json_encode(['type' => 'webauthn.get', 'challenge' => 'x', 'origin' => 'https://next-testnet.flovatar.com']);
        WebAuthnOrigins::assertClientData($ok);
        $this->assertSame(['https://next-testnet.flovatar.com'], WebAuthnOrigins::related());
        $this->assertSame(['origins' => ['https://testnet.flovatar.com', 'https://next-testnet.flovatar.com']], WebAuthnOrigins::wellKnown());
        // lbuchs' own check is a suffix regex: "evil-testnet.flovatar.com" or "http://" would pass it
        foreach (['https://evil-testnet.flovatar.com', 'http://testnet.flovatar.com', 'https://testnet.flovatar.com.evil.io', ''] as $bad) {
            try {
                WebAuthnOrigins::assertClientData(json_encode(['origin' => $bad]));
                $this->fail('accepted '.$bad);
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('do not accept', $e->getMessage());
            }
        }
    }

    public function test_prod_config_rp_flovatar_com(): void
    {
        config(['passkey.rp_id' => 'flovatar.com', 'passkey.origins' => ['https://flovatar.com', 'https://sportvatar.com']]);
        $this->assertSame('flovatar.com', WebAuthnOrigins::rpId());
        $this->assertSame(['https://sportvatar.com'], WebAuthnOrigins::related());
        $this->assertFalse(WebAuthnOrigins::isAllowed('https://api.flovatar.com'), 'pages are served on the frontend host, not the API origin');
    }

    public function test_transaction_assertion_uses_the_origin_allowlist(): void
    {
        $k = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $d = openssl_pkey_get_details($k);
        $pub = bin2hex(str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT));
        $msg = 'payload-message';
        $authData = hash('sha256', 'testnet.flovatar.com', true)."\x05".pack('N', 1);
        $client = json_encode(['type' => 'webauthn.get', 'challenge' => PasskeyCrypto::base64UrlEncode(PasskeyCrypto::challengeFor($msg)), 'origin' => 'https://next-testnet.flovatar.com']);
        openssl_sign($authData.hash('sha256', $client, true), $der, $k, OPENSSL_ALGO_SHA256);
        $raw = PasskeyCrypto::checkTransactionAssertion($pub, $msg, $authData, $client, $der, 'testnet.flovatar.com', true, ['https://testnet.flovatar.com', 'https://next-testnet.flovatar.com']);
        $this->assertSame(64, strlen($raw));
        $this->expectException(RuntimeException::class);
        PasskeyCrypto::checkTransactionAssertion($pub, $msg, $authData, $client, $der, 'testnet.flovatar.com', true, ['https://testnet.flovatar.com']);
    }

    public function test_link_cadence_resolves_every_address(): void
    {
        config(['passkey.contracts.HybridCustody' => '0x294e44e1ec6993c6', 'passkey.link.fcl_crypto' => '0x74daa6f9c7ef24b1']);
        foreach (['hc_publish_to_parent', 'hc_redeem_account', 'hc_link_status', 'verify_account_proof'] as $name) {
            $code = PasskeyPoc::cadence($name);
            $this->assertDoesNotMatchRegularExpression('/\b0x[A-Z][A-Za-z]+\b/', $code, $name.' has an unresolved alias');
        }
        $publish = PasskeyPoc::cadence('hc_publish_to_parent');
        $this->assertStringContainsString('import HybridCustody, CapabilityFactory, CapabilityFilter from 0x294e44e1ec6993c6', $publish);
        $this->assertStringContainsString('AllowAllFilter', $publish);
        $this->assertStringContainsString('transaction(parent: Address, name: String, description: String, thumbnail: String)', $publish);
        $this->assertStringNotContainsString('giveOwnership', preg_replace('#//.*#', '', $publish), 'ownership transfer would revoke the passkeys');
        $this->assertStringNotContainsString('seal()', preg_replace('#//.*#', '', $publish));
        $this->assertStringContainsString('transaction(childAddress: Address)', WalletLink::claimCadence());
        $args = WalletLink::publishArgs('0xC327C7079FD1B4A3');
        $this->assertSame(['type' => 'Address', 'value' => '0xc327c7079fd1b4a3'], $args[0]);
        $this->assertCount(4, $args);
    }

    public function test_link_parent_rules(): void
    {
        $acc = new PasskeyAccount(['address' => '0x1111111111111111']);
        $this->expectException(RuntimeException::class);
        WalletLink::assertLinkable($acc, '0x1111111111111111');
    }

    public function test_sponsor_spend_from_result(): void
    {
        $sponsor = '0x32097235c152f4f6';
        $r = ['events' => [
            ['type' => 'A.7e60df042a9c0868.FlowToken.TokensWithdrawn', 'values' => ['amount' => 0.06, 'from' => $sponsor]],
            ['type' => 'A.7e60df042a9c0868.FlowToken.TokensWithdrawn', 'values' => ['amount' => 0.001, 'from' => $sponsor]],
            ['type' => 'A.7e60df042a9c0868.FlowToken.TokensWithdrawn', 'values' => ['amount' => 5.0, 'from' => '0x1111111111111111']],
            ['type' => 'A.7e60df042a9c0868.FlowToken.TokensWithdrawn', 'values' => ['amount' => 0.0001, 'from' => $sponsor]],
            ['type' => 'A.912d5440f7e3769e.FlowFees.FeesDeducted', 'values' => ['amount' => 0.0001, 'inclusionEffort' => 1, 'executionEffort' => 0.1]],
        ]];
        $s = SponsorSpend::fromResult($r, $sponsor);
        $this->assertEqualsWithDelta(0.0001, $s['fee'], 1e-9);
        $this->assertEqualsWithDelta(0.0611, $s['outflow'], 1e-9);
        $this->assertNull(SponsorSpend::fromResult(['events' => []], $sponsor)['fee']);
    }

    public function test_kms_signer_config(): void
    {
        try {
            new KmsSponsorSigner('aws', 'arn:aws:kms:eu:1:key/x', 'not-hex');
            $this->fail('bad public key accepted');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('128 hex', $e->getMessage());
        }
        $s = new KmsSponsorSigner('gcp', 'projects/x', str_repeat('ab', 64));
        $this->assertSame(str_repeat('ab', 64), $s->publicKeyHex());
        $this->expectException(RuntimeException::class);
        $s->sign('message'); // gcp not wired: refuses instead of signing wrongly
    }

    public function test_sponsor_driver_selection(): void
    {
        config(['passkey.sponsor.driver' => 'kms', 'passkey.sponsor.kms' => ['provider' => 'aws', 'key' => 'arn', 'public_key' => str_repeat('cd', 64)]]);
        $this->assertInstanceOf(KmsSponsorSigner::class, \App\Services\Passkey\SponsorSigner::fromConfig());
        config(['passkey.sponsor.driver' => 'file']);
        $this->assertInstanceOf(\App\Services\Passkey\SponsorSigner::class, \App\Services\Passkey\SponsorSigner::fromConfig());
    }
}
