<?php

namespace App\Services\Fub;

use App\Models\Fub;
use App\Models\FubDraft;
use App\Models\FubItem;
use App\Models\Fun;
use Carbon\Carbon;

/**
 * Chain -> DB index for FUB v1. refreshFub() re-reads one FUB (record + items + new/changed SVG) and upserts the
 * fubs / fub_items rows; recordMint() stores a F.U.N. from its FunMinted event. Idempotent; site-only fields
 * (hidden_at, checks_passed_at) are never touched here.
 */
class FubIndexer
{
    public function __construct(protected FubChain $chain)
    {
    }

    public function refreshFub(int $id, ?int $height = null): ?Fub
    {
        $r = $this->chain->fub($id, $height);
        if (! $r) {
            return null;
        }
        $f = (array) $r['fub'];
        $row = Fub::firstOrNew(['flow_id' => $id]);
        $wasBuilding = ! $row->exists || $row->status === 'building';
        $row->fill([
            'creator' => \App\Support\Fub::normalizeAddress($f['creator'] ?? ''),
            'payout' => \App\Support\Fub::normalizeAddress($f['payout'] ?? ''),
            'status' => (string) $f['status'],
            'suspended' => (bool) $f['suspended'],
            'tier' => (string) $f['tier'],
            'slots' => (int) $f['slots'],
            'mint_cap' => (int) $f['mintCap'],
            'max_supply' => (int) $f['maxSupply'],
            'minted' => (int) $f['minted'],
            'name' => (string) $f['name'],
            'description' => (string) ($f['description'] ?? ''),
            'layers' => array_map(fn ($l) => ['key' => $l['key'], 'name' => $l['name'], 'required' => (bool) $l['required']], (array) ($f['layers'] ?? [])),
            'royalty' => (float) $f['royaltyCut'],
            'unique_combos' => (bool) $f['unique'],
            'license' => (string) ($f['license'] ?? ''),
            'island_id' => isset($f['islandId']) ? (int) $f['islandId'] : null,
            'manifest' => (string) $f['manifest'],
            'pending' => (string) ($f['pending'] ?? ''),
            'items' => (int) $f['items'],
            'published_at' => ! empty($f['publishedAt']) ? Carbon::createFromTimestamp((int) $f['publishedAt']) : null,
            'data' => ['heaviest' => (int) $r['heaviest'], 'composedCap' => (int) $r['composedCap'], 'maxLayers' => (int) $r['maxLayers'],
                'largeCap' => (int) $r['largeCap'], 'largeItems' => (int) $f['largeItems'], 'voucherIds' => $f['voucherIds'] ?? [],
                'feeTreasury' => (float) $f['feeTreasury'], 'feeBurn' => (float) $f['feeBurn'], 'tosHash' => (string) ($f['tosHash'] ?? ''),
                'seq' => (int) $f['seq'], 'pendingIds' => array_map('intval', (array) $r['pendingIds'])],
            'chain_read_at' => now(),
        ]);
        // automatic checks (Q11 b): items passed the gate at upload; nothing reported -> eligible for the directory
        if ($row->status !== 'building' && $row->checks_passed_at === null) {
            $row->checks_passed_at = now();
        }
        $row->save();

        $infos = $this->chain->allItems($id, count((array) $r['itemIds']), $height);
        $existing = FubItem::where('fub_flow_id', $id)->get()->keyBy('flow_id');
        $needSvg = [];
        $seen = [];
        foreach ($infos as $info) {
            $it = (array) $info['item'];
            $iid = (int) $it['id'];
            $seen[] = $iid;
            $item = $existing[$iid] ?? new FubItem(['flow_id' => $iid]);
            if (! $item->exists || $item->hash !== (string) $it['hash']) {
                $needSvg[] = $iid;
            }
            $item->fill([
                'fub_flow_id' => $id, 'layer' => (string) $it['layer'], 'name' => (string) $it['name'],
                'description' => (string) ($it['description'] ?? ''), 'rarity' => (string) $it['rarity'],
                'price' => (float) $it['price'], 'current_price' => (float) $it['currentPrice'], 'max_mint' => (int) $it['maxMint'],
                'minted' => (int) $it['minted'], 'increase' => (float) $it['increase'], 'price_cap' => (float) $it['priceCap'],
                'size' => (int) $it['size'], 'hash' => (string) $it['hash'], 'state' => (string) $it['state'],
                'live' => (bool) $info['live'], 'suspended' => (bool) $it['suspended'], 'metadata' => (array) ($it['metadata'] ?? []),
            ]);
            $item->save();
        }
        FubItem::where('fub_flow_id', $id)->whereNotIn('flow_id', $seen ?: [0])->delete(); // removed draft items
        if ($needSvg) {
            foreach ($this->chain->itemSvgs($needSvg) as $iid => $svg) {
                FubItem::where('flow_id', $iid)->update(['svg' => $svg]);
            }
        }
        // the draft that produced this FUB follows the chain status
        if ($wasBuilding && $row->status !== 'building') {
            FubDraft::where('chain_fub_id', $id)->where('status', '!=', 'published')->update(['status' => 'published', 'published_at' => $row->published_at ?? now()]);
        }

        return $row->fresh();
    }

    /** FunMinted event payload -> funs row (picks derived from the item layers). */
    public function recordMint(array $e, ?string $txId = null, $at = null): void
    {
        $fubId = (int) ($e['fubId'] ?? 0);
        $ids = array_map('intval', (array) ($e['items'] ?? []));
        $layers = FubItem::whereIn('flow_id', $ids)->pluck('layer', 'flow_id');
        $picks = [];
        foreach ($ids as $iid) {
            $picks[(string) ($layers[$iid] ?? 'item'.$iid)] = $iid;
        }
        Fun::updateOrCreate(['flow_id' => (int) $e['id']], [
            'fub_flow_id' => $fubId, 'serial' => (int) $e['serial'], 'owner' => \App\Support\Fub::normalizeAddress($e['to'] ?? null),
            'picks' => $picks, 'price' => (float) $e['price'], 'tx_id' => $txId, 'minted_at' => $at ?? now(),
        ]);
    }

    /** Safety net (scheduler): refresh every FUB the chain knows. */
    public function refreshAll(): int
    {
        $n = $this->chain->fubCount();
        for ($i = 1; $i <= $n; $i++) {
            $this->refreshFub($i);
        }

        return $n;
    }
}
