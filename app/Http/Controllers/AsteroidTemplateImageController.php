<?php

namespace App\Http\Controllers;

use App\Models\AsteroidTemplate;
use Cache;

class AsteroidTemplateImageController extends Controller
{
    public function __invoke($templateId)
    {
        $templateSvg = Cache::remember('asteroid-template-image-'.$templateId, now()->addMinutes(60), function() use ($templateId) {
            $template = AsteroidTemplate::where('flow_id', $templateId)->first();
            return ($template) ? $template->full_svg : null;
        });
        return $templateSvg ? response($templateSvg)->header('Content-Type', 'image/svg+xml') : abort(404);
    }
}
