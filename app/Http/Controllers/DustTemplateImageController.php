<?php

namespace App\Http\Controllers;

use App\Models\DustTemplate;
use Cache;

class DustTemplateImageController extends Controller
{
    public function __invoke($templateId)
    {
        $templateSvg = Cache::remember('dust-template-image-'.$templateId, now()->addMinutes(60), function() use ($templateId) {
            $template = DustTemplate::where('flow_id', $templateId)->first();
            return ($template) ? $template->full_svg : null;
        });
        return $templateSvg ? response($templateSvg)->header('Content-Type', 'image/svg+xml') : abort(404);
    }
}
