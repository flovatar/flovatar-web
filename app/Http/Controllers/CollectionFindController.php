<?php

namespace App\Http\Controllers;

use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Pack;
use Illuminate\Http\Request;
use Cache;

class CollectionFindController extends AuthController
{
    public function __invoke($name)
    {
        // Lean lists: the page reads the wallet's NFTs on chain (FCL) and only needs template names/categories.
        $templates = $this->templatesLean('all');
        $templatesDust = $this->getDustTemplatesLean();
        return view('collection')->with(['templates' => $templates, 'templatesDust' => $templatesDust, 'addressName' => $name, 'address' => '']);
    }
}
