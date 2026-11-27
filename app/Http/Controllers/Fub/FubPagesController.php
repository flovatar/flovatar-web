<?php

namespace App\Http\Controllers\Fub;

use App\Http\Controllers\Controller;
use App\Models\Fub;
use App\Models\FubDraft;
use App\Models\FubDraftItem;
use App\Models\FubItem;
use App\Models\FubReport;
use App\Models\Fun;
use App\Services\Fub\FubChain;
use App\Services\Fub\FubIndexer;
use App\Services\Render\Rasterizer;
use App\Support\Fub as FubSupport;
use App\Support\FubAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * FUB v1 public pages + JSON + the user-SVG content endpoints (KB notes/fub/FUB-BUILD.md §3).
 *
 * User SVG is ONLY served from /fub-content/* (or FUB_CONTENT_ORIGIN) with image/svg+xml, nosniff and a sandbox CSP,
 * and pages reference it only through <img> (scripts can't run in an <img> SVG; the CSP covers direct navigation).
 */
class FubPagesController extends Controller
{
    public function __construct(protected FubChain $chain)
    {
    }

    protected function guard(): void
    {
        abort_unless(FubSupport::enabled(), 404);
    }

    // ---------------------------------------------------------------------------------------------- pages

    /** /fub: the new FUB home (my FUBs + directory) when FUB is visible, else the 2022 page. */
    public function index()
    {
        if (! FubSupport::enabled()) {
            return view('fub');
        }
        $directory = Fub::query()->listed()->orderByDesc('published_at')->limit(60)->get();
        $fresh = Fub::query()->visible()->where('suspended', false)->orderByDesc('published_at')->limit(12)->get();

        return view('fub-v1.index', ['directory' => $directory, 'fresh' => $fresh, 'cfg' => $this->pageConfig()]);
    }

    public function studio(?int $draft = null)
    {
        $this->guard();

        return view('fub-v1.studio', ['draftId' => $draft, 'cfg' => $this->pageConfig()]);
    }

    /** /fub/{id}: the F.U.N. builder of one published FUB (buyers). */
    public function show(int $id)
    {
        $this->guard();
        $fub = Fub::where('flow_id', $id)->first();
        if (! $fub) {
            try {
                $fub = app(FubIndexer::class)->refreshFub($id);
            } catch (Throwable $e) {
                report($e);
            }
        }
        abort_if(! $fub || $fub->status === 'building' || $fub->hidden_at !== null, 404);

        return view('fub-v1.show', ['fub' => $fub, 'cfg' => $this->pageConfig()]);
    }

    public function fun(int $id)
    {
        $this->guard();
        $fun = Fun::where('flow_id', $id)->firstOrFail();
        $fub = Fub::where('flow_id', $fun->fub_flow_id)->firstOrFail();
        abort_if($fub->hidden_at !== null, 404);
        $items = FubItem::whereIn('flow_id', array_values($fun->picks))->get()->keyBy('flow_id');

        return view('fub-v1.fun', ['fun' => $fun, 'fub' => $fub, 'items' => $items, 'cfg' => $this->pageConfig()]);
    }

    protected function pageConfig(): array
    {
        return [
            'contract' => FubSupport::contract(), 'islands' => \App\Support\Islands::contract(),
            'network' => config('fub.network', env('FLOW_NETWORK', 'emulator')),
            'assistantUrl' => (string) config('fub.assistant_url', ''), 'contentBase' => FubSupport::contentUrl(''),
            'tosHash' => self::tosHash(),
        ];
    }

    /** Hash of the creator terms the creator accepts on Publish (stored on chain). */
    public static function tosHash(): string
    {
        return hash('sha3-256', (string) view('fub-v1.partials.terms')->render());
    }

    // ---------------------------------------------------------------------------------------------- JSON

    public function apiConfig()
    {
        $this->guard();
        try {
            $c = $this->chain->config();
        } catch (Throwable $e) {
            report($e);
            abort(503, 'Chain unavailable');
        }

        return response()->json(array_merge($c, ['tosHash' => self::tosHash(), 'assistantUrl' => (string) config('fub.assistant_url', '')]))
            ->header('Cache-Control', 'public, max-age=30');
    }

    public function apiList(Request $request)
    {
        $this->guard();
        $q = Fub::query()->visible();
        if ($c = FubSupport::normalizeAddress($request->query('creator'))) {
            $q->where('creator', $c);
        } else {
            $q->listed();
        }

        return response()->json(['fubs' => $q->orderByDesc('published_at')->limit(200)->get()->map(fn ($f) => $this->fubJson($f, false))]);
    }

    public function apiShow(int $id)
    {
        $this->guard();
        $f = Fub::where('flow_id', $id)->firstOrFail();
        abort_if($f->hidden_at !== null, 404);

        return response()->json($this->fubJson($f, true));
    }

