<?php

namespace App\Http\Controllers;

use App\Models\DustCollectible;
use App\Models\DustTemplate;
use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Flobot;
use App\Models\SaleOffer;
use App\Models\Sale;
use App\Models\Pack;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Storage;
use Cache;

class FilteredController extends Controller
{
    // List columns: everything except the svg blob; flow_metadata without metadata.svg (~27 KB each).
    // The web pages show images via /api/image/* and never read svg / full_svg / full_svg_no_bg.
    public const FLOVATAR_LIST_COLUMNS = ['id', 'name', 'series', 'flow_id', 'rare_count', 'epic_count', 'legendary_count', 'flow_address', 'created_at', 'updated_at'];
    public const FLOBOT_LIST_COLUMNS = ['id', 'name', 'series', 'flow_id', 'rarity', 'flow_address', 'flow_metadata', 'created_at', 'updated_at'];
    public const HEAVY_APPENDS = ['svg', 'full_svg', 'full_svg_no_bg'];

    protected function leanItemConstraints(){
        return function (MorphTo $morphTo) {
            $morphTo->constrain([
                Flovatar::class => function ($query) {
                    $query->select(self::FLOVATAR_LIST_COLUMNS)
                        ->selectRaw("JSON_REMOVE(flow_metadata, '$.metadata.svg') as flow_metadata");
                },
                Flobot::class => function ($query) {
                    $query->select(self::FLOBOT_LIST_COLUMNS);
                },
            ]);
        };
    }

    protected function hideHeavyItemAppends($results){
        foreach($results->getCollection() as $row){
            if($row->relationLoaded('item') && $row->item && ($row->item instanceof Flovatar || $row->item instanceof Flobot)){
                $row->item->makeHidden(self::HEAVY_APPENDS);
            }
        }
        return $results;
    }

    /** $lean: web pages (no svg blobs); the JSON API keeps its full output. */
    protected function getFilteredSales(Request $req, $lean = false){
        $salesQuery = SaleOffer::query()->notBlocked();

        if(!$req->get('nosvg')){
            $salesQuery->with($lean ? ['item' => $this->leanItemConstraints()] : 'item');
        }

        $results = $this->runFilterQuery($req, $salesQuery)->paginate(20);

        if($req->get('nosvg')) {
            $results->makeHidden(['flow_metadata']);
        } else if($lean) {
            $this->hideHeavyItemAppends($results);
        }
        return $results;
    }


    protected function getFilteredTransactions(Request $req, $lean = false){
        $salesQuery = Sale::query()->notBlocked();

        if($req->get('nosvg')){
            $salesQuery->with(['item' => function($query) {
                return $query->select(['id', 'flow_id','name', 'rare_count', 'epic_count', 'legendary_count', 'flow_address']);
            }]);
        } else {
            $salesQuery->with($lean ? ['item' => $this->leanItemConstraints()] : 'item');
        }

        $results = $this->runFilterQuery($req, $salesQuery)->paginate(50);

        if($lean && !$req->get('nosvg')){
            $this->hideHeavyItemAppends($results);
        }
        return $results;
    }



