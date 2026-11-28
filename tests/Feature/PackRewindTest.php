<?php

namespace Tests\Feature;

use App\Http\Middleware\ThrottleRequestsPerRoute;
use App\Models\PackRewindPack;
use App\Models\PasskeyAccount;
use App\Models\PasskeyFlowGrant;
use App\Services\Flow\SportvatarRewindAuthorizer;
use App\Services\Passkey\FlowGrant;
use App\Services\Passkey\TemplateRegistry;
use App\Support\PackRewind;
use App\Support\Stealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Pack Rewind (KB notes/pack-rewind.md): testnet-only switch, routes/markup, passkey allowlist, stock import, Sportvatar
 * authorisation message, and the passkey starting-FLOW guards (App\Services\Passkey\FlowGrant).
 */
class PackRewindTest extends TestCase
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
            'stealth.features.rewind' => 'on',
            'flovatar.contractAddress' => '0x2c6a4c4342aeca55',
            'passkey.flow_grant' => ['enabled' => true, 'amount' => 150, 'since' => 0, 'per_ip_per_day' => 2,
                'per_device_per_day' => 2, 'daily_cap' => 400, 'sponsor_reserve' => 200],
        ]);
        DB::purge();
        $this->withoutMiddleware(ThrottleRequestsPerRoute::class);
        (require base_path('database/migrations/2026_11_28_100001_create_pack_rewind_tables.php'))->up();
        \Illuminate\Support\Facades\Schema::create('passkey_accounts', function ($t) {
            $t->id(); $t->string('email')->nullable(); $t->string('address')->nullable(); $t->string('status')->default('active');
            $t->string('created_ip')->nullable(); $t->string('creation_tx')->nullable(); $t->string('gift_tx')->nullable();
            $t->timestamp('email_verified_at')->nullable(); $t->timestamp('recovered_at')->nullable(); $t->timestamps();
        });
    }

    private function routes(): void
    {
        Route::middleware(['web', 'stealth:rewind'])->group(base_path('routes/pack-rewind.php'));
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_feature_is_testnet_only(): void
    {
        $this->assertTrue(PackRewind::active());
        config(['services.flow.network' => 'mainnet']);
        $this->assertSame('off', Stealth::mode('rewind'), 'mainnet: forced off whatever PACK_REWIND_MODE says');
        $this->assertFalse(PackRewind::active());
        $this->assertFalse(PackRewind::enabled());
        config(['services.flow.network' => 'testnet', 'stealth.features.rewind' => null]);
        $this->assertFalse(PackRewind::active(), 'unset mode = off');
    }

    public function test_page_renders_timeline_in_chronological_order(): void
    {
        $this->routes();
        PackRewindPack::create(['collection' => 'flovatar', 'series' => 'genesis-standard', 'mainnet_id' => 1, 'testnet_id' => 23,
            'name' => 'Genesis Standard Pack', 'price' => '9', 'currency' => 'FLOW', 'contents' => []]);
        $html = $this->get('/pack-rewind')->assertOk()->getContent();
        $this->assertStringContainsString('Pack Rewind', $html);
        $this->assertStringContainsString('window.PACK_REWIND', $html);
        $keys = array_keys(config('pack_rewind.series'));
        $pos = array_map(fn ($k) => strpos($html, 'data-series="'.$k.'"'), $keys);
        $this->assertNotContains(false, $pos);
        $sorted = $pos; sort($sorted);
        $this->assertSame($sorted, $pos, 'series appear in config (chronological) order');
        $dates = array_column(config('pack_rewind.series'), 'date');
        $sd = $dates; sort($sd);
        $this->assertSame($sd, $dates, 'config series are chronological');
        $j = $this->getJson('/api/pack-rewind/series')->assertOk()->json('series');
        $this->assertSame(1, $j[0]['supply']);
        $this->assertSame('9.0', $j[0]['price']);
    }

    public function test_original_prices(): void
    {
        $s = config('pack_rewind.series');
        $this->assertSame(['9.0', '35.0', '39.0', '49.0', '3000.0'], [$s['genesis-standard']['price'], $s['genesis-deluxe']['price'],
            $s['flobit-s2']['price'], $s['terrakit']['price'], $s['dust-flobit']['price']]);
        $this->assertSame('DUST', $s['dust-flobit']['currency']);
        foreach ($s as $k => $v) {
            if ($v['collection'] === 'sportvatar') {
                $this->assertSame('9.0', $v['price'], $k.': Sportvatar packs are 9 FLOW');
            }
        }
    }

    public function test_reserve_requires_a_verified_login(): void
    {
        $this->routes();
        $this->postJson('/pack-rewind/reserve', ['series' => 'genesis-standard'])->assertStatus(401);
    }

    public function test_page_absent_when_off(): void
    {
        config(['stealth.features.rewind' => 'off']);
        $this->routes();
        $this->get('/pack-rewind')->assertNotFound();
    }

    public function test_passkey_allowlist_has_rewind_templates_only_when_active(): void
    {
        $allow = TemplateRegistry::defaultAllow();
        foreach (['rewind-buy-flow', 'rewind-buy-dust-with-flow', 'rewind-sv-buy-flow', 'rewind-sv-open'] as $id) {
            $this->assertArrayHasKey($id, $allow);
        }
        $manifest = TemplateRegistry::loadManifest();
        foreach (config('passkey-templates-rewind') as $id => $pin) {
            $this->assertSame($manifest[$id]['sha256'], $pin['sha256'], "$id pin == manifest");
        }
        config(['stealth.features.rewind' => 'off']);
        $this->assertArrayNotHasKey('rewind-buy-flow', TemplateRegistry::defaultAllow());
    }

    public function test_import_command_is_idempotent_and_keeps_sale_state(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'rw');
        file_put_contents($file, json_encode([
            ['collection' => 'flovatar', 'series' => 'genesis-standard', 'mainnetId' => 1, 'testnetId' => 23, 'name' => 'Genesis Standard Pack',
                'price' => '9.0', 'currency' => 'FLOW', 'contents' => [['m' => 458, 't' => 100, 'n' => 'Spark', 'r' => 'common', 'c' => 'spark']], 'source' => 'open-tx'],
            ['collection' => 'sportvatar', 'series' => 'sv-s1-rare', 'mainnetId' => 13621, 'testnetId' => 9001, 'name' => 'S1 Rare Pack',
                'price' => '9.0', 'currency' => 'FLOW', 'contents' => [], 'source' => 'chain'],
        ]));
        $this->artisan('pack-rewind:import', ['file' => $file])->assertExitCode(0);
        PackRewindPack::where('testnet_id', 23)->update(['status' => 'sold', 'sold_to' => '0x0000000000000001']);
        $this->artisan('pack-rewind:import', ['file' => $file])->assertExitCode(0);
        $this->assertSame(2, PackRewindPack::count());
        $this->assertSame('sold', PackRewindPack::where('testnet_id', 23)->value('status'));
        unlink($file);
    }

    public function test_sportvatar_authorisation_message_format(): void
    {
        $this->assertSame('SPORTVATAR-PACK-V2|purchase-flow|42|0x27407eda8e2bb47c|1700000000|2',
            SportvatarRewindAuthorizer::message(42, '0x27407EDA8E2BB47C', 1700000000, 2));
    }

    // ------------------------------------------------------------------ starting FLOW (passkey)

    private function account(string $addr = '0x00000000000000a1'): PasskeyAccount
    {
        return PasskeyAccount::create(['email' => $addr.'@t.test', 'address' => $addr, 'status' => 'active']);
    }

    private function req(string $ip = '1.2.3.4', string $dev = 'dev1'): Request
    {
        $r = Request::create('/passkey/flow-grant', 'POST', [], [FlowGrant::DEVICE_COOKIE => $dev], [], ['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => 'UA']);

        return $r;
    }

    private function svc(array &$sent, float $balance = 10000): FlowGrant
    {
        return new FlowGrant(function ($a, $amount) use (&$sent) { $sent[] = [$a->address, $amount]; return [str_repeat('a', 64), ['error' => '']]; },
            fn () => $balance);
    }

    public function test_grant_sends_150_once_per_account(): void
    {
        $sent = [];
        $a = $this->account();
        $g = $this->svc($sent)->grant($a, $this->req());
        $this->assertSame('sent', $g->status);
        $this->assertSame([['0x00000000000000a1', '150.00000000']], $sent);
        $this->svc($sent)->grant($a, $this->req());
        $this->assertCount(1, $sent, 'second call does not send again');
        $this->assertSame(1, PasskeyFlowGrant::count());
    }

    public function test_grant_kill_switch_and_mainnet(): void
    {
        $sent = [];
        config(['passkey.flow_grant.enabled' => false]);
        try { $this->svc($sent)->grant($this->account(), $this->req()); $this->fail('kill switch'); } catch (RuntimeException $e) { $this->assertStringContainsString('switched off', $e->getMessage()); }
        config(['passkey.flow_grant.enabled' => true, 'services.flow.network' => 'mainnet']);
        try { $this->svc($sent)->grant($this->account('0x00000000000000a2'), $this->req()); $this->fail('mainnet'); } catch (RuntimeException $e) { $this->assertStringContainsString('switched off', $e->getMessage()); }
        $this->assertSame([], $sent);
    }

    public function test_grant_ip_device_daily_cap_and_reserve_limits(): void
    {
        $sent = [];
        $this->svc($sent)->grant($this->account('0x00000000000000b1'), $this->req('5.5.5.5', 'd1'));
        $this->svc($sent)->grant($this->account('0x00000000000000b2'), $this->req('5.5.5.5', 'd2'));
        try { $this->svc($sent)->grant($this->account('0x00000000000000b3'), $this->req('5.5.5.5', 'd3')); $this->fail('ip'); } catch (RuntimeException $e) { $this->assertStringContainsString('network', $e->getMessage()); }
        // daily cap 400: two grants (300) done, a third (450) is refused even from another network/device
        try { $this->svc($sent)->grant($this->account('0x00000000000000b4'), $this->req('6.6.6.6', 'd4')); $this->fail('cap'); } catch (RuntimeException $e) { $this->assertStringContainsString('budget', $e->getMessage()); }
        config(['passkey.flow_grant.daily_cap' => 100000, 'passkey.flow_grant.per_device_per_day' => 1]);
        try { $this->svc($sent)->grant($this->account('0x00000000000000b5'), $this->req('7.7.7.7', 'd1')); $this->fail('device'); } catch (RuntimeException $e) { $this->assertStringContainsString('device', $e->getMessage()); }
        try { $this->svc($sent, 300)->grant($this->account('0x00000000000000b6'), $this->req('8.8.8.8', 'd8')); $this->fail('reserve'); } catch (RuntimeException $e) { $this->assertStringContainsString('empty', $e->getMessage()); }
        $this->assertCount(2, $sent);
    }

    public function test_grant_only_for_new_accounts(): void
    {
        $sent = [];
        $old = $this->account('0x00000000000000c1');
        $old->created_at = now()->subDays(3); $old->save();
        config(['passkey.flow_grant.since' => now()->subDay()->getTimestamp()]);
        $this->assertSame('ineligible', FlowGrant::status($old)['state']);
        try { $this->svc($sent)->grant($old, $this->req()); $this->fail('old'); } catch (RuntimeException $e) { $this->assertStringContainsString('new accounts', $e->getMessage()); }
        $this->assertSame('none', FlowGrant::status($this->account('0x00000000000000c2'))['state']);
    }

    public function test_failed_send_can_be_retried(): void
    {
        $a = $this->account('0x00000000000000d1');
        $fail = new FlowGrant(fn () => throw new RuntimeException('node down'), fn () => 10000);
        try { $fail->grant($a, $this->req()); $this->fail('should throw'); } catch (RuntimeException $e) { $this->assertStringContainsString('retry', $e->getMessage()); }
        $this->assertSame('failed', PasskeyFlowGrant::first()->status);
        $sent = [];
        $this->assertSame('sent', $this->svc($sent)->grant($a, $this->req())->status);
        $this->assertSame(1, PasskeyFlowGrant::count());
    }
}
