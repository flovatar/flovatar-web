<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Passkey PoC (testnet): a Flow account created for a passkey user. */
class PasskeyAccount extends Model
{
    protected $guarded = [];

    public function credentials()
    {
        return $this->hasMany(PasskeyCredential::class);
    }

    public function transactions()
    {
        return $this->hasMany(PasskeyTransaction::class);
    }
}
