<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One-time email code (hashed) for passkey sign-up / email verification. See App\Services\Passkey\EmailVerification. */
class PasskeyEmailCode extends Model
{
    protected $guarded = [];

    protected $hidden = ['code_hash'];

    protected $casts = ['expires_at' => 'datetime', 'verified_at' => 'datetime', 'consumed_at' => 'datetime'];
}
