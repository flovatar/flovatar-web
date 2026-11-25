<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * HybridCustody link: a passkey account (child) published to a wallet (parent) that claimed it.
 * status: proved (account-proof checked) -> published (child tx sealed) -> linked (parent claimed, verified on chain).
 * rewarded_at: ĐUST quests pay the "graduation" reward once per account (and should also check the parent address).
 */
class PasskeyWalletLink extends Model
{
    protected $guarded = [];

    protected $casts = ['linked_at' => 'datetime', 'rewarded_at' => 'datetime'];

    public function account()
    {
        return $this->belongsTo(PasskeyAccount::class, 'passkey_account_id');
    }
}
