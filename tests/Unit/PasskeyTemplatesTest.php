<?php

namespace Tests\Unit;

use App\Services\Passkey\FlowTransaction;
use App\Services\Passkey\TemplateRegistry;
use RuntimeException;
use Tests\TestCase;

/**
 * Passkey signing allowlist (config/passkey-templates.php) + manifest (resources/passkey/templates.json) + tx building.
 */
class PasskeyTemplatesTest extends TestCase
{
    private const ALIASES = [
        '0xProfile' => '0x35717efbbce11c74', '0xFungible' => '0x9a0766d93b6608b7', '0xNonFungible' => '0x631e88ae7f1d7c20',
        '0xFlowToken' => '0x7e60df042a9c0868', '0xFlovatar' => '0x2c6a4c4342aeca55', '0xComponent' => '0x2c6a4c4342aeca55',
        '0xTemplate' => '0x2c6a4c4342aeca55', '0xPack' => '0x2c6a4c4342aeca55', '0xMarketplace' => '0x2c6a4c4342aeca55',
        '0xFindProfile' => '0x35717efbbce11c74', '0xFind' => '0x35717efbbce11c74', '0xMetadataViews' => '0x631e88ae7f1d7c20',
        '0xStorefront2' => '0x2d55b98eb200daef', '0xDuc' => '0x82ec283f88a62e65', '0xFut' => '0x82ec283f88a62e65',
        '0xTokenForwarding' => '0x51ea0e37c27a1f1a', '0xDapperMerchant' => '0x03df89ac89a3d64a', '0xHybridCustody' => '0x294e44e1ec6993c6',
    ];

    private function registry(): TemplateRegistry
    {
        return new TemplateRegistry(null, require base_path('config/passkey-templates.php'), self::ALIASES);
    }

