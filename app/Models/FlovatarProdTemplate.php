<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlovatarProdTemplate extends Model
{
    use HasFactory;

    protected $casts = [
        'flow_metadata' => AsCollection::class
    ];
    protected $guarded = [];

    public function flovatarComponents()
    {
        return $this->hasMany(FlovatarComponent::class);
    }

    public function getFullSvgAttribute(){
        return config('flovatar.svgOpen').$this->svg.config('flovatar.svgClose');
    }
}
