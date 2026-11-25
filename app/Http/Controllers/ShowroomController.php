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
        $templates = $this->templatesLean('series1');

        return view('showroom')->with(['templates' => $templates]);
    }
}
