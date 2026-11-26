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

    private static ?bool $hasDisplay = null;

    /** Round-4 columns name / thumbnail present (migration 2026_11_26_200001)? Memoized per process. */
    public static function hasDisplayColumns(): bool
    {
        return self::$hasDisplay ??= \Illuminate\Support\Facades\Schema::hasColumn('island_residents', 'thumbnail');
    }

    public static function forgetDisplayColumns(): void
    {
        self::$hasDisplay = null;
    }

    public function island()
    {
        return $this->belongsTo(Island::class, 'island_flow_id', 'flow_id');
    }
}
