<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Discord account linked to a passkey account for the Discord quest (UNIQUE both ways). */
class QuestDiscordLink extends Model
{
    protected $guarded = [];

    protected $casts = ['in_guild' => 'boolean', 'verified_at' => 'datetime'];
}
