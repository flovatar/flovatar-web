<?php

namespace App\Http\Controllers;

use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\SaleOffer;
use App\Models\Pack;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Cache;

class SearchDustCollectibleController extends FilteredController
{
    public function __invoke()
    {

        $collectibles = Cache::remember('db-dust-collectible-search-'.$this->paramString(), now()->addSeconds(30), function() {
            return $this->getFilteredCollectibles(request());
        });

        return $collectibles;
    }
}
