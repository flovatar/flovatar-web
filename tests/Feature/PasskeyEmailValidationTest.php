<?php

namespace Tests\Feature;

use App\Http\Controllers\PasskeyPocController;
use Illuminate\Http\Request;
use Tests\TestCase;

/** Sign-up must require a valid email (server side, before any passkey ceremony or DB write). */
class PasskeyEmailValidationTest extends TestCase
{
    private function signupOptions(?string $email): array
    {
        config(['passkey.enabled' => true, 'services.flow.network' => 'testnet', 'services.flow.rest' => 'https://rest-testnet.onflow.org']);
        $req = Request::create('/passkey/register/options', 'POST', $email === null ? [] : ['email' => $email]);
        $res = app(PasskeyPocController::class)->registerOptions($req);

        return [$res->getStatusCode(), $res->getData(true)];
    }

    public function test_missing_or_blank_email_is_rejected(): void
    {
        foreach ([null, '', '   '] as $email) {
            [$status, $body] = $this->signupOptions($email);
            $this->assertSame(422, $status);
            $this->assertStringContainsString('enter your email', $body['error']);
        }
    }

    public function test_invalid_email_is_rejected(): void
    {
        foreach (['foo', 'foo@', '@bar.com', 'foo bar@baz.com', str_repeat('a', 185).'@x.com'] as $email) {
            [$status, $body] = $this->signupOptions($email);
            $this->assertSame(422, $status, $email);
            $this->assertStringContainsString('valid email', $body['error']);
        }
    }
}
