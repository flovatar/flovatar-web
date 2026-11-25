<?php

namespace App\Http\Controllers;

use App\Jobs\ExportImage;
use App\Models\Asteroid;
use App\Models\DustCollectible;
use App\Models\DustAccessory;
use App\Models\DustTemplate;
use App\Models\Pack;
use App\Models\Sale;
use Illuminate\Http\Request;
use Cache;
use Storage;

class AsteroidImageController extends Controller
{
    public function svg($collectibleId)
    {
        $collectibleSvg = Cache::remember('asteroid-image-'.$collectibleId, now()->addMinutes(1), function() use ($collectibleId) {
            $collectible = Asteroid::where('flow_id', $collectibleId)->first();
            return ($collectible) ? $collectible->full_svg : null;
        });
        return $collectibleSvg ? response($collectibleSvg)->header('Content-Type', 'image/svg+xml') : abort(404);
    }

    public function gif($collectibleId){
        return $this->imageFromStorage('/asteroid/gif/'.$collectibleId.'.gif');
    }
    public function png($collectibleId){
        return $this->imageFromStorage('/asteroid/png/'.$collectibleId.'.png');
    }
    public function mp4($collectibleId){
        return $this->imageFromStorage('/asteroid/mp4/'.$collectibleId.'.mp4');
    }
}