    public function test_manifest_is_up_to_date_with_the_js_transactions(): void
    {
        $node = trim((string) shell_exec('command -v node'));
        if ($node === '') {
            $this->markTestSkipped('node not installed');
        }
        exec(escapeshellarg($node).' '.escapeshellarg(base_path('bin/passkey-templates.js')).' --check 2>&1', $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));
    }

    public function test_every_pin_matches_the_manifest_and_the_cadence(): void
    {
        $allow = require base_path('config/passkey-templates.php');
        $manifest = TemplateRegistry::loadManifest();
        $this->assertGreaterThan(40, count($allow));
        foreach ($allow as $id => $pin) {
            $this->assertArrayHasKey($id, $manifest, $id);
            $this->assertSame($pin['sha256'], $manifest[$id]['sha256'], $id);
            $this->assertSame($pin['sha256'], hash('sha256', $manifest[$id]['cadence']), $id);
            $this->assertNotEmpty($pin['label']);
            // every allowlisted template resolves (only one authorizer, no custom signers, all aliases known)
            $tpl = $this->registry()->find($pin['sha256']);
            $code = $this->registry()->resolve($tpl['cadence']);
            $this->assertDoesNotMatchRegularExpression('/\b0x[A-Za-z][A-Za-z0-9_]*\b/', $code, $id);
            $this->assertCount(count($manifest[$id]['argTypes']), TemplateRegistry::parameterTypes($tpl['cadence']), $id);
        }
    }

    public function test_dapper_and_hybrid_templates_are_not_allowed(): void
    {
        $allow = require base_path('config/passkey-templates.php');
        foreach (array_keys($allow) as $id) {
            $this->assertStringNotContainsString('dapper', $id);
        }
        $manifest = TemplateRegistry::loadManifest();
        foreach (['flovatar-sale-buy-dapper', 'pack-buy-dapper-v2', 'sign-dapper-message', 'init-account-dapper', 'claim-child-dust-by-id'] as $id) {
            try {
                $this->registry()->find($manifest[$id]['sha256']);
                $this->fail($id.' must not be allowed');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('not available', $e->getMessage());
            }
        }
    }

    public function test_unknown_hash_and_tampered_manifest_are_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->registry()->find(hash('sha256', 'transaction { prepare(a: &Account) {} }'));
    }

    public function test_manifest_drift_is_rejected(): void
    {
        $manifest = TemplateRegistry::loadManifest();
        $manifest['flovatar-add-hat']['cadence'] .= "\n// changed";
        $reg = new TemplateRegistry($manifest, require base_path('config/passkey-templates.php'), self::ALIASES);
        $this->expectExceptionMessage('pinned hash');
        $reg->find('flovatar-add-hat');
    }

    public function test_argument_validation(): void
    {
        $reg = $this->registry();
        $hat = $reg->find('flovatar-add-hat');
        $this->assertSame(['UInt64', 'UInt64'], TemplateRegistry::parameterTypes($hat['cadence']));
        $ok = $reg->validateArgs($hat['cadence'], [['type' => 'UInt64', 'value' => '12'], ['type' => 'UInt64', 'value' => '7']]);
        $this->assertSame('12', $ok[0]['value']);

        foreach ([
            [['type' => 'UInt64', 'value' => '12']],                                             // count
            [['type' => 'UInt64', 'value' => '12'], ['type' => 'String', 'value' => '7']],       // type
            [['type' => 'UInt64', 'value' => '-1'], ['type' => 'UInt64', 'value' => '7']],       // range
            [['type' => 'UInt64', 'value' => 12], ['type' => 'UInt64', 'value' => '7']],         // not a string
        ] as $bad) {
            try {
                $reg->validateArgs($hat['cadence'], $bad);
                $this->fail('accepted '.json_encode($bad));
            } catch (RuntimeException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }

        $create = $reg->find('flovatar-create');
        $types = TemplateRegistry::parameterTypes($create['cadence']);
        $this->assertSame('UInt64?', $types[3]);
        $this->assertStringStartsWith('[', $types[12]);
        $args = [];
        foreach ($types as $tp) {
            $args[] = match (true) {
                str_ends_with($tp, '?') => ['type' => 'Optional', 'value' => null],
                str_starts_with($tp, '[') => ['type' => 'Array', 'value' => [['type' => 'UInt64', 'value' => '3']]],
                default => ['type' => $tp, 'value' => '1'],
            };
        }
        $this->assertCount(count($types), $reg->validateArgs($create['cadence'], $args));

        $gift = $reg->find('flovatar-send-gift');
        $out = $reg->validateArgs($gift['cadence'], [['type' => 'UInt64', 'value' => '1'], ['type' => 'Address', 'value' => '0x2C6A4C4342AECA55']]);
        $this->assertSame('0x2c6a4c4342aeca55', $out[1]['value']);

        $sale = $reg->find('flovatar-sale-create');
        $this->assertSame('10.00', $reg->validateArgs($sale['cadence'], [['type' => 'UInt64', 'value' => '1'], ['type' => 'UFix64', 'value' => '10.00']])[1]['value']);
        $this->assertSame('10.0', $reg->validateArgs($sale['cadence'], [['type' => 'UInt64', 'value' => '1'], ['type' => 'UFix64', 'value' => '10']])[1]['value']);
        $this->expectException(RuntimeException::class);
        $reg->validateArgs($sale['cadence'], [['type' => 'UInt64', 'value' => '1'], ['type' => 'UFix64', 'value' => '1e-7']]);
    }

    public function test_alias_resolution_matches_fcl(): void
    {
        $reg = $this->registry();
        $code = $reg->resolve($reg->find('init-account-dust')['cadence']);
        $this->assertStringContainsString('from 0x2c6a4c4342aeca55', $code);
        $this->assertStringContainsString('from 0x631e88ae7f1d7c20', $code);

        $missing = new TemplateRegistry(null, require base_path('config/passkey-templates.php'), ['0xFlovatar' => null] + self::ALIASES);
        $this->expectExceptionMessage('not configured');
        $missing->resolve($missing->find('init-account-dust')['cadence']);
    }

    public function test_tx_built_from_a_template_has_passkey_proposer_and_sponsor_payer(): void
    {
        $reg = $this->registry();
        $tpl = $reg->find('flovatar-remove-hat');
        $args = $reg->validateArgs($tpl['cadence'], [['type' => 'UInt64', 'value' => '5']]);
        $user = '0x89441b070df05cbf';
        $sponsor = '0x32097235c152f4f6';
        $tx = new FlowTransaction($reg->resolve($tpl['cadence']), $args, str_repeat('ab', 32), 9999, $user, 1, 42, $sponsor, [$user]);
        $body = $tx->toRestBody();
        $this->assertSame(FlowTransaction::addr($user), $body['proposal_key']['address']);
        $this->assertSame('1', (string) $body['proposal_key']['key_index']);
        $this->assertSame(FlowTransaction::addr($sponsor), $body['payer']);
        $this->assertSame([FlowTransaction::addr($user)], $body['authorizers']);
        $this->assertSame($reg->resolve($tpl['cadence']), base64_decode($body['script']));
        $this->assertSame(['type' => 'UInt64', 'value' => '5'], json_decode(base64_decode($body['arguments'][0]), true));
        // payload signer (passkey) and envelope signer (sponsor) are different accounts
        $this->assertSame([FlowTransaction::addr($user) => 0, FlowTransaction::addr($sponsor) => 1], $tx->signerIndex());
    }
}
