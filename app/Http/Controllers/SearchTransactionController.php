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

class SearchTransactionController extends FilteredController
{
    public function __invoke()
    {

        $sales = Cache::remember('db-transactions-search-'.$this->paramString(), now()->addSeconds(10), function() {
            return $this->getFilteredTransactions(request());
        });

        return $sales;
    }
}
