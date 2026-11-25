<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One claim per (passkey account, challenge): pending | verified | paid | rejected (+ payout batch / tx). */
class QuestClaim extends Model
{
    protected $guarded = [];

    protected $casts = ['evidence' => 'array', 'amount' => 'float', 'verified_at' => 'datetime', 'paid_at' => 'datetime', 'rejected_at' => 'datetime'];

    public const PENDING = 'pending';

    public const VERIFIED = 'verified';

    public const PAID = 'paid';

    public const REJECTED = 'rejected';

    public function challenge()
    {
        return $this->belongsTo(QuestChallenge::class, 'challenge_id');
    }

    public function account()
    {
        return $this->belongsTo(PasskeyAccount::class, 'passkey_account_id');
    }
}
