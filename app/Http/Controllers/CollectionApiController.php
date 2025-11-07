<?php

namespace App\Http\Controllers;

use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Pack;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Arr;
use Cache;

class CollectionApiController extends Controller
{
    public function address($address)
    {

        $flovatars = Cache::remember('db-flovatars-api-'.$address.'-'.$this->paramString(), now()->addMinutes(5), function() use ($address) {
            return Flovatar::whereFlowAddress($address)->orderBy('created_at', 'desc')->paginate(10);
        });

        return $flovatars;
    }


    public function latest()
    {
        $flovatars = Cache::remember('db-flovatars-latest-'.$this->paramString(), now()->addMinutes(5), function() {
            return Flovatar::orderBy('created_at', 'desc')->paginate(10);
        });

        return $flovatars;
    }
}
