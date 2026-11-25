<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** The passkey PoC must not exist unless PASSKEY_POC_ENABLED=true (prod safety). */
class PasskeyPocRoutesTest extends TestCase
{
    public function test_routes_are_not_registered_by_default(): void
    {
        $this->assertFalse((bool) config('passkey.enabled'));
        $this->assertFalse(Route::has('passkey.page'));
        $this->assertFalse(Route::has('passkey.register.options'));
        $this->assertFalse(Route::has('passkey.tx.submit'));
    }

    public function test_site_integration_is_off_by_default(): void
    {
        $this->assertFalse(\App\Support\PasskeyLogin::enabled());
        $this->assertSame('fcl.authenticate()', \App\Support\PasskeyLogin::connectAction());
        $this->assertSame('fcl.signUp()', \App\Support\PasskeyLogin::connectAction('fcl.signUp()'));
        $this->assertFalse(Route::has('passkey.tx.template'));
        $this->assertFalse(Route::has('passkey.account'));
    }

    public function test_login_nonce_shape_unchanged_when_off(): void
    {
        $json = $this->getJson('/login/nonce')->assertOk()->json();
        $this->assertSame(['nonce', 'message', 'authenticated', 'address'], array_keys($json));
    }
}
