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
        $tempBackground = isset($flowMeta['backgroundId']) ? \App\Support\TemplateSvgs::get($flowMeta['backgroundId']) : '';
        $tempAccessory = isset($flowMeta['accessoryId']) ? \App\Support\TemplateSvgs::get($flowMeta['accessoryId']) : '';
        $tempHat = isset($flowMeta['hatId']) ? \App\Support\TemplateSvgs::get($flowMeta['hatId']) : '';
        $tempEyeglasses = isset($flowMeta['eyeglassesId']) ? \App\Support\TemplateSvgs::get($flowMeta['eyeglassesId']) : '';

        return config('flovatar.svgOpen').$tempBackground.$this->svg.$tempAccessory.$tempEyeglasses.$tempHat.config('flovatar.svgClose');
    }


    public function getFullSvgNoBgAttribute(){

        if(!$this->svg || !$this->flow_metadata){
            return '';
        }

        $flowMeta = $this->flow_metadata;
        $tempAccessory = isset($flowMeta['accessoryId']) ? \App\Support\TemplateSvgs::get($flowMeta['accessoryId']) : '';
        $tempHat = isset($flowMeta['hatId']) ? \App\Support\TemplateSvgs::get($flowMeta['hatId']) : '';
        $tempEyeglasses = isset($flowMeta['eyeglassesId']) ? \App\Support\TemplateSvgs::get($flowMeta['eyeglassesId']) : '';

        return config('flovatar.svgOpen').$this->svg.$tempAccessory.$tempEyeglasses.$tempHat.config('flovatar.svgClose');
    }

}
