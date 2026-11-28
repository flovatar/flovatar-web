<?php

namespace Tests\Feature;

use App\Http\Middleware\ThrottleRequestsPerRoute;
use App\Support\DustEvm;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "Buy ĐUST from any chain" (KB notes/flow-evm-program.md EVM-B): rides on the ĐUST swap feature + DUST_EVM_ENABLED.
 * Off = no window.DUST_EVM, /buy-dust 404, no modal; on (testnet) = page, modal hook, static-safe config with the
 * testnet PunchSwap V2 + test USDC addresses and the simulated cross-chain step.
 */
class DustEvmTest extends TestCase
{
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
            'flovatar.dustEvm.enabled' => false,
        ]);
        DB::purge();
        $this->withoutMiddleware(ThrottleRequestsPerRoute::class);
    }

    public function test_everything_off(): void
    {
        $this->assertFalse(DustEvm::enabled());
        $this->get('/buy-dust')->assertNotFound();
        $html = $this->get('/dust')->assertOk()->getContent();
        $this->assertStringNotContainsString('window.DUST_EVM', $html);
        $this->assertStringNotContainsString('buyDust(', $html);
    }

    public function test_evm_flag_alone_does_nothing_without_the_swap_feature(): void
    {
        config(['flovatar.dustEvm.enabled' => true]);
        $this->assertFalse(DustEvm::enabled());
        $this->get('/buy-dust')->assertNotFound();
    }

    public function test_swap_on_evm_off_page_has_flow_only(): void
    {
        config(['flovatar.dustSwap.enabled' => true]);
        $html = $this->get('/buy-dust')->assertOk()->getContent();
        $this->assertStringContainsString('x-data="buyDust({})"', $html);
        $this->assertStringContainsString('x-data="buyDust({modal: true})"', $html, 'the FlovatarBuyDust modal is in the layout');
        $this->assertStringNotContainsString('window.DUST_EVM', $html);
    }

    public function test_both_on_testnet_config(): void
    {
        config(['flovatar.dustSwap.enabled' => true, 'flovatar.dustEvm.enabled' => true]);
        $this->assertTrue(DustEvm::enabled());
        $html = $this->get('/buy-dust')->assertOk()->getContent();
        $this->assertStringContainsString('window.DUST_EVM', $html);
        $this->assertStringContainsString('data-bd="opt-usdc"', $html);
        $this->assertStringContainsString('data-bd="opt-xchain"', $html);

        $b = DustEvm::bootstrap();
        $this->assertSame('testnet', $b['network']);
        $this->assertSame('0x8c5303eaa26202d6', $b['evm']);
        $this->assertSame('0xdfc20aee650fcbdf', $b['bridge']);
        $this->assertSame('0xeD53235cC3E9d2d464E9c408B95948836648870B', $b['router']);
        $this->assertSame('0x783ee59f55cae490ba81335b46cc06c391ac55bd', $b['dust']);
        $this->assertSame('0x12baeb407594ded4e5463578a6ce18c9bfdbf543', $b['pay']['address']);
        $this->assertSame(['direct', 'hop', 'via'], $b['routes']);
        $this->assertSame('simulate', $b['xchain']);
        $this->assertTrue($b['testFaucet']);
        $this->assertSame(10.0, $b['impactBlock']);
        // static-safe: nothing user specific
        $this->assertStringNotContainsString('0x27407eda8e2bb47c', json_encode($b));
    }

    public function test_bad_addresses_are_dropped(): void
    {
        config(['flovatar.dustEvm.router' => 'not-an-address', 'flovatar.dustEvm.routes' => ['via', 'bogus'], 'flovatar.dustEvm.xchain' => 'hack']);
        $b = DustEvm::bootstrap();
        $this->assertSame('', $b['router']);
        $this->assertSame(['via'], $b['routes']);
        $this->assertSame('off', $b['xchain']);
    }
}
