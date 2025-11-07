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

class JsonController extends Controller
{
    public function flovatar($flovatarId)
    {
        $flovatar = Flovatar::where('flow_id', $flovatarId)->first();
        return $flovatar ? $flovatar->flow_metadata : abort(404);
    }
    public function flobot($flobotId)
    {
        $flobot = Flobot::where('flow_id', $flobotId)->first();
        return $flobot ? $flobot->flow_metadata : abort(404);
    }

    public function component($componentId)
    {
        $component = FlovatarComponent::where('flow_id', $componentId)->first();
        return $component ? $component->flow_metadata : abort(404);
    }
    public function collectible($collectibleId)
    {
        $collectible = DustCollectible::where('flow_id', $collectibleId)->first();
        return $collectible ? $collectible->flow_metadata : abort(404);
    }
}
