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

    /**
     * Serve an exported image (gif/png/mp4). Default: redirect to the public CDN
     * (images.flovatar.com), which needs no bucket credentials on the web server.
     * FLOVATAR_IMAGE_PROXY=download restores the old behaviour (stream from the s3 disk).
     */
    protected function imageFromStorage(string $path){
        // testnet: legacy exports on the prod CDN belong to other (mainnet) items with the same id
        if(!config('render.legacy_fallback')){
            abort(404);
        }
        if(config('flovatar.imageProxy') === 'download'){
            return Storage::disk('s3')->download($path);
        }
        return redirect()->away(rtrim((string) config('flovatar.imagesUrl'), '/').'/'.ltrim($path, '/'), 302)
            ->header('Cache-Control', 'public, max-age=300');
    }

    protected function paramString(){
        return implode('--', request()->all());
    }


    // flovatar_templates columns without the svg / flow_metadata blobs (~32 MB for series 1)
    public const TEMPLATE_LEAN_COLUMNS = ['id', 'name', 'category', 'color', 'series', 'rarity', 'description', 'max_mintable', 'burned', 'flow_id', 'flow_address', 'created_at', 'updated_at'];

    /** Template sets served as /templates/data/{set}.js (route constraint in routes/web.php). */
    public const TEMPLATE_DATA_SETS = ['series1', 'flobot', 'dust', 'dust2'];

    /** dust => DUST series 1, dust2 => DUST series 2; null for Flovatar/Flobot sets. */
    public static function dustSeriesOfSet(string $set){
        return $set === 'dust' ? 1 : ($set === 'dust2' ? 2 : null);
    }

    public static function templateQuery(string $set){
        if(($dustSeries = static::dustSeriesOfSet($set)) !== null){
            // same rows and order as getDustTemplates() (the old inline window.templates of /builder/dust)
            return DustTemplate::query()->whereNotNull('flow_id')->whereDustSeriesId($dustSeries)->orderBy('id', 'asc');
        }
        $query = FlovatarTemplate::query()->orderBy('id', 'asc');
        if($set === 'series1'){
            $query->whereSeries(1);
        } else if($set === 'flobot'){
            $query->whereSeries(2)->whereNotNull('flow_address')->whereNotNull('flow_id');
        }
        return $query;
    }

    /**
     * Template list without svg/flow_metadata (pages that only need names, categories,
     * rarities). Plain arrays, cached 60 s (served stale up to 1 h while refreshing).
     * Sets: all | series1 | flobot.
     */
    protected function templatesLean(string $set){
        return Cache::flexible('tpl-lean-v1-'.$set, [60, 3600], function() use ($set) {
            return static::templateQuery($set)->select(self::TEMPLATE_LEAN_COLUMNS)->get()->toArray();
        });
    }

    /** Version of a template set (changes when templates are added/updated/burned). */
    public static function templatesVersion(string $set){
        return Cache::flexible('tpl-version-v1-'.$set, [60, 600], function() use ($set) {
            $columns = static::dustSeriesOfSet($set) !== null
                // dust templates: minted/prices change while a series is live; crc32 catches svg edits
                ? 'count(*) c, max(id) i, max(updated_at) u, coalesce(sum(minted),0) mi, coalesce(sum(max_mintable),0) m, coalesce(sum(current_price),0) p, coalesce(sum(base_price),0) bp, coalesce(sum(crc32(svg)),0) s'
                : 'count(*) c, max(id) i, max(updated_at) u, coalesce(sum(burned),0) b, coalesce(sum(max_mintable),0) m';
            $row = static::templateQuery($set)->reorder()->selectRaw($columns)->toBase()->first();
            return substr(md5(json_encode($row)), 0, 12);
        });
    }

    /**
     * JS that sets window.templates (with svg, without flow_metadata) for pages that draw
     * Flovatars client side. Served by TemplateDataController as a separate, versioned,
     * long-cacheable script instead of being inlined in every page (was ~37 MB per page).
     */
    public static function templatesScript(string $set){
        $version = static::templatesVersion($set);
        return Cache::flexible('tpl-script-v1-'.$set.'-'.$version, [3600, 86400], function() use ($set) {
            $templates = static::templateQuery($set)->get()->makeHidden(['flow_metadata'])->toArray();
            return 'window.templates = '.json_encode($templates, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT).';';
        });
    }

    protected function templatesScriptUrl(string $set){
        return route('templates.data', ['set' => $set, 'v' => static::templatesVersion($set)], false);
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

    /** Dust templates without svg/flow_metadata for list pages (collection, showroom, collectible page). */
    protected function getDustTemplatesLean($series = 1){
        return Cache::flexible('db-dust-templates-lean-v1-'.$series, [10, 120], function() use ($series) {
            return DustTemplate::whereNotNull('flow_id')->whereDustSeriesId($series)->orderBy('id', 'asc')->get()->makeHidden(['flow_metadata', 'svg'])->toArray();
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
        // One aggregate scan instead of four; refreshed in the background (flexible) so no
        // visitor waits for it: fresh for 60 s, served stale up to 10 min while refreshing.
        return Cache::flexible('db-sales-stats', [60, 600], function() {

            $dustBurned = Storage::exists('burned-dust.txt') ? Storage::get('burned-dust.txt') : 0;
            $dustBurned = $dustBurned ? round(floatval($dustBurned)) : 0;

            $dustClaimed = Storage::exists('claimed-dust.txt') ? Storage::get('claimed-dust.txt') : 0;
            $dustClaimed = $dustClaimed ? round(floatval($dustClaimed)) : 0;

            $agg = Sale::notBlocked()->notDust()->selectRaw('sum(price) as total, avg(price) as avg, count(price) as count, max(price) as max')->first();

            return [
                'total' => $agg->total,
                'avg' => $agg->avg,
                'count' => $agg->count,
                'max' => $agg->max,
                'dustBurned' => $dustBurned,
                'dustClaimed' => $dustClaimed
            ];
        });
    }
}
