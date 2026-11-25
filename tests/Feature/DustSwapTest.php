<?php

namespace Tests\Feature;

use App\Http\Middleware\ThrottleRequestsPerRoute;
use App\Services\Passkey\TemplateRegistry;
use App\Support\DustSwap;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * ĐUST ⇄ FLOW swap POC (KB notes/dust-swap.md): DUST_SWAP_ENABLED off = no markup, no window.DUST_SWAP, no passkey
 * swap templates; on (testnet) = rate chip, widget, pay-with partials, and ONLY the FLOW → ĐUST templates allowlisted.
 */
class DustSwapTest extends TestCase
{
    /** Testnet FCL aliases (as in tests/Unit/PasskeyTemplatesTest) + our testnet Increment copy. */
    private const ALIASES = [
        '0xFungible' => '0x9a0766d93b6608b7', '0xNonFungible' => '0x631e88ae7f1d7c20', '0xMetadataViews' => '0x631e88ae7f1d7c20',
        '0xFlowToken' => '0x7e60df042a9c0868', '0xFlovatar' => '0x2c6a4c4342aeca55', '0xStorefront2' => '0x2d55b98eb200daef',
        '0xSwapRouter' => '0x84e6590ef1493ae7', '0xSwapConfig' => '0x84e6590ef1493ae7', '0xSwapFactory' => '0x84e6590ef1493ae7',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'session.driver' => 'array',
            'cache.default' => 'array',
            'headless.enabled' => false,
            'services.flow.network' => 'testnet',
            'flovatar.dustSwap.enabled' => false,
        ]);
        DB::purge();
        $this->withoutMiddleware(ThrottleRequestsPerRoute::class);
    }

    private function partials(): string
    {
        return view('partials.dust-swap-widget')->render()
            .view('partials.dust-swap-pay')->render()
            .view('partials.dust-swap-pay-dust', ['swapKind' => 'flobit'])->render();
    }

    public function test_flag_off_renders_nothing(): void
    {
        $this->assertFalse(DustSwap::enabled());
        $this->assertSame('', trim($this->partials()));
        $html = $this->get('/dust')->assertOk()->getContent();
        $this->assertStringNotContainsString('window.DUST_SWAP', $html);
        $this->assertStringNotContainsString('dustSwapWidget', $html);
        $this->assertStringNotContainsString('dustRateChip', $html);
        $this->assertStringContainsString('app.increment.fi/swap', $html, 'flag off keeps the old "Buy ĐUST" link');
    }

    public function test_flag_on_renders_widget_chip_and_config(): void
    {
        config(['flovatar.dustSwap.enabled' => true]);
        $html = $this->get('/dust')->assertOk()->getContent();
        $this->assertStringContainsString('window.DUST_SWAP', $html);
        $this->assertStringContainsString('x-data="dustSwapWidget()"', $html);
        $this->assertStringContainsString('x-data="dustRateChip()"', $html);
        $this->assertStringContainsString('href="/dust#swap"', $html);
        $this->assertStringContainsString('Swap ÐUST</a>', $html, 'nav links to the widget instead of Increment');
        $partials = $this->partials();
        $this->assertStringContainsString('dustSwapPay()', $partials);
        $this->assertStringContainsString("dustSwapPayDust('flobit')", $partials);

        $b = DustSwap::bootstrap();
        $this->assertSame('testnet', $b['network']);
        $this->assertSame('0x84e6590ef1493ae7', $b['router']);
        $this->assertSame('0x76d585e079b73059', $b['pair']);
        $this->assertSame(1.0, $b['slippageDefault']);
        $this->assertSame(3.0, $b['slippageMax']);
        $this->assertSame(2.0, $b['impactWarn']);
        $this->assertSame(10.0, $b['impactBlock']);
        $this->assertSame(300, $b['deadlineSeconds']);
        $this->assertTrue($b['passkeyBuy']);
        // static-safe: nothing user specific, never USD
        $this->assertStringNotContainsStringIgnoringCase('usd', json_encode($b));
    }

    public function test_passkey_allowlist_has_swap_templates_only_behind_the_flag_on_testnet(): void
    {
        $all = array_merge(DustSwap::PASSKEY_TEMPLATES, DustSwap::WALLET_ONLY_TEMPLATES);

        // flag off: no swap template at all
        $r = new TemplateRegistry();
        foreach ($all as $id) {
            $this->assertArrayNotHasKey($id, $r->allowed(), $id.' allowlisted with the flag off');
        }

        // flag on, testnet: FLOW → ĐUST only
        config(['flovatar.dustSwap.enabled' => true]);
        $r = new TemplateRegistry(null, null, self::ALIASES);
        foreach (DustSwap::PASSKEY_TEMPLATES as $id) {
            $t = $r->find($id);
            $this->assertSame($id, $t['id']);
            $this->assertStringContainsString('SwapRouter', $t['cadence']);
            $this->assertStringContainsString('availableBalance', $t['cadence'], 'spend only availableBalance FLOW');
            $this->assertStringContainsString('deadline: deadline', $t['cadence']);
            $this->assertStringContainsString('0x84e6590ef1493ae7', $r->resolve($t['cadence']), 'testnet Increment aliases resolve');
        }
        foreach (DustSwap::WALLET_ONLY_TEMPLATES as $id) {
            $this->assertArrayNotHasKey($id, $r->allowed(), $id.' (ĐUST → FLOW) must never be allowlisted');
            try {
                $r->find($id);
                $this->fail($id.' resolved for a passkey account');
            } catch (RuntimeException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }

        // flag on but mainnet: still not allowlisted
        config(['services.flow.network' => 'mainnet']);
        $r = new TemplateRegistry();
        foreach ($all as $id) {
            $this->assertArrayNotHasKey($id, $r->allowed(), $id.' allowlisted on mainnet');
        }
    }

    public function test_swap_pins_match_the_manifest(): void
    {
        $manifest = collect(TemplateRegistry::loadManifest())->keyBy('id');
        foreach (config('passkey-templates-dust-swap') as $id => $pin) {
            $this->assertContains($id, DustSwap::PASSKEY_TEMPLATES);
            $this->assertSame($manifest[$id]['sha256'], $pin['sha256'], $id.' pin is stale');
        }
        $this->assertEqualsCanonicalizing(DustSwap::PASSKEY_TEMPLATES, array_keys(config('passkey-templates-dust-swap')));
    }
}
