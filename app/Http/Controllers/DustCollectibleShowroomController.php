<?php

namespace App\Http\Controllers;

use Cache;

class DustCollectibleShowroomController extends FilteredController
{
    public function __invoke()
    {

        $templates = $this->getDustTemplates()->makeHidden(['flow_metadata', 'svg']);

        return view('dust-collectible-showroom')->with(['templates' => $templates]);
    }
}
