<?php

namespace App\Http\Controllers;

use App\Models\Flobot;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Pack;
use App\Models\Sale;
use Illuminate\Http\Request;
use Cache;

class FlobotTemplateImageController extends Controller
{
    public function __invoke($templateId)
    {
        $templateSvg = Cache::remember('flobot-template-image-'.$templateId, now()->addMinutes(60), function() use ($templateId) {
            $template = FlovatarTemplate::where('flow_id', $templateId)->first();
            return ($template) ? $template->full_svg : null;
        });
        return $templateSvg ? response($templateSvg)->header('Content-Type', 'image/svg+xml') : abort(404);
    }
}