    protected function runFilterQuery(Request $req, $salesQuery){


        if($req->get('rarity') && $req->get('rarity') != 'all'){
            $templatesRarity = FlovatarTemplate::select('id')->whereRarity($req->get('rarity'))->get()->flatten();
            $salesQuery->whereHasMorph('item', [Flovatar::class, FlovatarComponent::class], function (Builder $query, $type) use($req, $templatesRarity){
                if($type === Flovatar::class){
                    if($req->get('rarity') == 'common'){
                        $query->where('rare_count', '=', 0)->where('epic_count', '=', 0)->where('legendary_count', '=', 0);
                    } else if($req->get('rarity') == 'rare'){
                        $query->where('rare_count', '>', 0)->where('epic_count', '=', 0)->where('legendary_count', '=', 0);
                    } else if($req->get('rarity') == 'epic'){
                        $query->where('epic_count', '>', 0)->where('legendary_count', '=', 0);
                    } else if($req->get('rarity') == 'legendary'){
                        $query->where('legendary_count', '>', 0);
                    }
                } else if($type === FlovatarComponent::class){
                    $query->whereIn('flovatar_template_id', $templatesRarity);
                } else if($type === Flobot::class){
                    $query->where('rarity', $req->get('rarity'));
                }
            });
        }


        if($req->get('templatecat') && $req->get('templatecat') != 'all'){
            if($req->get('templatecat') == 'flovatar'){
                $salesQuery->whereItemType(Flovatar::class);
            } else if($req->get('templatecat') == 'flobot'){
                $salesQuery->whereItemType(Flobot::class);
            } else if($req->get('templatecat') == 'collectible'){
                $salesQuery->whereItemType(DustCollectible::class);
            } else {
                $salesQuery->whereItemType(FlovatarComponent::class);
            }
        }


        if($req->get('dapper') == 'true'){
            $salesQuery->where('is_dapper', true);
        } else {
            $salesQuery->where('is_dapper', false);
        }

        if($req->get('pricemin')){
            $salesQuery->where('price', '>=', intval($req->get('pricemin')));
        }
        if($req->get('pricemax')){
            $salesQuery->where('price', '<=', intval($req->get('pricemax')));
        }



        if($req->get('flobitcat') && $req->get('flobitcat') != 'all' && $req->get('templatecat') == 'flobits'){
            $templates = FlovatarTemplate::select('id')->whereCategory($req->get('flobitcat'))->get()->flatten();

            $salesQuery->whereHasMorph('item', [FlovatarComponent::class], function (Builder $query, $type) use($templates){
                $query->whereIn('flovatar_template_id', $templates);
            });
        } else if($req->get('templatecat') && $req->get('templatecat') != 'all' && $req->get('templatecat') != 'flovatar' && $req->get('templatecat') != 'flobot' && $req->get('templatecat') != 'collectible'){
            if($req->get('templatecat') == 'boost' || $req->get('templatecat') == 'spark' || $req->get('templatecat') == 'land' || $req->get('templatecat') == 'flobotkit' || $req->get('templatecat') == 'fub') {
                $templates = FlovatarTemplate::select('id')->whereCategory($req->get('templatecat'))->get()->flatten();
            } else {
                $templates = FlovatarTemplate::select('id')->where('category', 'NOT LIKE', 'boost')->where('category','NOT LIKE', 'spark')->where('category', 'NOT LIKE', 'land')->where('category', 'NOT LIKE', 'flobotkit')->where('category', 'NOT LIKE', 'fub')->get()->flatten();
            }
            $salesQuery->whereHasMorph('item', [Flovatar::class, FlovatarComponent::class], function (Builder $query, $type) use($templates){
                if($type === FlovatarComponent::class){
                    $query->whereIn('flovatar_template_id', $templates);
                }
            });
        }

        if($req->get('templateid') && $req->get('templateid') != 'all'){
            $templates = FlovatarTemplate::select('id')->whereFlowId($req->get('templateid'))->get()->flatten();
            $salesQuery->whereHasMorph('item', [FlovatarComponent::class], function (Builder $query, $type) use($templates){
                $query->whereIn('flovatar_template_id', $templates);
            });
        }
        if($req->get('flovatar_id')){
            $salesQuery->whereHasMorph('item', [Flovatar::class], function (Builder $query, $type) use($req){
                $query->where('flow_id', $req->get('flovatar_id'));
            });
        }
        if($req->get('flobot_id')){
            $salesQuery->whereHasMorph('item', [Flobot::class], function (Builder $query, $type) use($req){
                $query->where('flow_id', $req->get('flobot_id'));
            });
        }
        if($req->get('collectible_id')){
            $salesQuery->whereHasMorph('item', [DustCollectible::class], function (Builder $query, $type) use($req){
                $query->where('flow_id', $req->get('collectible_id'));
            });
        }
        if($req->get('dust_template_id')){
            $salesQuery->whereHasMorph('item', [DustTemplate::class], function (Builder $query, $type) use($req){
                $query->where('flow_id', $req->get('dust_template_id'));
            });
        }

        if($req->get('address') && $req->get('address') != ''){
            $salesQuery->where('flow_buyer_address', $req->get('address'))->orWhere('flow_seller_address', $req->get('address'));
        }

        if($req->get('datemin') && $req->get('datemin') != ''){
            $salesQuery->where('created_at', '>=', Carbon::parse($req->get('datemin'))->startOfDay());
        }
        if($req->get('datemax') && $req->get('datemax') != ''){
            $salesQuery->where('created_at', '<=', Carbon::parse($req->get('datemax'))->endOfDay());
        }


        if($req->get('sortby')){
            if($req->get('sortby') == 'datenew'){
                $salesQuery->orderBy('created_at', 'desc');
            } else if($req->get('sortby') == 'dateold'){
                $salesQuery->orderBy('created_at', 'asc');
            } else if($req->get('sortby') == 'pricelow'){
                $salesQuery->orderBy('price', 'asc');
            } else if($req->get('sortby') == 'pricehigh'){
                $salesQuery->orderBy('price', 'desc');
            }
        } else {
            $salesQuery->orderBy('created_at', 'desc');
        }

        return $salesQuery;
    }



