<?php

namespace App\Http\Controllers;

use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Pack;
use Illuminate\Http\Request;
use Cache;

class ShowroomController extends FilteredController
{
    public function __invoke()
    {
        $templates = $this->getTemplates()->makeHidden(['flow_metadata', 'svg']);

        return view('showroom')->with(['templates' => $templates]);
    }
}
