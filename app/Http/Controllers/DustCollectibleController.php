<?php

namespace App\Http\Controllers;

use App\Models\DustCollectible;
use Cache;

class DustCollectibleController extends Controller
{
    public function __invoke($collectibleId, $address)
    {

        // only rarity/flow_id are used (rarity text); the collectible svg comes from the collectible itself
        $templates = $this->getDustTemplatesLean();
        $sales = Cache::remember(\App\Support\Stealth::cacheKey('dust-collectible-sales-'.$collectibleId), now()->addMinutes(10), function() use ($collectibleId) {
            $collectible = DustCollectible::where('flow_id', $collectibleId)->first();
            return $collectible ? $collectible->sales()->marketVisible()->orderBy('created_at', 'asc')->get() : [];
        });
        $saleOffer = Cache::remember('dust-collectible-sale-offers-'.$collectibleId, now()->addMinutes(1), function() use ($collectibleId) {
            $collectible = DustCollectible::where('flow_id', $collectibleId)->first();
            return $collectible ? $collectible->saleOffers()->notDust()->where('is_completed', false)->orderBy('created_at', 'desc')->first() : null;
        });
        return view('dust-collectible')->with(['templates' => $templates, 'address' => $address, 'collectibleId' => $collectibleId, 'collectibleSales' => $sales, 'collectibleSaleOffer' => $saleOffer]);
    }
}
