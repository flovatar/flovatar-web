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

class SearchController extends FilteredController
{
    public function __invoke()
    {

        $sales = Cache::remember('marketplace-sales-search-lean-'.$this->paramString(), now()->addSeconds(10), function() {
            return $this->getFilteredSales(request(), true);
        });

        return $sales;
    }
}
