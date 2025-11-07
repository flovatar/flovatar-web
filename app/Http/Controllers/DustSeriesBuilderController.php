<?php

namespace App\Http\Controllers;

use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Pack;
use Illuminate\Http\Request;
use Cache;

class DustSeriesBuilderController extends Controller
{
    public function __invoke()
    {

        $templates = $this->getDustTemplates(2);
        $series = $this->getDustSeries(2);
        return view('builder-dust')->with(['templates' => $templates, 'series' => $series]);
    }
}
