<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Admin flags for a quest account: frozen (no claims, no payouts) and a per-account cap override. */
class QuestAccountFlag extends Model
{
    protected $guarded = [];

    protected $casts = ['frozen' => 'boolean', 'cap_override' => 'float'];
}
