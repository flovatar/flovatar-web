<?php

namespace App\Http\Controllers;

use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Pack;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Cache;

class DownloadController extends Controller
{
    public function svg($flovatarId, $address)
    {
        $flovatar = Flovatar::whereFlowAddress($address)->whereFlowId($flovatarId)->first();
        if(!$flovatar){
            return abort(404);
        }
        return response()->streamDownload(function () use ($flovatar){
            echo $flovatar->full_svg;
        }, 'Flovatar-'.$flovatar->name.'.svg');
    }

    public function templateSvg($templateId)
    {
        $fullSvg = Cache::remember('db-template-svg-'.$templateId, now()->addMinutes(60), function() use ($templateId) {
            $template = FlovatarTemplate::whereFlowId($templateId)->first();
            return $template ? $template->full_svg : null;
        });

        if(!$fullSvg){
            return abort(404);
        }
        return $fullSvg;
        /*
        return response()->streamDownload(function () use ($fullSvg, $templateId){
            echo $fullSvg;
        }, 'Flovatar-Template-'.$templateId.'.svg');
        */
    }

    public function png($flovatarId, $address)
    {
        $flovatar = Flovatar::whereFlowAddress($address)->whereFlowId($flovatarId)->first();
        if(!$flovatar){
            return abort(404);
        }
        return response()->streamDownload(function () use ($flovatar){
            echo $flovatar->png;
        }, 'Flovatar-'.$flovatar->name.'.png');
    }
}
