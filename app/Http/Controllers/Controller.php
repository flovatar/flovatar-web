<?php

namespace App\Http\Controllers;

use App\Models\DustSeries;
use App\Models\FlovatarTemplate;
use App\Models\DustTemplate;
use App\Models\AsteroidTemplate;
use App\Models\Sale;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Cache;
use Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected function paramString(){
        return implode('--', request()->all());
    }


    protected function getTemplates(){
        return Cache::remember('db-templates', now()->addMinutes(60), function() {
            return FlovatarTemplate::whereSeries(1)->orderBy('id', 'asc')->get();
        });
    }

    protected function getDustTemplates($series = 1){
        return Cache::remember('db-dust-templates-'.$series, now()->addSeconds(10), function() use ($series) {
            return DustTemplate::whereNotNull('flow_id')->whereDustSeriesId($series)->orderBy('id', 'asc')->get();
        });
    }

    protected function getAsteroidTemplates($series = 1){
        return Cache::remember('db-asteroid-templates-'.$series, now()->addSeconds(10), function() use ($series) {
            return AsteroidTemplate::whereNotNull('flow_id')->orderBy('id', 'asc')->get();
        });
    }

    protected function getDustSeries($series = 1){
        return Cache::remember('db-dust-series-'.$series, now()->addMinutes(60), function() use ($series) {
            return DustSeries::whereFlowId($series)->orderBy('id', 'asc')->get();
        });
    }


    protected function getAllTemplates(){
        return Cache::remember('db-all-templates', now()->addMinutes(60), function() {
            return FlovatarTemplate::orderBy('id', 'asc')->get()->makeHidden('svg');
        });
    }
    protected function getFlobotTemplates(){
        return Cache::remember('db-flobot-templates', now()->addMinutes(60), function() {
            return FlovatarTemplate::whereSeries(2)->whereNotNull('flow_address')->whereNotNull('flow_id')->orderBy('id', 'asc')->get();
        });
    }


    protected function getStats(){
        return Cache::remember('db-sales-stats', now()->addMinutes(1), function() {

            $dustBurned = Storage::exists('burned-dust.txt') ? Storage::get('burned-dust.txt') : 0;
            $dustBurned = $dustBurned ? round(floatval($dustBurned)) : 0;

            $dustClaimed = Storage::exists('claimed-dust.txt') ? Storage::get('claimed-dust.txt') : 0;
            $dustClaimed = $dustClaimed ? round(floatval($dustClaimed)) : 0;

            return [
                'total' => Sale::selectRaw('sum(price) as total')->first()->total,
                'avg' => Sale::selectRaw('avg(price) as avg')->first()->avg,
                'count' => Sale::selectRaw('count(price) as count')->first()->count,
                'max' => Sale::selectRaw('max(price) as max')->first()->max,
                'dustBurned' => $dustBurned,
                'dustClaimed' => $dustClaimed
            ];
        });
    }
}
