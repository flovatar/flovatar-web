<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DustCollectible extends Model
{
    use HasFactory;

    protected $casts = [
        'flow_metadata' => AsCollection::class
    ];
    protected $guarded = [];
    protected $appends = ['full_svg'];
    protected $hidden = ['svg'];

    public function saleOffers()
    {
        return $this->morphMany(SaleOffer::class, 'item');
    }

    public function sales()
    {
        return $this->morphMany(Sale::class, 'item');
    }


    public function dustSeries()
    {
        return $this->belongsTo(DustSeries::class);
    }

    public function getFullSvgAttribute(){

        return $this->svg;
        /*
                if(!$this->svg || !$this->dust_series_id){
                    return '';
                }
                $series = DustSeries::whereFlowId($this->dust_series_id)->first();

                return $series ? $series->svg_prefix.$this->svg.$series->svg_suffix : '';

                $flowMeta = $this->flow_metadata;
                $tempLayers = isset($flowMeta['layers']) ? $flowMeta['layers'] : [];
                $svg = '';
                foreach($tempLayers as $key => $val){
                    $curTemplate = DustTemplate::whereFlowId($flowMeta['accessoryId'])->first();
                    if($curTemplate){
                        $svg = $curTemplate ? $curTemplate->svg : '';
                    }
                }
        */
    }
    public function getFullSvgNoBgAttribute(){

        return $this->svg;

    }

}
