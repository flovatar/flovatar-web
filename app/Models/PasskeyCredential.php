<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Passkey PoC (testnet): one WebAuthn credential = one Flow account key (ECDSA_P256 / SHA2_256). */
class PasskeyCredential extends Model
{
    protected $guarded = [];

    protected $casts = ['backed_up' => 'boolean', 'last_used_at' => 'datetime'];

    public function account()
    {
        return $this->belongsTo(PasskeyAccount::class, 'passkey_account_id');
    }
}