    protected function getFilteredFlovatars(Request $req, $lean = false){
        $flovatarsQuery = Flovatar::with('saleOffers');
        if($lean){
            $flovatarsQuery->select(self::FLOVATAR_LIST_COLUMNS)
                ->selectRaw("JSON_REMOVE(flow_metadata, '$.metadata.svg') as flow_metadata");
        }



        if($req->get('rarity') && $req->get('rarity') != 'all'){
            if($req->get('rarity') == 'common'){
                $flovatarsQuery->where('rare_count', '=', 0)->where('epic_count', '=', 0)->where('legendary_count', '=', 0);
            } else if($req->get('rarity') == 'rare'){
                $flovatarsQuery->where('rare_count', '>', 0)->where('epic_count', '=', 0)->where('legendary_count', '=', 0);
            } else if($req->get('rarity') == 'epic'){
                $flovatarsQuery->where('epic_count', '>', 0)->where('legendary_count', '=', 0);
            } else if($req->get('rarity') == 'legendary'){
                $flovatarsQuery->where('legendary_count', '>', 0);
            }
        }

        if($req->get('mintmin')){
            $flovatarsQuery->where('flow_id', '>=', intval($req->get('mintmin')));
        }
        if($req->get('mintmax')){
            $flovatarsQuery->where('flow_id', '<=', intval($req->get('mintmax')));
        }

        if($req->get('template') && $req->get('template') != 'all' && $req->get('category') && $req->get('category') != 'all'){
            $flovatarsQuery->where('flow_metadata->metadata->components->'.$req->get('category'), intval($req->get('template')));
        }

        if($req->get('accessoryId')){
            $flovatarsQuery->where('flow_metadata->accessoryId', intval($req->get('accessoryId')));
        }
        if($req->get('hatId')){
            $flovatarsQuery->where('flow_metadata->hatId', intval($req->get('hatId')));
        }
        if($req->get('eyeglassesId')){
            $flovatarsQuery->where('flow_metadata->eyeglassesId', intval($req->get('eyeglassesId')));
        }
        if($req->get('backgroundId')){
            $flovatarsQuery->where('flow_metadata->backgroundId', intval($req->get('backgroundId')));
        }

        if($req->get('sortby')){
            if($req->get('sortby') == 'datenew'){
                $flovatarsQuery->orderBy('flow_id', 'desc');
            } else if($req->get('sortby') == 'dateold'){
                $flovatarsQuery->orderBy('flow_id', 'asc');
            } else if($req->get('sortby') == 'raritylow'){
                $flovatarsQuery->orderBy('legendary_count', 'asc')->orderBy('epic_count', 'asc')->orderBy('rare_count', 'asc')->orderBy('flow_id', 'desc');
            } else if($req->get('sortby') == 'rarityhigh'){
                $flovatarsQuery->orderBy('legendary_count', 'desc')->orderBy('epic_count', 'desc')->orderBy('rare_count', 'desc')->orderBy('flow_id', 'asc');
            } else {
                $flovatarsQuery->orderBy('flow_id', 'desc');
            }
        } else {
            $flovatarsQuery->orderBy('flow_id', 'desc');
        }


        $flovatars = $flovatarsQuery->paginate(20);

        if($lean){
            $flovatars->getCollection()->each->makeHidden(self::HEAVY_APPENDS);
        }

        return $flovatars;
    }



