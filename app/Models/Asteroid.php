<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * Asteroid Island NFT (not minted yet). Referenced by AsteroidImageController.
 */
class Asteroid extends Model
{
    protected $guarded = [];

    protected $casts = [
        'flow_metadata' => AsCollection::class,
    ];

    public function getFullSvgAttribute()
    {
        return config('flovatar.svgOpenAsteroid', config('flovatar.svgOpen')).$this->svg.config('flovatar.svgClose');
    }
}
