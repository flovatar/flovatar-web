<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\FilteredController;
use Cache;

class SearchNoSvgController extends FilteredController
{
    public function __invoke()
    {

        $sales = Cache::remember(\App\Support\Stealth::cacheKey('marketplace-sales-search-'.$this->paramString()), now()->addSeconds(10), function() {
            $req = request()->merge(['nosvg' => 1]);
            return $this->getFilteredSales($req);
        });

        return $sales;
    }
}