    protected function getFilteredFlobots(Request $req, $lean = false){
        $flobotsQuery = Flobot::with('saleOffers');
        if($lean){
            $flobotsQuery->select(self::FLOBOT_LIST_COLUMNS);
        }



        if($req->get('rarity') && $req->get('rarity') != 'all'){
            $flobotsQuery->where('rarity', $req->get('rarity'));
        }



        if($req->get('mintmin')){
            $flobotsQuery->where('flow_id', '>=', intval($req->get('mintmin')));
        }
        if($req->get('mintmax')){
            $flobotsQuery->where('flow_id', '<=', intval($req->get('mintmax')));
        }

        if($req->get('template') && $req->get('template') != 'all' && $req->get('category') && $req->get('category') != 'all'){
            $flobotsQuery->where('flow_metadata->metadata->components->'.$req->get('category'), intval($req->get('template')));
        }

        if($req->get('backgroundId')){
            $flobotsQuery->where('flow_metadata->backgroundId', intval($req->get('backgroundId')));
        }

        if($req->get('sortby')){
            if($req->get('sortby') == 'datenew'){
                $flobotsQuery->orderBy('flow_id', 'desc');
            } else if($req->get('sortby') == 'dateold'){
                $flobotsQuery->orderBy('flow_id', 'asc');
            } else if($req->get('sortby') == 'raritylow'){
                $flobotsQuery->orderBy('legendary_count', 'asc')->orderBy('epic_count', 'asc')->orderBy('rare_count', 'asc')->orderBy('flow_id', 'desc');
            } else if($req->get('sortby') == 'rarityhigh'){
                $flobotsQuery->orderBy('legendary_count', 'desc')->orderBy('epic_count', 'desc')->orderBy('rare_count', 'desc')->orderBy('flow_id', 'asc');
            } else {
                $flobotsQuery->orderBy('flow_id', 'desc');
            }
        } else {
            $flobotsQuery->orderBy('flow_id', 'desc');
        }


        $flobots = $flobotsQuery->paginate(20);

        if($lean){
            $flobots->getCollection()->each->makeHidden(self::HEAVY_APPENDS);
        }

        return $flobots;
    }



    protected function getSalesByTemplateId($templateId, $quantity = 1){

        $salesQuery = SaleOffer::with('item')->notBlocked();
        $salesQuery->whereItemType(FlovatarComponent::class)->where('is_dapper', false);

        $templates = FlovatarTemplate::select('id')->whereFlowId($templateId)->get()->flatten();
        $salesQuery->whereHasMorph('item', [FlovatarComponent::class], function (Builder $query, $type) use($templates){
            $query->whereIn('flovatar_template_id', $templates);
        });

        $salesQuery->orderBy('price', 'asc')->orderBy('created_at', 'asc');

        if($quantity > 4){
            $quantity = 4;
        }
        return $salesQuery->take($quantity)->get();
    }





    public const COLLECTIBLE_LIST_COLUMNS = ['id', 'mint', 'name', 'dust_series_id', 'rare_count', 'epic_count', 'legendary_count', 'rarity_score', 'flow_id', 'flow_address', 'created_at', 'updated_at'];

    /** $lean (API v2): no svg blob / full_svg append; flow_metadata without its svg. */
    protected function getFilteredCollectibles(Request $req, $lean = false){
        $collectibleQuery = DustCollectible::with('saleOffers');
        if($lean){
            $collectibleQuery->select(self::COLLECTIBLE_LIST_COLUMNS)
                ->selectRaw("JSON_REMOVE(flow_metadata, '$.svg') as flow_metadata");
        }


/*
        if($req->get('rarity') && $req->get('rarity') != 'all'){
            $collectibleQuery->where('rarity', $req->get('rarity'));
        }
*/


        if($req->get('mintmin')){
            $collectibleQuery->where('flow_id', '>=', intval($req->get('mintmin')));
        }
        if($req->get('mintmax')){
            $collectibleQuery->where('flow_id', '<=', intval($req->get('mintmax')));
        }

        if($req->get('template') && $req->get('template') != 'all' && $req->get('layer') && $req->get('layer') != 'all'){
            $collectibleQuery->where('flow_metadata->metadata->layers->'.$req->get('layer'), intval($req->get('template')));
        }


        if($req->get('sortby')){
            if($req->get('sortby') == 'datenew'){
                $collectibleQuery->orderBy('flow_id', 'desc');
            } else if($req->get('sortby') == 'dateold'){
                $collectibleQuery->orderBy('flow_id', 'asc');
                /*
            } else if($req->get('sortby') == 'raritylow'){
                $collectibleQuery->orderBy('legendary_count', 'asc')->orderBy('epic_count', 'asc')->orderBy('rare_count', 'asc')->orderBy('flow_id', 'desc');
            } else if($req->get('sortby') == 'rarityhigh'){
                $collectibleQuery->orderBy('legendary_count', 'desc')->orderBy('epic_count', 'desc')->orderBy('rare_count', 'desc')->orderBy('flow_id', 'asc');
            */
            } else {
                $collectibleQuery->orderBy('flow_id', 'desc');
            }
        } else {
            $collectibleQuery->orderBy('flow_id', 'desc');
        }


        $collectibles = $collectibleQuery->paginate(20);

        if($lean){
            $collectibles->getCollection()->each->makeHidden(['svg', 'full_svg']);
        }

        return $collectibles;
    }




}
