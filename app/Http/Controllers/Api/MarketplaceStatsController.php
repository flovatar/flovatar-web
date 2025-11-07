<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\FilteredController;
use App\Models\Sale;
use Cache;

class MarketplaceStatsController extends FilteredController
{
    public function __invoke()
    {
        return $this->getStats();
    }
}
