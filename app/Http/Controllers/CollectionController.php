<?php

namespace App\Http\Controllers;

use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Pack;
use Illuminate\Http\Request;
use Cache;
use Auth;

class CollectionController extends AuthController
{
    public function __invoke($address)
    {
        $templates = $this->getAllTemplates();
        $templatesDust = $this->getDustTemplates()->makeHidden(['flow_metadata', 'svg']);
        $signature = Auth::check() ? '' : $this->getMessage();

        return view('collection')->with(['templates' => $templates, 'templatesDust' => $templatesDust, 'address' => $address, 'addressName' => '', 'signature' => $signature]);
    }
}
