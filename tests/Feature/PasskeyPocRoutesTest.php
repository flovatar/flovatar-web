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
}
