<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackRewindPack extends Model
{
    protected $table = 'pack_rewind_packs';

    protected $guarded = ['id'];

    protected $casts = [
        'contents' => 'array',
        'reserved_until' => 'datetime',
        'sold_at' => 'datetime',
        'opened_at' => 'datetime',
    ];

    /** Rows that can be offered right now: available, or reserved with an expired hold. */
    public function scopeOfferable($q)
    {
        return $q->where(function ($w) {
            $w->where('status', 'available')
                ->orWhere(fn ($r) => $r->where('status', 'reserved')->where('reserved_until', '<', now()));
        });
    }
}
