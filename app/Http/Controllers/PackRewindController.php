<?php

namespace App\Http\Controllers;

use App\Models\PackRewindPack;
use App\Services\Flow\FlovatarPackAuthorizer;
use App\Services\Flow\SportvatarRewindAuthorizer;
use App\Services\Passkey\FlowGateway;
use App\Support\PackRewind;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pack Rewind (TESTNET ONLY, KB notes/pack-rewind.md): the nostalgia timeline of every pack ever sold, re-minted on
 * testnet with the original contents. Sale = the original packs-v2 path: the server reserves the next pack of a series
 * (chronological mainnet order) and signs a short-lived authorisation bound to the buyer's address; the buyer's wallet
 * pays on chain (FlovatarPack.purchasePack: FLOW, or ĐUST for the ĐUST pack; SportvatarPack.purchasePackFlow: FLOW).
 * The DB only mirrors the sale state; the chain is the source of truth (confirm() reads the sealed tx).
 */
class PackRewindController extends AuthController
{
    private function gw(): FlowGateway
    {
        return new FlowGateway();
    }

    private function address(): ?string
    {
        return $this->authenticatedFlowAddress();
    }

    private function seriesStats(): array
    {
        return Cache::remember('pack-rewind:stats', 10, function () {
            $rows = PackRewindPack::selectRaw("series, count(*) total, sum(status = 'sold') sold")->groupBy('series')->get()->keyBy('series');
            $out = [];
            foreach (PackRewind::series() as $key => $s) {
                $r = $rows[$key] ?? null;
                $total = (int) ($r->total ?? 0);
                $sold = (int) ($r->sold ?? 0);
                $out[] = [
                    'key' => $key, 'collection' => $s['collection'], 'year' => $s['year'], 'date' => $s['date'],
                    'title' => $s['title'], 'price' => $s['price'], 'currency' => $s['currency'], 'contents' => $s['contents'],
                    'story' => $s['story'], 'art' => $s['art'],
                    'supply' => $total, 'sold' => $sold, 'remaining' => max(0, $total - $sold),
                ];
            }

            return $out;
        });
    }

    public function page(Request $request)
    {
        return view('pack-rewind.index', ['series' => $this->seriesStats()]);
    }

    public function apiSeries(Request $request): JsonResponse
    {
        $mine = [];
        if ($a = $this->address()) {
            $mine = PackRewindPack::where('sold_to', $a)->selectRaw('series, count(*) n')->groupBy('series')->pluck('n', 'series');
        }

        return response()->json(['series' => $this->seriesStats(), 'mine' => $mine, 'address' => $this->address()])
            ->header('Cache-Control', 'private, no-store');
    }

    /** Is this testnet pack still in the contract account's stock (not bought)? */
    private function inStock(PackRewindPack $p): bool
    {
        $c = $p->collection === 'sportvatar' ? PackRewind::sportvatarContract() : PackRewind::flovatarContract();
        $name = $p->collection === 'sportvatar' ? 'SportvatarPack' : 'FlovatarPack';
        $code = "import $name from $c\naccess(all) fun main(id: UInt64): Bool { return $name.getPackInfo(id: id) != nil }";

        return (bool) $this->gw()->script($code, [['type' => 'UInt64', 'value' => (string) $p->testnet_id]]);
    }

    public function reserve(Request $request): JsonResponse
    {
        $address = $this->address();
        if (! $address) {
            return response()->json(['error' => 'Connect and verify your wallet first.', 'loginRequired' => true], 401);
        }
        $key = (string) $request->input('series');
        $cfg = PackRewind::series()[$key] ?? null;
        if (! $cfg) {
            return response()->json(['error' => 'Unknown pack'], 422);
        }
        $ttl = (int) config('pack_rewind.reservation_ttl', 600);
        $open = PackRewindPack::where('reserved_by', $address)->where('status', 'reserved')->where('reserved_until', '>', now())->get();
        // a pending reservation of the same series is handed out again (retry after a cancelled wallet prompt)
        $pack = $open->firstWhere('series', $key);
        if (! $pack && $open->count() >= (int) config('pack_rewind.max_open_reservations', 3)) {
            return response()->json(['error' => 'You already hold '.$open->count().' reserved packs: buy them or wait a few minutes.'], 429);
        }
        $tries = 0;
        while (! $pack && $tries < 6) {
            $tries++;
            $pack = DB::transaction(function () use ($key, $address, $ttl) {
                $p = PackRewindPack::where('series', $key)->offerable()->orderBy('mainnet_id')->lockForUpdate()->first();
                if ($p) {
                    $p->update(['status' => 'reserved', 'reserved_by' => $address, 'reserved_until' => now()->addSeconds($ttl)]);
                }

                return $p;
            });
            if (! $pack) {
                return response()->json(['error' => 'Sold out: every pack of this series has been replayed.'], 410);
            }
            try {
                if (! $this->inStock($pack)) {
                    $pack->update(['status' => 'sold', 'reserved_by' => null, 'sold_at' => now()]);
                    Cache::forget('pack-rewind:stats');
                    $pack = null;
                }
            } catch (Throwable $e) {
                Log::warning('pack-rewind stock check failed: '.$e->getMessage());
            }
        }
        if (! $pack) {
            return response()->json(['error' => 'Please try again in a moment.'], 503);
        }
        try {
            if ($pack->collection === 'sportvatar') {
                $auth = SportvatarRewindAuthorizer::fromConfig()->authorize((int) $pack->testnet_id, $address, $ttl);
            } else {
                $kind = $pack->currency === 'DUST' ? 'dust' : 'flow';
                $auth = app(FlovatarPackAuthorizer::class)->authorize($kind, (int) $pack->testnet_id, $address, $ttl);
            }
        } catch (Throwable $e) {
            Log::error('pack-rewind authorise failed: '.$e->getMessage());

            return response()->json(['error' => 'The pack could not be authorised right now.'], 503);
        }

        return response()->json([
            'collection' => $pack->collection, 'series' => $pack->series, 'packId' => (int) $pack->testnet_id,
            'mainnetId' => (int) $pack->mainnet_id, 'price' => rtrim(rtrim(number_format((float) $pack->price, 8, '.', ''), '0'), '.'),
            'currency' => $pack->currency, 'kind' => $auth['kind'], 'expiry' => $auth['expiry'], 'signature' => $auth['signature'],
        ])->header('Cache-Control', 'private, no-store');
    }