    public function apiRefresh(Request $request, int $id)
    {
        $this->guard();
        $height = null;
        if ($tx = $request->input('tx')) {
            abort_unless(is_string($tx) && preg_match('/^(0x)?[0-9a-f]{64}$/i', $tx), 422);
            try {
                $height = $this->chain->txHeight($tx);
                $this->recordMintsFromTx($tx);
            } catch (Throwable $e) {
                report($e);
            }
        }
        $f = app(FubIndexer::class)->refreshFub($id, $height);
        abort_unless($f, 404);

        return response()->json($this->fubJson($f, true));
    }

    /** Index FunMinted events of a just-sealed mint tx right away (the event indexer catches up anyway). */
    protected function recordMintsFromTx(string $tx): void
    {
        $r = $this->chain->txResult($tx);
        foreach ((array) ($r['events'] ?? []) as $e) {
            if ($e['type'] === FubSupport::typeId('FunMinted')) {
                app(FubIndexer::class)->recordMint((array) $e['values'], strtolower(preg_replace('/^0x/', '', $tx)));
            }
        }
    }

    public function apiFuns(Request $request)
    {
        $this->guard();
        $q = Fun::query()->whereHas('fub', fn ($f) => $f->whereNull('hidden_at'));
        if ($o = FubSupport::normalizeAddress($request->query('owner'))) {
            $q->where('owner', $o);
        } elseif ($fid = (int) $request->query('fub')) {
            $q->where('fub_flow_id', $fid);
        } else {
            abort(422, 'owner or fub required');
        }

        return response()->json(['funs' => $q->orderByDesc('flow_id')->limit(500)->get()->map(fn ($n) => [
            'id' => $n->flow_id, 'fub' => $n->fub_flow_id, 'serial' => $n->serial, 'owner' => $n->owner, 'picks' => $n->picks, 'price' => $n->price,
            'image' => FubSupport::contentUrl('fun/'.$n->flow_id.'.svg'), 'url' => url('/fub/fun/'.$n->flow_id),
        ])]);
    }

    public function fubJson(Fub $f, bool $items): array
    {
        $out = [
            'id' => $f->flow_id, 'name' => $f->name, 'description' => $f->description, 'creator' => $f->creator, 'payout' => $f->payout,
            'status' => $f->status, 'suspended' => $f->suspended, 'tier' => $f->tier, 'slots' => $f->slots, 'mintCap' => $f->mint_cap,
            'maxSupply' => $f->max_supply, 'minted' => $f->minted, 'layers' => $f->layers ?? [], 'royalty' => $f->royalty, 'unique' => $f->unique_combos,
            'license' => $f->license, 'islandId' => $f->island_id, 'items' => $f->items, 'manifest' => $f->manifest, 'pending' => $f->pending,
            'publishedAt' => $f->published_at?->toIso8601String(), 'data' => $f->data, 'url' => url('/fub/'.$f->flow_id),
            'listed' => Fub::query()->listed()->where('flow_id', $f->flow_id)->exists(),
        ];
        if ($items) {
            $out['itemList'] = $f->items()->whereNull('hidden_at')->get()->map(fn ($i) => [
                'id' => $i->flow_id, 'layer' => $i->layer, 'name' => $i->name, 'description' => $i->description, 'rarity' => $i->rarity,
                'price' => $i->price, 'currentPrice' => $i->current_price, 'maxMint' => $i->max_mint, 'minted' => $i->minted, 'increase' => $i->increase,
                'priceCap' => $i->price_cap, 'size' => $i->size, 'state' => $i->state, 'live' => $i->live, 'suspended' => $i->suspended,
                'aiAssisted' => ($i->metadata['aiAssisted'] ?? 'false') === 'true',
                'image' => FubSupport::contentUrl('item/'.$i->flow_id.'.svg?h='.substr((string) $i->hash, 0, 12)),
            ])->values();
        }

        return $out;
    }

    // ---------------------------------------------------------------------------------------------- reports (Q11 b)

