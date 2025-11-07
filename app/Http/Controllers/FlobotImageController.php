<?php

namespace App\Http\Controllers;

use App\Jobs\ExportImage;
use App\Models\Flobot;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Pack;
use App\Models\Sale;
use Illuminate\Http\Request;
use Cache;
use Storage;

class FlobotImageController extends Controller
{
    public function svg($flobotId)
    {
        $flobotSvg = Cache::remember('flobot-image-'.$flobotId, now()->addMinutes(1), function() use ($flobotId) {
            $flobot = Flobot::where('flow_id', $flobotId)->first();
            return ($flobot) ? $flobot->full_svg : null;
        });
        return $flobotSvg ? response($flobotSvg)->header('Content-Type', 'image/svg+xml') : abort(404);
    }
    public function svgNoBg($flobotId)
    {
        $flobotSvg = Cache::remember('flobot-image-nobg-'.$flobotId, now()->addMinutes(1), function() use ($flobotId) {
            $flobot = Flobot::where('flow_id', $flobotId)->first();
            return ($flobot) ? $flobot->full_svg : null;
        });
        return $flobotSvg ? response($flobotSvg)->header('Content-Type', 'image/svg+xml') : abort(404);
    }
    public function gif($flovatarId){
        return Storage::disk('s3')->download('/flobot/gif/'.$flovatarId.'.gif');
    }
    public function gifNoBg($flovatarId){
        return Storage::disk('s3')->download('/flobot/gif/'.$flovatarId.'-nobg.gif');
    }
    public function png($flovatarId){
        return Storage::disk('s3')->download('/flobot/png/'.$flovatarId.'.png');
    }
    public function pngNoBg($flovatarId){
        return Storage::disk('s3')->download('/flobot/png/'.$flovatarId.'-nobg.png');
    }
    public function mp4($flovatarId){
        return Storage::disk('s3')->download('/flobot/mp4/'.$flovatarId.'.mp4');
    }
    public function mp4NoBg($flovatarId){
        return Storage::disk('s3')->download('/flobot/mp4/'.$flovatarId.'-nobg.mp4');
    }
}
