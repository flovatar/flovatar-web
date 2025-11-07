<?php

namespace App\Http\Controllers;

use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Pack;
use Illuminate\Http\Request;
use Cache;
use Auth;

class CollectionFindController extends AuthController
{
    public function __invoke($name)
    {
        $templates = $this->getTemplates();
        $templatesDust = $this->getDustTemplates()->makeHidden(['flow_metadata', 'svg']);
        $signature = Auth::check() ? '' : $this->getMessage();
        return view('collection')->with(['templates' => $templates, 'templatesDust' => $templatesDust, 'addressName' => $name, 'address' => '', 'signature' => $signature]);
    }
}