    public function report(Request $request)
    {
        $this->guard();
        $v = $request->validate([
            'fub' => ['required', 'integer', 'min:1'], 'item' => ['nullable', 'integer', 'min:1'], 'fun' => ['nullable', 'integer', 'min:1'],
            'reason' => ['required', 'in:stolen-art,offensive,scam,illegal,other'], 'details' => ['nullable', 'string', 'max:1000'],
        ]);
        abort_unless(Fub::where('flow_id', $v['fub'])->exists(), 404);
        FubReport::create(['fub_flow_id' => $v['fub'], 'item_flow_id' => $v['item'] ?? null, 'fun_flow_id' => $v['fun'] ?? null,
            'reporter' => FubAuth::address(), 'reason' => $v['reason'], 'details' => $v['details'] ?? null,
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'))]);

        return response()->json(['ok' => true, 'message' => 'Thanks. The Flovatar team reviews every report.']);
    }

    // ---------------------------------------------------------------------------------------------- user SVG content

    public static function svgResponse(string $svg, int $maxAge = 300)
    {
        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=us-ascii',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src 'none'; sandbox",
            'Cross-Origin-Resource-Policy' => 'same-site',
            'Content-Disposition' => 'inline; filename="fub.svg"',
            'Cache-Control' => $maxAge > 0 ? 'public, max-age='.$maxAge : 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    protected function svgOpen(): string
    {
        try {
            return (string) ($this->chain->config()['svgOpen'] ?: '');
        } catch (Throwable $e) {
            return '';
        }
    }

    public static function frame(string $open, string $inner): string
    {
        $open = $open !== '' ? $open : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3000 3000" width="100%" height="100%">';

        return $open.$inner.'</svg>';
    }

    /** One published item (FUB not hidden, item not hidden; suspended -> placeholder). */
    public function itemSvg(int $id)
    {
        $this->guard();
        $it = FubItem::where('flow_id', $id)->firstOrFail();
        $f = Fub::where('flow_id', $it->fub_flow_id)->firstOrFail();
        abort_if($f->hidden_at !== null || $it->hidden_at !== null, 404);
        $inner = ($f->suspended || $it->suspended) ? '' : (string) $it->svg;

        return self::svgResponse(self::frame($this->svgOpen(), '<g id="fun-l0" data-item="'.$it->flow_id.'">'.$inner.'</g>'), $f->status === 'building' ? 0 : 3600);
    }

    /** Composition of a published FUB from picked item ids (?i=1,2,3), same order/markup as the contract's composeSvg. */
    public function composeSvg(Request $request, int $fubId)
    {
        $this->guard();
        $f = Fub::where('flow_id', $fubId)->firstOrFail();
        abort_if($f->hidden_at !== null || $f->status === 'building', 404);
        $ids = array_slice(array_filter(array_map('intval', explode(',', (string) $request->query('i', '')))), 0, 16);
        $items = FubItem::where('fub_flow_id', $fubId)->whereIn('flow_id', $ids)->whereNull('hidden_at')->get()->keyBy('layer');

        return self::svgResponse($this->compose($f, $items, fn ($it) => $it->live), 600);
    }

    /** Composition for a minted F.U.N. */
    public function funSvg(int $id)
    {
        $this->guard();
        [$svg] = $this->funComposition($id);

        return self::svgResponse($svg, 3600);
    }

    protected function funComposition(int $id): array
    {
        $fun = Fun::where('flow_id', $id)->firstOrFail();
        $f = Fub::where('flow_id', $fun->fub_flow_id)->firstOrFail();
        abort_if($f->hidden_at !== null, 404);
        $items = FubItem::where('fub_flow_id', $f->flow_id)->whereIn('flow_id', array_values($fun->picks))->get()->keyBy('layer');

        return [$this->compose($f, $items, fn () => true), $fun, $f];
    }

    protected function compose(Fub $f, $byLayer, callable $ok): string
    {
        $parts = '';
        if (! $f->suspended) {
            foreach (array_values($f->layers ?? []) as $i => $l) {
                $it = $byLayer[$l['key']] ?? null;
                if ($it && $ok($it)) {
                    $parts .= '<g id="fun-l'.$i.'" data-item="'.$it->flow_id.'">'.(($it->suspended || $it->hidden_at) ? '' : (string) $it->svg).'</g>';
                }
            }
        }

        return self::frame($this->svgOpen(), $parts);
    }

    /** Thumbnail PNG (MetadataViews.Display thumbnail URL on chain: /fub/fun/{id}.png), rendered once with resvg. */
    public function funPng(int $id)
    {
        $this->guard();
        [$svg, $fun] = $this->funComposition($id);
        $path = 'fub/fun/'.$id.'-'.substr(hash('sha256', $svg), 0, 12).'.png';
        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            try {
                $png = app(Rasterizer::class)->png((new \App\Services\Render\SvgFlattener())->flatten($svg), 1000, 1000);
            } catch (Throwable $e) {
                report($e);

                return redirect(FubSupport::contentUrl('fun/'.$id.'.svg'));
            }
            $disk->put($path, $png);
        }

        return response($disk->get($path), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** Owner-only draft previews (session): one item, or a composition of draft item ids (?i=). Never cached publicly. */
    public function draftItemSvg(int $id)
    {
        $this->guard();
        $it = FubDraftItem::findOrFail($id);
        $d = FubDraft::findOrFail($it->fub_draft_id);
        abort_unless($d->isOwnedBy(FubAuth::address()), 404);

        return self::svgResponse(self::frame($this->svgOpen(), '<g id="fun-l0">'.$it->svg.'</g>'), 0);
    }

    public function draftComposeSvg(Request $request, int $draftId)
    {
        $this->guard();
        $d = FubDraft::findOrFail($draftId);
        abort_unless($d->isOwnedBy(FubAuth::address()), 404);
        $ids = array_slice(array_filter(array_map('intval', explode(',', (string) $request->query('i', '')))), 0, 16);
        $items = $d->items()->whereIn('id', $ids)->where('chain_state', '!=', 'deleted')->get()->keyBy('layer_key');
        $parts = '';
        foreach ($d->layers()->get()->values() as $i => $l) {
            if ($it = $items[$l->key] ?? null) {
                $parts .= '<g id="fun-l'.$i.'" data-item="d'.$it->id.'">'.$it->svg.'</g>';
            }
        }

        return self::svgResponse(self::frame($this->svgOpen(), $parts), 0);
    }
}