    /** After the wallet's tx sealed: verify it on chain (Purchased + Deposit to the buyer) and mark the pack sold. */
    public function confirm(Request $request): JsonResponse
    {
        $address = $this->address();
        if (! $address) {
            return response()->json(['error' => 'Not signed in'], 401);
        }
        $txId = strtolower((string) $request->input('txId'));
        $collection = $request->input('collection') === 'sportvatar' ? 'sportvatar' : 'flovatar';
        $packId = (int) $request->input('packId');
        if (! preg_match('/^[0-9a-f]{64}$/', $txId)) {
            return response()->json(['error' => 'Bad transaction id'], 422);
        }
        $pack = PackRewindPack::where('collection', $collection)->where('testnet_id', $packId)->first();
        if (! $pack) {
            return response()->json(['error' => 'Unknown pack'], 404);
        }
        $r = $this->gw()->wait($txId, 30);
        if ($r['status'] !== 'Sealed' || $r['error'] !== '') {
            return response()->json(['error' => 'Transaction not sealed or failed', 'status' => $r['status']], 409);
        }
        $c = substr($collection === 'sportvatar' ? PackRewind::sportvatarContract() : PackRewind::flovatarContract(), 2);
        $name = $collection === 'sportvatar' ? 'SportvatarPack' : 'FlovatarPack';
        $bought = false;
        $toBuyer = false;
        foreach ($r['events'] as $e) {
            $v = (array) ($e['values'] ?? []);
            if ($e['type'] === "A.$c.$name.Purchased" && (int) ($v['id'] ?? -1) === $packId) {
                $bought = true;
            }
            if ($e['type'] === "A.$c.$name.Deposit" && (int) ($v['id'] ?? -1) === $packId
                && strtolower((string) ($v['to'] ?? '')) === $address) {
                $toBuyer = true;
            }
        }
        if (! $bought || ! $toBuyer) {
            return response()->json(['error' => 'This transaction did not buy that pack for you'], 422);
        }
        $pack->update(['status' => 'sold', 'sold_to' => $address, 'sold_tx' => $txId, 'sold_at' => now(), 'reserved_by' => null, 'reserved_until' => null]);
        Cache::forget('pack-rewind:stats');

        return response()->json(['ok' => true, 'pack' => $this->packJson($pack)]);
    }

    private function packJson(PackRewindPack $p): array
    {
        $s = PackRewind::series()[$p->series] ?? [];

        return [
            'collection' => $p->collection, 'packId' => (int) $p->testnet_id, 'mainnetId' => (int) $p->mainnet_id,
            'series' => $p->series, 'title' => $s['title'] ?? $p->name, 'year' => $s['year'] ?? null,
            'art' => $s['art'] ?? null, 'contents' => $p->contents,
            'source' => str_starts_with((string) $p->source, 'regenerated') ? 'regenerated' : 'original',
            'opened' => $p->opened_at !== null,
        ];
    }

    /** Rewind data for pack ids the wallet holds (the page reads the ids from chain). ?flovatar=1,2&sportvatar=3 */
    public function packs(Request $request): JsonResponse
    {
        $out = [];
        foreach (['flovatar', 'sportvatar'] as $col) {
            $ids = array_slice(array_filter(array_map('intval', explode(',', (string) $request->query($col, '')))), 0, 200);
            if ($ids) {
                foreach (PackRewindPack::where('collection', $col)->whereIn('testnet_id', $ids)->get() as $p) {
                    $out[] = $this->packJson($p);
                }
            }
        }

        return response()->json(['packs' => $out])->header('Cache-Control', 'private, no-store');
    }

    /** After an open tx sealed: mark the pack opened (cosmetic; contents already came from the chain). */
    public function opened(Request $request): JsonResponse
    {
        $txId = strtolower((string) $request->input('txId'));
        $collection = $request->input('collection') === 'sportvatar' ? 'sportvatar' : 'flovatar';
        $packId = (int) $request->input('packId');
        if (! preg_match('/^[0-9a-f]{64}$/', $txId)) {
            return response()->json(['error' => 'Bad transaction id'], 422);
        }
        $r = $this->gw()->wait($txId, 30);
        $c = substr($collection === 'sportvatar' ? PackRewind::sportvatarContract() : PackRewind::flovatarContract(), 2);
        $name = $collection === 'sportvatar' ? 'SportvatarPack' : 'FlovatarPack';
        foreach ($r['events'] as $e) {
            if ($e['type'] === "A.$c.$name.Opened" && (int) (((array) $e['values'])['id'] ?? -1) === $packId) {
                PackRewindPack::where('collection', $collection)->where('testnet_id', $packId)->update(['opened_at' => now()]);

                return response()->json(['ok' => true]);
            }
        }

        return response()->json(['error' => 'Open not found in that transaction'], 422);
    }
}
