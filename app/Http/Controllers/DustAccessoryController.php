<?php

namespace App\Http\Controllers;

use App\Models\DustAccessory;
use App\Models\DustTemplate;
use App\Models\Sale;
use App\Models\SaleOffer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DustAccessoryController extends Controller
{
    public function __invoke($accessoryId, $address)
    {

        $templates = Cache::remember('db-templates-dust-accessory-'.$accessoryId, now()->addMinutes(60), function() use ($accessoryId) {
            return DustTemplate::whereHas('dustAccessories', function (Builder $query) use ($accessoryId) {
                $query->whereFlowId($accessoryId);
            })->orderBy('id', 'asc')->get();
        });

        // unknown accessory (dust_accessories is empty on the relaunch DB): 404 instead of a 500 on null->id
        abort_if($templates->isEmpty(), 404);
        $templateId = $templates->first()->id;

        $sales = Cache::remember('flovatar-dust-accessory-sales-'.$templateId, now()->addMinutes(60), function() use ($templateId) {
            $components = DustAccessory::select('id')->whereDustTemplateId($templateId)->get()->flatten();
            $sales = Sale::notBlocked()->select(
                [
                    DB::raw('DATE_FORMAT(created_at, "%c/%d/%Y") as date'),
                    DB::raw('MAX(created_at) as maxdate'),
                    DB::raw('SUM(price) as total'),
                    DB::raw('COUNT(price) as count'),
                    DB::raw('AVG(price) as avg'),
                    DB::raw('MIN(price) as min'),
                    DB::raw('MAX(price) as max')
                ])
                ->whereIn('item_id', $components)
                ->where('item_type', 'App\Models\DustAccessory')
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
        $saleoffers = Cache::remember('flovatar-dust-accessory-saleoffers-'.$templateId, now()->addMinutes(60), function() use ($templateId) {
            $components = DustAccessory::select('id')->whereDustTemplateId($templateId)->get()->flatten();
            $saleoffers = SaleOffer::notBlocked()->select(
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
                ->where('item_type', 'App\Models\DustAccessory')
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


        $saleOffer = Cache::remember('flovatar-dust-accessory-sale-offer-'.$accessoryId, now()->addMinutes(1), function() use ($accessoryId) {
            $component = DustAccessory::where('flow_id', $accessoryId)->first();
            return $component ? $component->saleOffers()->where('is_completed', false)->where('is_dapper', true)->orderBy('created_at', 'desc')->first() : null;
        });

        return view('dustaccessory')->with(['templates' => $templates, 'address' => $address, 'accessoryId' => $accessoryId, 'componentSales' => $sales, 'accessorySaleOffers' => $saleoffers, 'accessorySaleOffer' => $saleOffer]);
    }
}
