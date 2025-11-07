<?php

namespace App\Http\Controllers\Api;

use App\Models\Flovatar;
use Cache;
use App\Http\Controllers\Controller;

class CollectionApiController extends Controller
{
    public function address($address)
    {

        $flovatars = Cache::remember('db-flovatars-api-'.$address, now()->addMinutes(5), function() use ($address) {
            return Flovatar::whereFlowAddress($address)->orderBy('created_at', 'desc')->paginate(100)->makeHidden(['svg', 'id', 'full_svg', 'full_svg_no_bg']);
        });

        return $flovatars;
    }


    public function latest()
    {
        $flovatars = Cache::remember('db-flovatars-latest-'.$this->paramString(), now()->addMinutes(5), function() {
            return Flovatar::orderBy('created_at', 'desc')->paginate(100)->makeHidden(['svg', 'id', 'full_svg', 'full_svg_no_bg']);
        });

        return $flovatars;
    }
}
