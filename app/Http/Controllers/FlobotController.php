<?php

namespace App\Http\Controllers;

use App\Models\Flobot;
use Cache;

class FlobotController extends Controller
{
    public function __invoke($flobotId, $address)
    {

        $templates = $this->getFlobotTemplates();
        $sales = Cache::remember('flobot-sales-'.$flobotId, now()->addMinutes(10), function() use ($flobotId) {
            $flobot = Flobot::where('flow_id', $flobotId)->first();
            return $flobot ? $flobot->sales()->orderBy('created_at', 'asc')->get() : [];
        });
        $saleOffer = Cache::remember('flobot-sale-offers-'.$flobotId, now()->addMinutes(1), function() use ($flobotId) {
            $flobot = Flobot::where('flow_id', $flobotId)->first();
            return $flobot ? $flobot->saleOffers()->where('is_completed', false)->orderBy('created_at', 'desc')->first() : null;
        });
        return view('flobot')->with(['templates' => $templates, 'address' => $address, 'flobotId' => $flobotId, 'flobotSales' => $sales, 'flobotSaleOffer' => $saleOffer]);
    }

}
