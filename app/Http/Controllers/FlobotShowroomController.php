<?php

namespace App\Http\Controllers;

use App\Models\Flobot;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Pack;
use Illuminate\Http\Request;
use Cache;

class FlobotShowroomController extends FilteredController
{
    public function __invoke()
    {

        $templates = $this->getFlobotTemplates()->makeHidden(['flow_metadata', 'svg']);

        return view('flobot-showroom')->with(['templates' => $templates]);
    }
}
