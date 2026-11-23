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

class SearchFlovatarController extends FilteredController
{
    public function __invoke()
    {

        $flovatars = Cache::remember('db-flovatar-search-lean-'.$this->paramString(), now()->addSeconds(30), function() {
            return $this->getFilteredFlovatars(request(), true);
        });

        return $flovatars;
    }
}
