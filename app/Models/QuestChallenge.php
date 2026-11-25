<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** ĐUST Quests catalogue row (seeded from config/quests.php once per key; amounts edited in /quests/admin). */
class QuestChallenge extends Model
{
    protected $guarded = [];

    protected $casts = ['active' => 'boolean', 'amount' => 'float', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'max_claims_total' => 'integer', 'sort' => 'integer'];
}
