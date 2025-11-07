<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DustTemplate extends Model
{
    use HasFactory;

    protected $casts = [
        'flow_metadata' => AsCollection::class
    ];
    protected $guarded = [];

    public function dustAccessories()
    {
        return $this->hasMany(DustAccessory::class);
    }

    public function dustSeries()
    {
        return $this->belongsTo(DustSeries::class);
    }

    public function getFullSvgAttribute(){

        if(!$this->svg || !$this->dust_series_id){
            return '';
        }
        $series = DustSeries::whereFlowId($this->dust_series_id)->first();

        return $series ? $series->svg_prefix.$this->svg.$series->svg_suffix : '';

    }
}
