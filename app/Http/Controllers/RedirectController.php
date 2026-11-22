<?php

namespace App\Http\Controllers;

use App\Models\DustCollectible;
use App\Models\Flovatar;
use App\Models\Flobot;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Pack;
use App\Models\Sale;
use Illuminate\Http\Request;
use Cache;

class RedirectController extends Controller
{
    public function flovatar($flovatarId)
    {
        $flovatar = Flovatar::where('flow_id', $flovatarId)->first();
        if(!$flovatar){
            $flovatar = Flovatar::where('name', $flovatarId)->first();
        }
        return $flovatar ? redirect('/flovatars/'.$flovatar->flow_id.'/'.$flovatar->flow_address) : abort(404);
    }
    /** FlovaChat was retired: send old /chat/{id|name} links to the Flovatar page. */
    public function retiredChat($flovatarId)
    {
        return redirect('/flovatars/'.rawurlencode($flovatarId), 301);
    }

    public function flobot($flobotId)
    {
        $flobot = Flobot::where('flow_id', $flobotId)->first();
        return $flobot ? redirect('/flobots/'.$flobot->flow_id.'/'.$flobot->flow_address) : abort(404);
    }

    public function component($componentId)
    {
        $component = FlovatarComponent::where('flow_id', $componentId)->first();
        return $component ? redirect('/components/'.$component->flow_id.'/'.$component->flow_address) : abort(404);
    }
    public function collectible($collectibleId)
    {
        $collectible = DustCollectible::where('flow_id', $collectibleId)->first();
        return $collectible ? redirect('/collectibles/'.$collectible->flow_id.'/'.$collectible->flow_address) : abort(404);
    }
}
