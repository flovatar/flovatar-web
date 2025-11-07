<?php

namespace App\Http\Controllers;

use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Pack;
use Illuminate\Http\Request;
use Cache;

class FlobotBuilderController extends Controller
{
    public function __invoke()
    {

        $templates = $this->getFlobotTemplates();
        return view('flobot-builder')->with(['templates' => $templates]);
    }
}
