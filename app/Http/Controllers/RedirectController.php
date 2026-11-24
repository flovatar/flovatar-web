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
        return $this->toOwnerUrl('flovatars', $flovatar);
    }
    /** FlovaChat was retired: send old /chat/{id|name} links to the Flovatar page. */
    public function retiredChat($flovatarId)
    {
        return redirect('/flovatars/'.rawurlencode($flovatarId), 301);
    }

    public function flobot($flobotId)
    {
        $flobot = Flobot::where('flow_id', $flobotId)->first();
        return $this->toOwnerUrl('flobots', $flobot);
    }

    public function component($componentId)
    {
        $component = FlovatarComponent::where('flow_id', $componentId)->first();
        return $this->toOwnerUrl('components', $component);
    }
    public function collectible($collectibleId)
    {
        $collectible = DustCollectible::where('flow_id', $collectibleId)->first();
        return $this->toOwnerUrl('collectibles', $collectible);
    }

    /**
     * Canonical owner URL. Items without a known owner (e.g. a Flobit equipped inside a Flovatar, or an indexer gap)
     * 404 instead of redirecting to '/{segment}/{id}/' which the router collapses back to '/{segment}/{id}' (redirect loop).
     */
    private function toOwnerUrl(string $segment, $item)
    {
        abort_unless($item && $item->flow_address, 404);

        return redirect('/'.$segment.'/'.$item->flow_id.'/'.$item->flow_address);
    }
}
