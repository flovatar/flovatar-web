<?php

namespace App\Http\Controllers;

use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Pack;
use Illuminate\Http\Request;
use Cache;

class AsteroidBuilderController extends Controller
{
    public function __invoke()
    {
        $templates = $this->getAsteroidTemplates();
        return view('builder-asteroid')->with(['templates' => $templates]);
    }
}
