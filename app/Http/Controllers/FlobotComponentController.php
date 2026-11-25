<?php

namespace App\Http\Controllers;

use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Sale;
use App\Models\SaleOffer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Cache;

class FlobotComponentController extends Controller
{
    public function __invoke($componentId, $address)
    {

        $templates = Cache::remember('db-templates-flobotcomponent-'.$componentId, now()->addMinutes(60), function() use ($componentId) {
            return FlovatarTemplate::whereHas('flobotComponents', function (Builder $query) use ($componentId) {
                $query->whereFlowId($componentId);
            })->whereSeries(2)->orderBy('id', 'asc')->get();
        });

        $templateId = $templates ? $templates->first()->id : 0;

        $sales = Cache::remember('flobot-component-sales-'.$templateId, now()->addMinutes(60), function() use ($templateId) {
            $components = FlovatarComponent::select('id')->whereFlovatarTemplateId($templateId)->get()->flatten();
            $sales = Sale::notBlocked()->notDust()->select(
                [
                    DB::raw('DATE_FORMAT(created_at, "%c/%d") as date'),
                    DB::raw('MAX(created_at) as maxdate'),
                    DB::raw('SUM(price) as total'),
                    DB::raw('COUNT(price) as count'),
                    DB::raw('AVG(price) as avg'),
                    DB::raw('MIN(price) as min'),
                    DB::raw('MAX(price) as max')
                ])
                ->whereIn('item_id', $components)
                ->where('item_type', 'App\Models\FlovatarComponent')
                ->groupBy('date')
                ->orderBy('maxdate')
                ->get();

            return [
                'date' => $sales->pluck('date'),
                'total' => $sales->pluck('total'),
                'count' => $sales->pluck('count'),
                'avg' => $sales->pluck('avg'),
                'min' => $sales->pluck('min'),
                'max' => $sales->pluck('max')
            ];
        });
        $saleoffers = Cache::remember('flobot-component-saleoffers-'.$templateId, now()->addMinutes(60), function() use ($templateId) {
            $components = FlovatarComponent::select('id')->whereFlovatarTemplateId($templateId)->get()->flatten();
            $saleoffers = SaleOffer::notBlocked()->notDust()->select(
                [
                    DB::raw('DATE_FORMAT(created_at, "%c/%d") as date'),
                    DB::raw('MAX(created_at) as maxdate'),
                    DB::raw('SUM(price) as total'),
                    DB::raw('COUNT(price) as count'),
                    DB::raw('AVG(price) as avg'),
                    DB::raw('MIN(price) as min'),
                    DB::raw('MAX(price) as max')
                ])
                ->whereIn('item_id', $components)
                ->where('item_type', 'App\Models\FlovatarComponent')
                ->groupBy('date')
                ->orderBy('maxdate')
                ->get();

            return [
                'date' => $saleoffers->pluck('date'),
                'total' => $saleoffers->pluck('total'),
                'count' => $saleoffers->pluck('count'),
                'avg' => $saleoffers->pluck('avg'),
                'min' => $saleoffers->pluck('min'),
                'max' => $saleoffers->pluck('max')
            ];
        });


        $saleOffer = Cache::remember('flobot-component-sale-offer-'.$componentId, now()->addMinutes(1), function() use ($componentId) {
            $component = FlovatarComponent::where('flow_id', $componentId)->first();
            return $component ? $component->saleOffers()->where('is_completed', false)->where('is_dapper', true)->orderBy('created_at', 'desc')->first() : null;
        });

        return view('flovatarcomponent')->with(['templates' => $templates, 'address' => $address, 'componentId' => $componentId, 'componentSales' => $sales, 'componentSaleOffers' => $saleoffers, 'componentSaleOffer' => $saleOffer]);
    }
}
