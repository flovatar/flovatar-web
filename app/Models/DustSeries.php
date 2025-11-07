<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DustSeries extends Model
{
    use HasFactory;

    protected $casts = [
        'flow_metadata' => AsCollection::class,
        'colors' => AsCollection::class,
        'layers' => AsCollection::class
    ];
    protected $guarded = [];

    public function dustTemplates()
    {
        return $this->hasMany(DustTemplate::class);
    }

    public function dustCollectible()
    {
        return $this->hasMany(DustCollectible::class);
    }
}
