<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Cache of a FlovatarAsteroidIsland template (metadata + joined SVG chunks), refreshed by `islands:sync`. */
class IslandTemplate extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['svg', 'svg_front'];

    protected $casts = [
        'enabled' => 'boolean',
        'metadata' => 'array',
    ];
}
