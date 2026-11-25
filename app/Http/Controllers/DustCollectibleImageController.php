<?php

namespace App\Http\Controllers;

use App\Jobs\ExportImage;
use App\Models\DustCollectible;
use App\Models\DustAccessory;
use App\Models\DustTemplate;
use App\Models\Pack;
use App\Models\Sale;
use Illuminate\Http\Request;
use Cache;
use Storage;

class DustCollectibleImageController extends Controller
{
    public function svg($collectibleId)
    {
        $collectibleSvg = Cache::remember('dust-collectible-image-'.$collectibleId, now()->addMinutes(1), function() use ($collectibleId) {
            $collectible = DustCollectible::where('flow_id', $collectibleId)->first();
            return ($collectible) ? $collectible->full_svg : null;
        });
        return $collectibleSvg ? response($collectibleSvg)->header('Content-Type', 'image/svg+xml') : abort(404);
    }

    public function gif($collectibleId){
        return $this->imageFromStorage('/dust/collectible/gif/'.$collectibleId.'.gif');
    }
    public function png($collectibleId){
        return $this->imageFromStorage('/dust/collectible/png/'.$collectibleId.'.png');
    }
    public function mp4($collectibleId){
        return $this->imageFromStorage('/dust/collectible/mp4/'.$collectibleId.'.mp4');
    }
}
