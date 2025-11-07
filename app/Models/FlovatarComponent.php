<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class FlovatarComponent extends Model
{
    use HasFactory;

    protected $casts = [
        'flow_metadata' => AsCollection::class,
        'destroyed' => 'boolean',
        'packed' => 'boolean'
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

    public function flovatarTemplate()
    {
        return $this->belongsTo(FlovatarTemplate::class);
    }



    public function scopeReadyForPack($query){
        return $query->whereFlowAddress(config('services.flow.address'))
            ->whereNotNull('flow_id')
            ->whereNotNull('flow_address')
            ->whereDestroyed(0)
            ->wherePacked(0);
    }
    public function scopeReadyForFlobitPack($query){
        return $query->whereFlowAddress(config('services.flow.address'))
            ->whereNotNull('flow_id')
            ->whereNotNull('flow_address')
            ->whereDestroyed(0)
            ->wherePacked(0)
            ->whereHas('flovatarTemplate', function(Builder $query2) {
                return $query2->whereColor('c3');
            });
    }
    public function scopeReadyForDustPack($query){
        return $query->whereFlowAddress(config('services.flow.address'))
            ->whereNotNull('flow_id')
            ->whereNotNull('flow_address')
            ->whereDestroyed(0)
            ->wherePacked(0)
            ->whereHas('flovatarTemplate', function(Builder $query2) {
                return $query2->whereColor('dust-pack');
            });
    }
    public function scopeReadyForTerraKitPack($query, $series=2){
        return $query->whereFlowAddress(config('services.flow.address'))
            ->whereNotNull('flow_id')
            ->whereNotNull('flow_address')
            ->whereDestroyed(0)
            ->wherePacked(0)
            ->whereHas('flovatarTemplate', function(Builder $query2) use ($series){
                return $query2->whereSeries($series);
            });
    }

    public function scopeWhereCategory($query, $category){
        return $query->whereHas('flovatarTemplate', function(Builder $query2) use ($category) {
                return $query2->whereCategory($category);
            });
    }
    public function scopeWhereBoosterRarity($query, $rarity){
        return $query->whereHas('flovatarTemplate', function(Builder $query2) use ($rarity) {
            return $query2->whereCategory('boost')->whereRarity($rarity);
        });
    }
    public function scopeWhereComponentRarity($query, $rarity){
        return $query->whereHas('flovatarTemplate', function(Builder $query2) use ($rarity) {
            return $query2->where('category','not like','spark')->where('category','not like','boost')->whereRarity($rarity);
        });
    }
    public function scopeWhereNotCategory($query, $category){
        return $query->whereHas('flovatarTemplate', function(Builder $query2) use ($category) {
            return $query2->where('category','not like',$category);
        });
    }
}
