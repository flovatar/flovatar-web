<?php

namespace App\Http\Controllers;

use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Pack;
use App\Models\Sale;
use Illuminate\Http\Request;
use Cache;

class FlovatarController extends Controller
{
    public function __invoke($flovatarId, $address)
    {

        $templates = $this->getTemplates();
        $sales = Cache::remember('flovatar-sales-'.$flovatarId, now()->addMinutes(10), function() use ($flovatarId) {
            $flovatar = Flovatar::where('flow_id', $flovatarId)->first();
            return $flovatar ? $flovatar->sales()->orderBy('created_at', 'asc')->get() : [];
        });
        $saleOffer = Cache::remember('flovatar-sale-offers-'.$flovatarId, now()->addMinutes(1), function() use ($flovatarId) {
            $flovatar = Flovatar::where('flow_id', $flovatarId)->first();
            return $flovatar ? $flovatar->saleOffers()->where('is_completed', false)->where('is_dapper', true)->orderBy('created_at', 'desc')->first() : null;
        });
        return view('flovatar')->with(['templates' => $templates, 'address' => $address, 'flovatarId' => $flovatarId, 'flovatarSales' => $sales, 'flovatarSaleOffer' => $saleOffer]);
    }
}
