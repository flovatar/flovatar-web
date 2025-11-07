<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsteroidTemplate extends Model
{
    use HasFactory;

    protected $casts = [
        'flow_metadata' => AsCollection::class
    ];
    protected $guarded = [];


    public function getFullSvgAttribute()
    {
        return config('flovatar.svgOpenAsteroid').$this->svg.config('flovatar.svgClose');
    }
}
