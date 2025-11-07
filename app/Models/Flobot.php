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
        $tempBackground = isset($flowMeta['backgroundId']) ? FlovatarTemplate::whereFlowId($flowMeta['backgroundId'])->first() : '';
        $tempBackground = $tempBackground ? $tempBackground->svg : '';
        $tempAccessories = isset($flowMeta['accessories']) ? $flowMeta['accessories'] : [];
        $accessorySvg = '';
        foreach($tempAccessories as $tempAccessory){
            $curAccessory = FlovatarTemplate::whereFlowId($flowMeta['accessoryId'])->first();
            if($curAccessory){
                $accessorySvg = $curAccessory ? $curAccessory->svg : '';
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
            $curAccessory = FlovatarTemplate::whereFlowId($flowMeta['accessoryId'])->first();
            if($curAccessory){
                $accessorySvg = $curAccessory ? $curAccessory->svg : '';
            }
        }

        return config('flovatar.svgOpenFlobot').$this->svg.$accessorySvg.config('flovatar.svgClose');
    }

}
