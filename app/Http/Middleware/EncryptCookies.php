<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array
     */
    protected $except = [
        'vipcookie',
        // login marker read by JS (AuthController::LOGIN_MARKER_COOKIE), value '1'
        'fl_auth',
        // stealth gate cookie: HMAC-signed by App\Support\Stealth itself (the headless Worker checks its presence)
        'fl_s',
    ];
}
