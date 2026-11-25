<?php

namespace App\Http\Controllers;

use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Pack;
use Illuminate\Http\Request;
use Cache;

class BuilderController extends Controller
{
    public function __invoke()
    {

        // Template SVGs come from a separate cacheable script (TemplateDataController)
        $templatesScript = $this->templatesScriptUrl('series1');
        return view('builder')->with(['templatesScript' => $templatesScript]);
    }
}
