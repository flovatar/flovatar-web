<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleOffer extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'flow_metadata' => AsCollection::class
    ];

    public function item()
    {
        return $this->morphTo();
    }
    public function flovatar()
    {
        return $this->belongsTo(Flovatar::class, 'item_id')
            ->whereItemType(Flovatar::class);
    }
    public function dustCollectible()
    {
        return $this->belongsTo(DustCollectible::class, 'item_id')
            ->whereItemType(DustCollectible::class);
    }
    public function component()
    {
        return $this->belongsTo(FlovatarComponent::class, 'item_id')
            ->whereItemType(FlovatarComponent::class);
    }

    public function scopeIsFlovatar($query){
        return $query->whereItemType('App\Models\Flovatar');
    }
    public function scopeIsComponent($query){
        return $query->whereItemType('App\Models\FlovatarComponent');
    }
    public function scopeIsDustCollectible($query){
        return $query->whereItemType('App\Models\DustCollectible');
    }
}
