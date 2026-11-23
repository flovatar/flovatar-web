<?php

namespace App\Http\Controllers;

use App\Models\Flobot;
use Cache;

class FlobotController extends Controller
{
    public function __invoke($flobotId, $address)
    {

        // Template SVGs come from a separate cacheable script (TemplateDataController)
        $templatesScript = $this->templatesScriptUrl('flobot');
        $sales = Cache::remember('flobot-sales-'.$flobotId, now()->addMinutes(10), function() use ($flobotId) {
            $flobot = Flobot::where('flow_id', $flobotId)->first();
            return $flobot ? $flobot->sales()->orderBy('created_at', 'asc')->get() : [];
        });
        $saleOffer = Cache::remember('flobot-sale-offers-'.$flobotId, now()->addMinutes(1), function() use ($flobotId) {
            $flobot = Flobot::where('flow_id', $flobotId)->first();
            return $flobot ? $flobot->saleOffers()->where('is_completed', false)->orderBy('created_at', 'desc')->first() : null;
        });
        return view('flobot')->with(['templatesScript' => $templatesScript, 'address' => $address, 'flobotId' => $flobotId, 'flobotSales' => $sales, 'flobotSaleOffer' => $saleOffer]);
    }

}
