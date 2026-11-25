<?php

namespace Tests\Feature;

use App\Http\Middleware\ThrottleRequestsPerRoute;
use App\Models\PasskeyAccount;
use App\Models\PasskeyCredential;
use App\Models\PasskeyTransaction;
use App\Models\PasskeyWalletLink;
use App\Models\QuestChallenge;
use App\Models\QuestClaim;
use App\Models\QuestDiscordLink;
use App\Models\QuestPayoutBatch;
use App\Models\User;
use App\Services\Quests\PayoutLedger;
use App\Services\Quests\QuestEngine;
use App\Services\Quests\ShareCheck;
use App\Support\DustQuests;
use App\Support\PasskeyLogin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * ĐUST Quests (KB notes/dust-quests.md): flag-off safety, eligibility, once-per-challenge, caps, budget, honor timing,
 * quiz, share oEmbed, Discord OAuth (mocked provider), chain evidence (mocked REST), HMAC payout API + on-chain report
 * verification. In-memory SQLite; every HTTP call to Flow / Discord / oEmbed is faked.
 */
class DustQuestsTest extends TestCase
{
    const FL = '0x2c6a4c4342aeca55';

    const REWARDS = '0x8cd1024bebb10e25';

    const SECRET = 'test-secret-0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array',
            'passkey.enabled' => true, 'passkey.login_enabled' => true,
            'services.flow.network' => 'testnet', 'services.flow.rest' => 'https://rest-testnet.onflow.org',
            'services.flow.address' => self::FL, 'services.flow.storefrontaddress' => '0x2d55b98eb200daef',
            'quests.enabled' => true,
            'quests.payout.secret' => self::SECRET, 'quests.payout.rewards_address' => self::REWARDS,
            'quests.discord.client_id' => 'cid', 'quests.discord.client_secret' => 'csecret', 'quests.discord.guild_id' => '895444910075961394',
            'quests.discord.api' => 'https://discord.test/api', 'app.url' => 'https://testnet.flovatar.com',
            'quests.admin_addresses' => ['0xc327c7079fd1b4a3'],
        ]);
        DB::purge('sqlite');
        foreach (['2026_11_25_100001_create_passkey_poc_tables.php', '2026_11_25_300001_passkey_hardening.php', '2026_11_25_500001_create_dust_quests_tables.php'] as $m) {
            (require database_path('migrations/'.$m))->up();
        }
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->string('auth_type', 16)->nullable();
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('sales', function (Blueprint $t) {
            $t->id();
            $t->string('currency')->nullable();
            $t->string('transaction_id')->nullable();
            $t->string('flow_buyer_address')->nullable();
            $t->string('flow_seller_address')->nullable();
            $t->timestamps();
        });
        Schema::create('sale_offers', function (Blueprint $t) {
            $t->id();
            $t->string('flow_address')->nullable();
            $t->string('currency')->nullable();
            $t->timestamps();
        });
        Route::middleware('web')->group(base_path('routes/quests.php'));
        app('router')->getRoutes()->refreshNameLookups();
        $this->withoutMiddleware(ThrottleRequestsPerRoute::class);
        Cache::flush();
    }

    private int $n = 0;

    private function account(bool $verified = true, ?string $address = null): PasskeyAccount
    {
        $this->n++;
        $address ??= '0x'.str_pad(dechex(0xabc000 + $this->n), 16, '0', STR_PAD_LEFT);
        $a = PasskeyAccount::create(['address' => $address, 'email' => "u{$this->n}@example.com", 'status' => 'active', 'email_verified_at' => $verified ? now() : null]);
        PasskeyCredential::create(['passkey_account_id' => $a->id, 'credential_id' => 'cred'.$this->n, 'public_key' => str_repeat('a', 128), 'key_index' => 0, 'user_handle' => 'h', 'status' => 'active']);

        return $a;
    }

    /** Signed-in passkey session for $a (Laravel login + passkey session keys, like PasskeyLogin::loginSite). */
    private function as(PasskeyAccount $a): self
    {
        $user = User::firstOrCreate(['email' => $a->address], ['name' => $a->address, 'password' => 'x', 'auth_type' => 'passkey']);
        $cred = PasskeyCredential::where('passkey_account_id', $a->id)->first();

        return $this->actingAs($user)->withSession([PasskeyLogin::SESSION_ACCOUNT => $a->id, PasskeyLogin::SESSION_CREDENTIAL => $cred->id]);
    }

    private function engine(): QuestEngine
    {
        return app(QuestEngine::class);
    }

    private function challenge(string $key): QuestChallenge
    {
        $this->engine()->challenges(false);

        return QuestChallenge::where('key', $key)->firstOrFail();
    }

    // ------------------------------------------------------------------ flag / catalogue / eligibility

    public function test_off_by_default_and_needs_passkey_and_testnet(): void
    {
        $this->assertTrue(DustQuests::enabled());
        config(['quests.enabled' => false]);
        $this->assertFalse(DustQuests::enabled());
        config(['quests.enabled' => true, 'passkey.login_enabled' => false]);
        $this->assertFalse(DustQuests::enabled(), 'needs the passkey sign-in');
        config(['passkey.login_enabled' => true, 'services.flow.network' => 'mainnet']);
        $this->assertFalse(DustQuests::enabled(), 'never on mainnet');
        // the default config (no env) is off
        $this->assertFalse((bool) (require base_path('config/quests.php'))['enabled']);
    }

    public function test_catalogue_seed_is_insert_only_and_fits_the_cap(): void
    {
        $e = $this->engine();
        $this->assertGreaterThan(10, $e->syncCatalogue());
        $active = QuestChallenge::where('active', true)->sum('amount');
        $this->assertLessThanOrEqual(1000, $active);
        QuestChallenge::where('key', 'geo')->update(['amount' => 33]);
        $this->assertSame(0, $e->syncCatalogue());
        $this->assertEquals(33, QuestChallenge::where('key', 'geo')->value('amount'), 'admin edits survive');
        $this->assertFalse((bool) QuestChallenge::where('key', 'follow_tiktok')->value('active'), 'TikTok off until Luca confirms');
    }

    public function test_wallet_users_and_unverified_accounts_are_not_eligible(): void
    {
        $wallet = User::create(['email' => '0x27407eda8e2bb47c', 'name' => 'w', 'password' => 'x']);
        $this->actingAs($wallet)->getJson('/quests/state')->assertOk()->assertJson(['eligible' => false, 'reason' => 'not_passkey']);
        $this->getJson('/quests/state')->assertJson(['eligible' => false]);
        $a = $this->account(false);
        $this->as($a)->getJson('/quests/state')->assertOk()->assertJson(['eligible' => false, 'reason' => 'email']);
        $this->as($a)->postJson('/quests/account_email/claim')->assertStatus(403);
    }

    // ------------------------------------------------------------------ claims, caps, budget

    public function test_claim_once_per_challenge_and_unique_email(): void
    {
        $a = $this->account();
        $r = $this->as($a)->postJson('/quests/account_email/claim')->assertOk()->json();
        $this->assertSame('verified', $r['status']);
        $this->assertEquals(50, $r['amount']);
        $this->as($a)->postJson('/quests/account_email/claim')->assertStatus(409);
        $this->assertSame(1, QuestClaim::count());
        // a second account re-using the same (verified) email cannot earn it again
        $b = $this->account();
        $b->update(['email' => $a->email]);
        $this->as($b)->postJson('/quests/account_email/claim')->assertStatus(409);
    }

    public function test_per_user_cap_clips_then_blocks(): void
    {
        $a = $this->account();
        $e = $this->engine();
        $e->setCapOverride($a, 60);
        $this->as($a)->postJson('/quests/account_email/claim')->assertOk();                  // 50
        $c = $this->challenge('follow_x');
        $e->startHonor($a, $c);
        $this->travel(11)->seconds();
        $r = $this->as($a)->postJson('/quests/follow_x/claim')->assertOk()->json();         // 25 -> clipped to 10
        $this->assertEquals(10, $r['amount']);
        $e->startHonor($a, $this->challenge('follow_instagram'));
        $this->travel(11)->seconds();
        $this->as($a)->postJson('/quests/follow_instagram/claim')->assertStatus(409)->assertJsonFragment(['error' => 'You reached the season cap of Đ 60. Thank you for playing!']);
        $this->assertEquals(60, $e->totals($a)['committed']);
    }

    public function test_season_budget_and_frozen_accounts(): void
    {
        QuestEngine::setSetting('season_budget', 70);
        $a = $this->account();
        $b = $this->account();
        $this->as($a)->postJson('/quests/account_email/claim')->assertOk();
        $this->as($b)->postJson('/quests/account_email/claim')->assertStatus(409)->assertJsonFragment(['error' => 'The ĐUST Quests budget for this season is used up. Thank you for playing!']);
        $c = $this->account();
        $this->engine()->freeze($c, true, 'test');
        $this->as($c)->postJson('/quests/account_email/claim')->assertStatus(403);
        $this->as($c)->getJson('/quests/state')->assertJson(['eligible' => false, 'reason' => 'frozen']);
    }

    public function test_honor_click_needs_open_and_wait(): void
    {
        $a = $this->account();
        $this->as($a)->postJson('/quests/follow_youtube/claim')->assertStatus(422)->assertJsonFragment(['error' => 'Open the link first.']);
        $this->as($a)->postJson('/quests/follow_youtube/start')->assertOk()->assertJson(['wait' => 10]);
        $this->as($a)->postJson('/quests/follow_youtube/claim')->assertStatus(425);
        $this->travel(11)->seconds();
        $this->as($a)->postJson('/quests/follow_youtube/claim')->assertOk()->assertJson(['status' => 'verified']);
    }

    public function test_quiz_requires_all_right_and_limits_attempts(): void
    {
        $a = $this->account();
        $right = array_map(fn ($q) => $q['answer'], config('quests.quiz'));
        $wrong = $right;
        $wrong[0] = ($wrong[0] + 1) % 4;
        $state = $this->as($a)->getJson('/quests/state')->json();
        $this->assertArrayNotHasKey('answer', $state['quiz'][0], 'answers never leave the server');
        $this->as($a)->postJson('/quests/lore_quiz/claim', ['answers' => $wrong])->assertStatus(422)->assertJsonFragment(['error' => '4 of 5 right. Try again (4 tries left today).']);
        $this->as($a)->postJson('/quests/lore_quiz/claim', ['answers' => $right])->assertOk()->assertJson(['status' => 'verified', 'amount' => 75]);
        $b = $this->account();
        for ($i = 0; $i < 5; $i++) {
            $this->as($b)->postJson('/quests/lore_quiz/claim', ['answers' => $wrong])->assertStatus(422);
        }
        $this->as($b)->postJson('/quests/lore_quiz/claim', ['answers' => $right])->assertStatus(429);
    }

    public function test_share_post_oembed_verify_review_and_reuse(): void
    {
        $this->assertNull(ShareCheck::parse('https://evil.example/x/status/1'));
        $this->assertSame('x:1234567890', ShareCheck::parse('https://twitter.com/Fan/status/1234567890?s=20')['key']);
        $this->assertSame('tiktok', ShareCheck::parse('https://www.tiktok.com/@fan/video/7311111111111111111')['platform']);
        Http::fake([
            'publish.twitter.com/*' => Http::sequence()
                ->push(['html' => '<blockquote><p>My Flovatar! <a href="https://twitter.com/hashtag/Flovatar">#Flovatar</a> by <a>@flovatar</a></p></blockquote>', 'author_name' => 'Fan'])
                ->push(['html' => '<blockquote><p>gm everyone</p></blockquote>', 'author_name' => 'Other']),
        ]);
        $a = $this->account();
        $this->as($a)->postJson('/quests/share_card/claim', ['url' => 'https://x.com/fan/status/1234567890'])->assertOk()->assertJson(['status' => 'verified']);
        $b = $this->account();
        $this->as($b)->postJson('/quests/share_card/claim', ['url' => 'https://twitter.com/fan/status/1234567890'])->assertStatus(409)->assertJsonFragment(['error' => 'This post was already used.']);
        $c = $this->account();
        $this->as($c)->postJson('/quests/share_card/claim', ['url' => 'https://x.com/other/status/999999999'])->assertOk()->assertJson(['status' => 'pending']);
        // admin approves the review
        $claim = QuestClaim::where('passkey_account_id', $c->id)->first();
        $this->assertTrue($this->engine()->approve($claim)['ok']);
        $this->assertSame('verified', $claim->fresh()->status);
    }

    public function test_many_accounts_from_one_network_go_to_review(): void
    {
        config(['quests.limits.accounts_per_ip_per_day' => 2]);
        $statuses = [];
        for ($i = 0; $i < 4; $i++) {
            $statuses[] = $this->as($this->account())->withServerVariables(['REMOTE_ADDR' => '2001:db8:1:2::'.($i + 1)])->postJson('/quests/account_email/claim')->json('status');
        }
        $this->assertSame(['verified', 'verified', 'pending', 'pending'], $statuses, 'IPv6 /64 bucket');
    }

    // ------------------------------------------------------------------ Discord OAuth (mocked provider)

    public function test_discord_oauth_flow_with_mocked_provider(): void
    {
        Http::fake([
            'discord.test/api/oauth2/token' => Http::response(['access_token' => 'tok', 'token_type' => 'Bearer']),
            'discord.test/api/oauth2/token/revoke' => Http::response([]),
            'discord.test/api/users/@me' => Http::response(['id' => '111222333444555666', 'username' => 'fan', 'global_name' => 'Fan']),
            'discord.test/api/users/@me/guilds*' => Http::response([['id' => '1'], ['id' => '895444910075961394']]),
        ]);
        $a = $this->account();
        $res = $this->as($a)->get('/quests/discord/connect');
        $res->assertRedirect();
        $loc = $res->headers->get('Location');
        $this->assertStringStartsWith('https://discord.com/oauth2/authorize?', $loc);
        parse_str(parse_url($loc, PHP_URL_QUERY), $q);
        $this->assertSame('identify guilds', $q['scope']);
        $this->assertSame('https://testnet.flovatar.com/quests/discord/callback', $q['redirect_uri']);
        // wrong state is refused
        $this->as($a)->withSession(['quests.discord_state' => ['s' => $q['state'], 'a' => $a->id, 't' => time()]])
            ->get('/quests/discord/callback?code=abc&state=nope')->assertRedirect('/quests#discord=expired');
        $this->as($a)->withSession(['quests.discord_state' => ['s' => $q['state'], 'a' => $a->id, 't' => time()]])
            ->get('/quests/discord/callback?code=abc&state='.$q['state'])->assertRedirect('/quests#discord=ok');
        $this->assertSame('verified', QuestClaim::where('passkey_account_id', $a->id)->where('challenge_key', 'discord')->value('status'));
        $this->assertSame('111222333444555666', QuestDiscordLink::where('passkey_account_id', $a->id)->value('discord_user_id'));
        // the same Discord account on another Flovatar account
        $b = $this->account();
        $this->as($b)->withSession(['quests.discord_state' => ['s' => 'st2', 'a' => $b->id, 't' => time()]])
            ->get('/quests/discord/callback?code=abc&state=st2')->assertRedirect('/quests#discord=taken');
        $this->assertSame(0, QuestClaim::where('passkey_account_id', $b->id)->count());
    }

    public function test_discord_member_check(): void
    {
        Http::fake([
            'discord.test/api/oauth2/token' => Http::response(['access_token' => 'tok']),
            'discord.test/api/oauth2/token/revoke' => Http::response([]),
            'discord.test/api/users/@me' => Http::response(['id' => '999888777666555444', 'username' => 'lurker']),
            'discord.test/api/users/@me/guilds*' => Http::response([['id' => '42']]),
        ]);
        $a = $this->account();
        $this->as($a)->withSession(['quests.discord_state' => ['s' => 'st', 'a' => $a->id, 't' => time()]])
            ->get('/quests/discord/callback?code=abc&state=st')->assertRedirect('/quests#discord=join');
        $this->as($a)->postJson('/quests/discord/claim')->assertStatus(422);
    }

    // ------------------------------------------------------------------ on-chain evidence (mocked REST)

    private function fakeTx(string $txId, string $authorizer, array $events, string $status = 'Sealed', string $error = ''): array
    {
        $ev = array_map(fn ($t) => ['type' => $t, 'payload' => base64_encode(json_encode(['type' => 'Event', 'value' => ['id' => $t, 'fields' => [['name' => 'id', 'value' => ['type' => 'UInt64', 'value' => '7']]]]]))], $events);

        return [
            'rest-testnet.onflow.org/v1/transaction_results/'.$txId => Http::response(['status' => $status, 'error_message' => $error, 'events' => $ev]),
            'rest-testnet.onflow.org/v1/transactions/'.$txId => Http::response(['authorizers' => [substr($authorizer, 2)], 'proposal_key' => ['address' => substr($authorizer, 2)], 'payer' => '32097235c152f4f6', 'arguments' => [], 'script' => '']),
        ];
    }

    public function test_equip_flobit_is_proven_by_a_sealed_sponsored_tx(): void
    {
        $a = $this->account();
        $other = $this->account();
        $t1 = str_repeat('1', 64);
        $t2 = str_repeat('2', 64);
        PasskeyTransaction::create(['passkey_account_id' => $a->id, 'kind' => 'flovatar-add-hat', 'tx_id' => $t1, 'signed_by' => 'passkey#0']);
        Http::fake($this->fakeTx($t1, $other->address, ['A.2c6a4c4342aeca55.Flovatar.Updated']));
        $this->as($a)->postJson('/quests/equip_flobit/claim')->assertStatus(422); // authorizer mismatch
        PasskeyTransaction::create(['passkey_account_id' => $a->id, 'kind' => 'flovatar-add-background', 'tx_id' => $t2, 'signed_by' => 'passkey#0']);
        Http::fake($this->fakeTx($t2, $a->address, ['A.2c6a4c4342aeca55.Flovatar.Updated']));
        $r = $this->as($a)->postJson('/quests/equip_flobit/claim')->assertOk()->json();
        $this->assertSame('verified', $r['status']);
        $this->assertSame($t2, QuestClaim::where('challenge_key', 'equip_flobit')->first()->evidence['tx_id']);
    }

    public function test_failed_tx_or_foreign_event_does_not_count(): void
    {
        $a = $this->account();
        $t = str_repeat('3', 64);
        PasskeyTransaction::create(['passkey_account_id' => $a->id, 'kind' => 'flobot-create', 'tx_id' => $t, 'signed_by' => 'passkey#0']);
        // same event name but from ANOTHER contract address
        Http::fake($this->fakeTx($t, $a->address, ['A.0000000000000001.Flobot.Created']));
        $this->as($a)->postJson('/quests/make_flobot/claim')->assertStatus(422);
    }

    public function test_first_sale_from_the_index_and_graduation_once_per_wallet(): void
    {
        $a = $this->account();
        DB::table('sales')->insert(['flow_seller_address' => $a->address, 'flow_buyer_address' => '0xc327c7079fd1b4a3', 'currency' => 'DUST', 'transaction_id' => 'abc']);
        $this->as($a)->postJson('/quests/first_sale/claim')->assertOk();
        // graduation: link recorded by the passkey code + confirmed on chain (WalletLink status script faked)
        $b = $this->account();
        PasskeyWalletLink::create(['passkey_account_id' => $b->id, 'child_address' => $b->address, 'parent_address' => '0xc327c7079fd1b4a3', 'status' => 'linked', 'linked_at' => now()]);
        Http::fake(['rest-testnet.onflow.org/v1/scripts*' => Http::response('"'.base64_encode(json_encode(['type' => 'Dictionary', 'value' => [
            ['key' => ['type' => 'String', 'value' => 'parents'], 'value' => ['type' => 'Dictionary', 'value' => [['key' => ['type' => 'Address', 'value' => '0xc327c7079fd1b4a3'], 'value' => ['type' => 'Bool', 'value' => true]]]]],
            ['key' => ['type' => 'String', 'value' => 'managerHasChild'], 'value' => ['type' => 'Bool', 'value' => true]],
        ]])).'"')]);
        $this->as($b)->postJson('/quests/graduation/claim')->assertOk()->assertJson(['amount' => 100]);
        $this->assertNotNull(PasskeyWalletLink::where('passkey_account_id', $b->id)->value('rewarded_at'));
        $c = $this->account();
        PasskeyWalletLink::create(['passkey_account_id' => $c->id, 'child_address' => $c->address, 'parent_address' => '0xc327c7079fd1b4a3', 'status' => 'linked', 'linked_at' => now()]);
        $this->as($c)->postJson('/quests/graduation/claim')->assertStatus(422);
    }

    public function test_streak_counts_consecutive_days(): void
    {
        $a = $this->account();
        $e = $this->engine();
        for ($i = 6; $i >= 1; $i--) {
            DB::table('quest_activity_days')->insert(['passkey_account_id' => $a->id, 'day' => now()->utc()->subDays($i)->toDateString()]);
        }
        $this->assertSame(0, $e->streak($a));
        $this->as($a)->postJson('/quests/streak_7/claim')->assertStatus(422);
        $this->as($a)->postJson('/quests/seen')->assertOk();
        $this->assertSame(7, $e->streak($a));
        $this->as($a)->postJson('/quests/streak_7/claim')->assertOk();
    }

    // ------------------------------------------------------------------ payout API

    private function signed(string $path, array $body, ?string $nonce = null, ?int $ts = null, string $secret = self::SECRET)
    {
        $raw = json_encode($body);
        $ts ??= time();
        $nonce ??= bin2hex(random_bytes(12));
        $sig = PayoutLedger::signature($secret, (string) $ts, $nonce, 'POST', $path, $raw);

        return $this->call('POST', $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_QUESTS_TIMESTAMP' => (string) $ts, 'HTTP_X_QUESTS_NONCE' => $nonce, 'HTTP_X_QUESTS_SIGNATURE' => $sig,
        ], $raw);
    }

    private function verifiedClaim(PasskeyAccount $a, string $key, float $amount): QuestClaim
    {
        $c = $this->challenge($key);

        return QuestClaim::create(['passkey_account_id' => $a->id, 'address' => $a->address, 'challenge_id' => $c->id, 'challenge_key' => $key,
            'season' => 'season-1', 'amount' => $amount, 'status' => 'verified', 'verified_at' => now()]);
    }

    public function test_payout_api_rejects_bad_signatures_and_replays(): void
    {
        $this->signed('/quests/payout/status', [], null, null, 'wrong-secret-0123456789abcdef0123456789')->assertStatus(401);
        $this->signed('/quests/payout/status', [], null, time() - 3600)->assertStatus(401);
        $this->signed('/quests/payout/status', [], 'fixednonce-1234567890')->assertOk();
        $this->signed('/quests/payout/status', [], 'fixednonce-1234567890')->assertStatus(401);
        $this->postJson('/quests/payout/reserve', ['max' => 5])->assertStatus(401);
    }

    public function test_reserve_respects_caps_frozen_and_is_idempotent(): void
    {
        QuestEngine::setSetting('daily_cap', 100);
        $a = $this->account();
        $b = $this->account();
        $frozen = $this->account();
        $c1 = $this->verifiedClaim($a, 'account_email', 50);
        $c2 = $this->verifiedClaim($b, 'account_email', 50);
        $c3 = $this->verifiedClaim($b, 'make_flobot', 75);      // over today's cap -> waits
        $this->verifiedClaim($frozen, 'account_email', 50);
        $this->engine()->freeze($frozen, true);
        $r = $this->signed('/quests/payout/reserve', ['max' => 10])->assertOk()->json();
        $this->assertSame([$c1->id, $c2->id], array_column($r['items'], 'claim_id'));
        $this->assertSame('50.00000000', $r['items'][0]['amount']);
        $this->assertSame(self::REWARDS, $r['rewards_address']);
        $again = $this->signed('/quests/payout/reserve', ['max' => 10])->json();
        $this->assertSame($r['batch_id'], $again['batch_id'], 'same open batch until reported');
        $this->assertTrue($again['reused']);
        $this->assertNull($c3->fresh()->batch_id);
    }

    private function payoutTxFake(string $txId, string $authorizer, array $recipients, array $amounts, array $ids, string $error = ''): void
    {
        $arr = fn ($type, $vals) => base64_encode(json_encode(['type' => 'Array', 'value' => array_map(fn ($v) => ['type' => $type, 'value' => (string) $v], $vals)]));
        Http::fake([
            'rest-testnet.onflow.org/v1/transaction_results/'.$txId => Http::response(['status' => 'Sealed', 'error_message' => $error, 'events' => []]),
            'rest-testnet.onflow.org/v1/transactions/'.$txId => Http::response(['authorizers' => [substr($authorizer, 2)], 'proposal_key' => ['address' => substr($authorizer, 2)], 'payer' => substr($authorizer, 2),
                'arguments' => [$arr('Address', $recipients), $arr('UFix64', $amounts), $arr('UInt64', $ids), base64_encode(json_encode(['type' => 'UFix64', 'value' => '100.0']))], 'script' => '']),
        ]);
    }

    public function test_report_verifies_the_transaction_on_chain(): void
    {
        $a = $this->account();
        $b = $this->account();
        $c1 = $this->verifiedClaim($a, 'account_email', 50);
        $c2 = $this->verifiedClaim($b, 'account_email', 25.5);
        $batch = $this->signed('/quests/payout/reserve', ['max' => 10])->json('batch_id');
        // wrong amount -> refused, nothing paid
        $bad = str_repeat('b', 64);
        $this->payoutTxFake($bad, self::REWARDS, [$a->address, $b->address], ['50.00000000', '30.00000000'], [$c1->id, $c2->id]);
        $this->signed('/quests/payout/report', ['batch_id' => $batch, 'tx_id' => $bad])->assertStatus(422);
        // not signed by the rewards wallet -> refused
        $other = str_repeat('c', 64);
        $this->payoutTxFake($other, '0x27407eda8e2bb47c', [$a->address, $b->address], ['50.00000000', '25.50000000'], [$c1->id, $c2->id]);
        $this->signed('/quests/payout/report', ['batch_id' => $batch, 'tx_id' => $other])->assertStatus(422);
        $this->assertSame(0, QuestClaim::where('status', 'paid')->count());
        // the right one
        $good = str_repeat('d', 64);
        $this->payoutTxFake($good, self::REWARDS, [$a->address, $b->address], ['50.00000000', '25.50000000'], [$c1->id, $c2->id]);
        $this->signed('/quests/payout/report', ['batch_id' => $batch, 'tx_id' => $good])->assertOk()->assertJson(['status' => 'paid', 'paid' => 2]);
        $this->assertSame(['paid', 'paid'], [$c1->fresh()->status, $c2->fresh()->status]);
        $this->assertSame($good, $c1->fresh()->tx_id);
        $this->signed('/quests/payout/report', ['batch_id' => $batch, 'tx_id' => $good])->assertOk()->assertJson(['already' => true]);
        $this->assertNull($this->signed('/quests/payout/reserve', ['max' => 10])->json('batch_id'), 'paid claims are never handed out again');
    }

    public function test_failed_tx_and_not_sent_release_claims(): void
    {
        $a = $this->account();
        $b = $this->account();
        $c1 = $this->verifiedClaim($a, 'account_email', 50);
        $c2 = $this->verifiedClaim($b, 'account_email', 50);
        $batch = $this->signed('/quests/payout/reserve', ['max' => 10])->json('batch_id');
        // worker skipped c2 (no ĐUST receiver yet) and sent c1
        $this->signed('/quests/payout/report', ['batch_id' => $batch, 'tx_id' => null, 'not_sent' => [$c2->id], 'reason' => 'no receiver'])->assertOk()->assertJson(['status' => 'reserved']);
        $this->assertNull($c2->fresh()->batch_id);
        $failed = str_repeat('e', 64);
        $this->payoutTxFake($failed, self::REWARDS, [$a->address], ['50.00000000'], [$c1->id], 'panic: recipient has no DUST receiver');
        $this->signed('/quests/payout/report', ['batch_id' => $batch, 'tx_id' => $failed])->assertOk()->assertJson(['status' => 'failed']);
        $this->assertNull($c1->fresh()->batch_id);
        $this->assertSame('verified', $c1->fresh()->status);
        $this->assertSame('failed', QuestPayoutBatch::find($batch)->status);
    }

    // ------------------------------------------------------------------ admin

    public function test_admin_endpoints_need_an_admin_wallet_session(): void
    {
        $a = $this->account();
        $this->as($a)->getJson('/quests/admin/data')->assertStatus(403);
        $wallet = User::create(['email' => '0x27407eda8e2bb47c', 'name' => 'w', 'password' => 'x']);
        $this->actingAs($wallet)->getJson('/quests/admin/data')->assertStatus(403);
        $admin = User::create(['email' => '0xc327c7079fd1b4a3', 'name' => 'a', 'password' => 'x']);
        Http::fake(['rest-testnet.onflow.org/v1/scripts*' => Http::response('"'.base64_encode(json_encode(['type' => 'Optional', 'value' => ['type' => 'UFix64', 'value' => '20000.00000000']])).'"')]);
        $d = $this->actingAs($admin)->getJson('/quests/admin/data?status=all')->assertOk()->json();
        $this->assertEquals(20000, $d['rewards']['balance']);
        $this->assertEquals(1000000, $d['settings']['season_budget']);
        $geo = collect($d['challenges'])->firstWhere('key', 'geo');
        $this->actingAs($admin)->postJson('/quests/admin/challenges/'.$geo['id'], ['amount' => 15])->assertOk();
        $this->assertEquals(15, QuestChallenge::find($geo['id'])->amount);
        $this->actingAs($admin)->postJson('/quests/admin/accounts/'.$a->address, ['frozen' => true, 'reason' => 'bot'])->assertOk()->assertJson(['frozen' => true]);
        $this->assertTrue($this->engine()->isFrozen($a));
    }
}
