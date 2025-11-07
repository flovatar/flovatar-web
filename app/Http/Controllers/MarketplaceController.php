<?php

namespace App\Http\Controllers;

use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Sale;
use App\Models\SaleOffer;
use App\Models\Pack;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Cache;

class MarketplaceController extends FilteredController
{
    public function __invoke()
    {
        $templates = $this->getAllTemplates()->makeHidden(['flow_metadata', 'svg']);

        $stats = $this->getStats();

        return view('marketplace')->with(['templates' => $templates, 'stats' => $stats]);
    }
}
