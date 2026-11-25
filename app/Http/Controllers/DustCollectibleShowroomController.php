<?php

namespace App\Http\Controllers;

use Cache;

class DustCollectibleShowroomController extends FilteredController
{
    public function __invoke()
    {

        $templates = $this->getDustTemplatesLean();

        return view('dust-collectible-showroom')->with(['templates' => $templates]);
    }
}
