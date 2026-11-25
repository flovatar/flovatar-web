<?php

namespace App\Http\Controllers;

use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Pack;
use Illuminate\Http\Request;
use Cache;

class DustBuilderController extends Controller
{
    public function __invoke()
    {

        // Template SVGs come from a separate cacheable script (TemplateDataController).
        // Same rows/order/svg strings as before; only flow_metadata (unused by dust-builder.js) is left out.
        $templatesScript = $this->templatesScriptUrl('dust');
        $series = $this->getDustSeries();
        return view('builder-dust')->with(['templatesScript' => $templatesScript, 'series' => $series]);
    }
}
