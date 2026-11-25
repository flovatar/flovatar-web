<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Flobot extends Model
{
    use HasFactory;

    protected $casts = [
        'flow_metadata' => AsCollection::class
    ];
    protected $guarded = [];
    protected $appends = ['full_svg', 'full_svg_no_bg'];
    protected $hidden = ['svg'];

    public function saleOffers()
    {
        return $this->morphMany(SaleOffer::class, 'item');
    }

    public function sales()
    {
        return $this->morphMany(Sale::class, 'item');
    }

    public function getFullSvgAttribute(){

        if(!$this->svg || !$this->flow_metadata){
            return '';
        }

        $flowMeta = $this->flow_metadata;
        $tempBackground = isset($flowMeta['backgroundId']) ? \App\Support\TemplateSvgs::get($flowMeta['backgroundId']) : '';
        $tempAccessories = isset($flowMeta['accessories']) ? $flowMeta['accessories'] : [];
        $accessorySvg = '';
        foreach($tempAccessories as $tempAccessory){
            // (sic) reads accessoryId like before; memoised lookup instead of a query per call
            $curAccessory = isset($flowMeta['accessoryId']) ? \App\Support\TemplateSvgs::get($flowMeta['accessoryId']) : '';
            if($curAccessory){
                $accessorySvg = $curAccessory;
            }
        }

        return config('flovatar.svgOpenFlobot').$tempBackground.$this->svg.$accessorySvg.config('flovatar.svgClose');
    }


    public function getFullSvgNoBgAttribute(){

        if(!$this->svg || !$this->flow_metadata){
            return '';
        }

        $flowMeta = $this->flow_metadata;
        $tempAccessories = isset($flowMeta['accessories']) ? $flowMeta['accessories'] : [];
        $accessorySvg = '';
        foreach($tempAccessories as $tempAccessory){
            // (sic) reads accessoryId like before; memoised lookup instead of a query per call
            $curAccessory = isset($flowMeta['accessoryId']) ? \App\Support\TemplateSvgs::get($flowMeta['accessoryId']) : '';
            if($curAccessory){
                $accessorySvg = $curAccessory;
            }
        }

        return config('flovatar.svgOpenFlobot').$this->svg.$accessorySvg.config('flovatar.svgClose');
    }

}
