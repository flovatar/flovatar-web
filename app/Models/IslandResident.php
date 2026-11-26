<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A reference from an island slot (chain slot id) to an NFT that stays in the owner's wallet. */
class IslandResident extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'valid' => 'boolean',
        'set_at' => 'datetime',
    ];

    public function island()
    {
        return $this->belongsTo(Island::class, 'island_flow_id', 'flow_id');
    }
}
