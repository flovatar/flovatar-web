<?php

namespace App\Http\Controllers;

use App\Jobs\ExportImage;
use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Pack;
use App\Models\Sale;
use Illuminate\Http\Request;
use Cache;
use Storage;

class FlovatarImageController extends Controller
{
    public function svg($flovatarId)
    {
        $flovatarSvg = Cache::remember('flovatar-image-'.$flovatarId, now()->addMinutes(1), function() use ($flovatarId) {
            $flovatar = Flovatar::where('flow_id', $flovatarId)->first();
            return ($flovatar) ? $flovatar->full_svg : null;
        });
        return $flovatarSvg ? response($flovatarSvg)->header('Content-Type', 'image/svg+xml') : abort(404);
    }
    public function svgNoBg($flovatarId)
    {
        $flovatarSvg = Cache::remember('flovatar-image-nobg-'.$flovatarId, now()->addMinutes(1), function() use ($flovatarId) {
            $flovatar = Flovatar::where('flow_id', $flovatarId)->first();
            return ($flovatar) ? $flovatar->full_svg_no_bg : null;
        });
        return $flovatarSvg ? response($flovatarSvg)->header('Content-Type', 'image/svg+xml') : abort(404);
    }
    public function gif($flovatarId){
        return Storage::disk('s3')->download('/flovatar/gif/'.$flovatarId.'.gif');
    }
    public function gifNoBg($flovatarId){
        return Storage::disk('s3')->download('/flovatar/gif/'.$flovatarId.'-nobg.gif');
    }
    public function png($flovatarId){
        return Storage::disk('s3')->download('/flovatar/png/'.$flovatarId.'.png');
    }
    public function pngNoBg($flovatarId){
        return Storage::disk('s3')->download('/flovatar/png/'.$flovatarId.'-nobg.png');
    }
    public function mp4($flovatarId){
        return Storage::disk('s3')->download('/flovatar/mp4/'.$flovatarId.'.mp4');
    }
    public function mp4NoBg($flovatarId){
        return Storage::disk('s3')->download('/flovatar/mp4/'.$flovatarId.'-nobg.mp4');
    }
}
