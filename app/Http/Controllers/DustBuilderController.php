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

        $templates = $this->getDustTemplates();
        $series = $this->getDustSeries();
        return view('builder-dust')->with(['templates' => $templates, 'series' => $series]);
    }
}
