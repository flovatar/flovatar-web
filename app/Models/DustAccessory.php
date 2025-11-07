<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DustAccessory extends Model
{
    use HasFactory;


    protected $casts = [
        'flow_metadata' => AsCollection::class,
        'destroyed' => 'boolean',
    ];
    protected $guarded = [];

    public function saleOffers()
    {
        return $this->morphMany(SaleOffer::class, 'item');
    }

    public function sales()
    {
        return $this->morphMany(Sale::class, 'item');
    }

    public function dustTemplate()
    {
        return $this->belongsTo(DustTemplate::class);
    }


    public function scopeWhereLayer($query, $layer){
        return $query->whereHas('dustTemplate', function(Builder $query2) use ($layer) {
            return $query2->whereLayer($layer);
        });
    }
    public function scopeWhereRarity($query, $rarity){
        return $query->whereHas('dustTemplate', function(Builder $query2) use ($rarity) {
            return $query2->whereRarity($rarity);
        });
    }
}
