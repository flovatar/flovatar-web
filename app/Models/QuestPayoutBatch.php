<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A batch of verified claims handed to the payout worker (status reserved | paid | failed). */
class QuestPayoutBatch extends Model
{
    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = ['total' => 'float', 'count' => 'integer', 'reserved_at' => 'datetime', 'reported_at' => 'datetime'];
}
