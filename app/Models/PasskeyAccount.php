<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Passkey account (testnet): a Flow account created for a passkey user (or re-created from the chain, recovered_at). */
class PasskeyAccount extends Model
{
    protected $guarded = [];

    protected $casts = ['email_verified_at' => 'datetime', 'recovered_at' => 'datetime'];

    public function credentials()
    {
        return $this->hasMany(PasskeyCredential::class);
    }

    public function transactions()
    {
        return $this->hasMany(PasskeyTransaction::class);
    }

    public function walletLinks()
    {
        return $this->hasMany(PasskeyWalletLink::class);
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email !== null && $this->email !== '' && $this->email_verified_at !== null;
    }
}
