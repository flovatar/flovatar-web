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

class SearchFlobotController extends FilteredController
{
    public function __invoke()
    {

        $flobots = Cache::remember('db-flobot-search-'.$this->paramString(), now()->addSeconds(30), function() {
            return $this->getFilteredFlobots(request());
        });

        return $flobots;
    }
}
