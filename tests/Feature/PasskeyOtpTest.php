<?php

namespace Tests\Feature;

use App\Http\Controllers\PasskeyPocController;
use App\Mail\PasskeyOtpMail;
use App\Models\PasskeyAccount;
use App\Models\PasskeyCredential;
use App\Models\PasskeyEmailCode;
use App\Services\Passkey\AccountRecovery;
use App\Services\Passkey\EmailVerification;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Email one-time codes (hashing, TTL, attempts, cooldown, rate limits, generic answers), the sign-up gate in the
 * controller, the testnet-only code peek, and re-creating rows after a DB-loss recovery. In-memory SQLite with only the
 * passkey tables.
 */
class PasskeyOtpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'passkey.enabled' => true, 'services.flow.network' => 'testnet', 'services.flow.rest' => 'https://rest-testnet.onflow.org',
            'passkey.email.test_read' => false, 'cache.default' => 'array',
        ]);
        DB::purge('sqlite');
        foreach (['2026_11_25_100001_create_passkey_poc_tables.php', '2026_11_25_300001_passkey_hardening.php'] as $m) {
            (require database_path('migrations/'.$m))->up();
        }
        $this->assertTrue(Schema::hasColumn('passkey_accounts', 'email_verified_at'));
        Mail::fake();
    }

    private function sentCode(string $email): string
    {
        $code = null;
        Mail::assertSent(PasskeyOtpMail::class, function (PasskeyOtpMail $m) use ($email, &$code) {
            if ($m->hasTo($email)) {
                $code = $m->code;
            }

            return true;
        });
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $code);

        return $code;
    }

    public function test_code_is_sent_hashed_and_single_use(): void
    {
        $otp = new EmailVerification;
        $out = $otp->start('a@example.com', 'signup', '1.2.3.4');
        $this->assertTrue($out['ok']);
        $this->assertSame(60, $out['cooldown']);
        $code = $this->sentCode('a@example.com');
        $row = PasskeyEmailCode::first();
        $this->assertNotSame($code, $row->code_hash);
        $this->assertStringNotContainsString($code, json_encode($row->getAttributes()));
        $this->assertTrue($otp->verify('a@example.com', 'signup', $code, '1.2.3.4')['ok']);
        $this->assertFalse($otp->verify('a@example.com', 'signup', $code, '1.2.3.4')['ok'], 'single use');
        // a code for another purpose never matches
        $otp2 = new EmailVerification;
        $this->travel(61)->seconds();
        $otp2->start('a@example.com', 'verify', '1.2.3.4');
        $this->assertFalse($otp2->verify('a@example.com', 'signup', $this->sentCode('a@example.com'), '1.2.3.4')['ok']);
    }

    public function test_wrong_attempts_burn_the_code(): void
    {
        $otp = new EmailVerification;
        $otp->start('b@example.com', 'signup', '1.2.3.4');
        $code = $this->sentCode('b@example.com');
        $wrong = str_pad((string) (((int) $code + 1) % 1000000), 6, '0', STR_PAD_LEFT);
        for ($i = 1; $i <= 4; $i++) {
            $r = $otp->verify('b@example.com', 'signup', $wrong, '1.2.3.4');
            $this->assertFalse($r['ok']);
            $this->assertStringContainsString((5 - $i).' ', $r['error']);
        }
        $r = $otp->verify('b@example.com', 'signup', $wrong, '1.2.3.4');
        $this->assertStringContainsString('Too many wrong codes', $r['error']);
        $this->assertFalse($otp->verify('b@example.com', 'signup', $code, '1.2.3.4')['ok'], 'burnt after 5 wrong codes');
    }

    public function test_expiry_cooldown_and_newest_code_only(): void
    {
        $otp = new EmailVerification;
        $otp->start('c@example.com', 'signup', '1.2.3.4');
        $first = $this->sentCode('c@example.com');
        $again = $otp->start('c@example.com', 'signup', '1.2.3.4');
        $this->assertFalse($again['ok']);
        $this->assertSame(429, $again['status']);
        $this->assertStringContainsString('wait', $again['error']);
        $this->travel(61)->seconds();
        Mail::fake();
        $this->assertTrue($otp->start('c@example.com', 'signup', '1.2.3.4')['ok']);
        $second = $this->sentCode('c@example.com');
        if ($first !== $second) {
            $this->assertFalse($otp->verify('c@example.com', 'signup', $first, '1.2.3.4')['ok'], 'older code invalidated');
        }
        $this->travel(11)->minutes();
        $r = $otp->verify('c@example.com', 'signup', $second, '1.2.3.4');
        $this->assertFalse($r['ok']);
        $this->assertTrue($r['expired']);
    }

    public function test_per_email_and_per_ip_limits(): void
    {
        config(['passkey.email.sends_per_ip_per_hour' => 3, 'passkey.email.sends_per_email_per_hour' => 2, 'passkey.email.resend_cooldown' => 1]);
        $otp = new EmailVerification;
        $this->assertTrue($otp->start('d1@example.com', 'signup', '2001:db8:1:2::10')['ok']);
        $this->travel(2)->seconds();
        $this->assertTrue($otp->start('d1@example.com', 'signup', '9.9.9.9')['ok']);
        $this->travel(2)->seconds();
        $this->assertSame(429, $otp->start('d1@example.com', 'signup', '8.8.8.8')['status'], 'per-email hourly cap');
        // IPv6: same /64 = one bucket
        $this->assertTrue($otp->start('d2@example.com', 'signup', '2001:db8:1:2::11')['ok']);
        $this->assertTrue($otp->start('d3@example.com', 'signup', '2001:db8:1:2:ffff::1')['ok']);
        $this->assertSame(429, $otp->start('d4@example.com', 'signup', '2001:db8:1:2::99')['status'], 'per-/64 cap');
        $this->assertTrue($otp->start('d5@example.com', 'signup', '2001:db8:1:3::1')['ok'], 'other /64');
        $this->assertSame('20010db800010002::/64', EmailVerification::ipBucket('2001:db8:1:2::abcd'));
        $this->assertSame('1.2.3.4', EmailVerification::ipBucket('1.2.3.4'));
    }

    public function test_test_code_peek_is_testnet_and_resend_dev_only(): void
    {
        $otp = new EmailVerification;
        $otp->start('delivered+t1@resend.dev', 'signup', '1.2.3.4');
        $this->assertNull(EmailVerification::peekTestCode('delivered+t1@resend.dev'), 'flag off');
        config(['passkey.email.test_read' => true]);
        $this->travel(61)->seconds();
        $otp->start('delivered+t2@resend.dev', 'signup', '1.2.3.4');
        $otp->start('someone@example.com', 'signup', '1.2.3.4');
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) EmailVerification::peekTestCode('delivered+t2@resend.dev'));
        $this->assertNull(EmailVerification::peekTestCode('someone@example.com'), 'never for real addresses');
        config(['services.flow.network' => 'mainnet']);
        $this->assertNull(EmailVerification::peekTestCode('delivered+t2@resend.dev'), 'never on mainnet');
    }

    private function request(string $uri, array $data, Store $session): Request
    {
        $r = Request::create($uri, 'POST', $data, [], [], ['REMOTE_ADDR' => '5.6.7.8']);
        $r->setLaravelSession($session);
        app()->instance('request', $r);

        return $r;
    }

    public function test_signup_requires_a_confirmed_email_and_reveals_existing_accounts_only_after_the_code(): void
    {
        $session = new Store('t', new ArraySessionHandler(10));
        $session->start();
        $ctl = app(PasskeyPocController::class);
        PasskeyAccount::create(['address' => '0x00000000000000aa', 'email' => 'taken@example.com', 'status' => 'active']);

        // options without a code
        $res = $ctl->registerOptions($this->request('/passkey/register/options', ['email' => 'new@example.com'], $session));
        $this->assertSame(422, $res->getStatusCode());
        $this->assertTrue($res->getData(true)['needs_code']);

        // start: identical generic answer for a taken and a free address
        $a = $ctl->emailStart($this->request('/passkey/email/start', ['email' => 'taken@example.com', 'purpose' => 'signup'], $session))->getData(true);
        $b = $ctl->emailStart($this->request('/passkey/email/start', ['email' => 'new@example.com', 'purpose' => 'signup'], $session))->getData(true);
        unset($a['email'], $b['email']);
        $this->assertSame($a, $b);

        // verify the taken one: only now "existing"
        $v = $ctl->emailVerify($this->request('/passkey/email/verify', ['email' => 'taken@example.com', 'code' => $this->sentCode('taken@example.com'), 'purpose' => 'signup'], $session))->getData(true);
        $this->assertTrue($v['existing']);
        $res = $ctl->registerOptions($this->request('/passkey/register/options', ['email' => 'taken@example.com'], $session));
        $this->assertSame(422, $res->getStatusCode());

        // verify the new one -> options are issued for exactly that email
        $v = $ctl->emailVerify($this->request('/passkey/email/verify', ['email' => 'new@example.com', 'code' => $this->sentCode('new@example.com'), 'purpose' => 'signup'], $session))->getData(true);
        $this->assertFalse($v['existing']);
        $res = $ctl->registerOptions($this->request('/passkey/register/options', ['email' => 'new@example.com'], $session));
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('new@example.com', $res->getData(true)['publicKey']['user']['name']);
        $res = $ctl->registerOptions($this->request('/passkey/register/options', ['email' => 'other@example.com'], $session));
        $this->assertSame(422, $res->getStatusCode(), 'a confirmed email does not unlock another one');
    }

    public function test_restore_recreates_rows_after_db_loss(): void
    {
        $r = (new \ReflectionClass(AccountRecovery::class))->newInstanceWithoutConstructor();
        $pub = str_repeat('cd', 64);
        $cred = $r->restore(['address' => '0x89aa1b070df05cbf', 'key_index' => 2, 'public_key' => $pub], 'cred-id-1', 'abcd', 'Recovered · Linux · Chrome', '1.2.3.4');
        $this->assertSame('active', $cred->status);
        $this->assertSame(2, (int) $cred->key_index);
        $acc = $cred->account;
        $this->assertSame('0x89aa1b070df05cbf', $acc->address);
        $this->assertNull($acc->email);
        $this->assertNotNull($acc->recovered_at);
        $this->assertFalse($acc->hasVerifiedEmail());
        // partial loss: account row survived, credential row lost -> account reused
        $cred2 = $r->restore(['address' => '0x89aa1b070df05cbf', 'key_index' => 3, 'public_key' => str_repeat('ef', 64)], 'cred-id-2', null, 'x', '1.2.3.4');
        $this->assertSame($acc->id, $cred2->passkey_account_id);
        $this->assertSame(1, PasskeyAccount::count());
        $this->assertSame(2, PasskeyCredential::count());
    }
}
