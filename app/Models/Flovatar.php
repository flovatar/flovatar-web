<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Flovatar extends Model
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
        $tempAccessory = isset($flowMeta['accessoryId']) ? FlovatarTemplate::whereFlowId($flowMeta['accessoryId'])->first() : '';
        $tempAccessory = $tempAccessory ? $tempAccessory->svg : '';
        $tempHat = isset($flowMeta['hatId']) ? FlovatarTemplate::whereFlowId($flowMeta['hatId'])->first() : '';
        $tempHat = $tempHat ? $tempHat->svg : '';
        $tempEyeglasses = isset($flowMeta['eyeglassesId']) ? FlovatarTemplate::whereFlowId($flowMeta['eyeglassesId'])->first() : '';
        $tempEyeglasses = $tempEyeglasses ? $tempEyeglasses->svg : '';

        return config('flovatar.svgOpen').$tempBackground.$this->svg.$tempAccessory.$tempEyeglasses.$tempHat.config('flovatar.svgClose');
    }


    public function getFullSvgNoBgAttribute(){

        if(!$this->svg || !$this->flow_metadata){
            return '';
        }

        $flowMeta = $this->flow_metadata;
        $tempAccessory = isset($flowMeta['accessoryId']) ? FlovatarTemplate::whereFlowId($flowMeta['accessoryId'])->first() : '';
        $tempAccessory = $tempAccessory ? $tempAccessory->svg : '';
        $tempHat = isset($flowMeta['hatId']) ? FlovatarTemplate::whereFlowId($flowMeta['hatId'])->first() : '';
        $tempHat = $tempHat ? $tempHat->svg : '';
        $tempEyeglasses = isset($flowMeta['eyeglassesId']) ? FlovatarTemplate::whereFlowId($flowMeta['eyeglassesId'])->first() : '';
        $tempEyeglasses = $tempEyeglasses ? $tempEyeglasses->svg : '';

        return config('flovatar.svgOpen').$this->svg.$tempAccessory.$tempEyeglasses.$tempHat.config('flovatar.svgClose');
    }

}
