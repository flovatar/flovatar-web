<?php

namespace App\Services\Fub;

use App\Services\Flow\FlowRestClient;
use App\Support\Fub;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Read-only chain access for FUB v1 (FlovatarUniverseBuilder scripts via the Flow REST API). Inline Cadence, contract
 * address from config/fub.php. Every write is a transaction: creator/buyer txs are signed in the wallet (FCL), draft
 * writes by the operator (FubOperator).
 */
class FubChain
{
    public function __construct(protected ?FlowRestClient $rest = null)
    {
    }

    protected function rest(): FlowRestClient
    {
        return $this->rest ??= new FlowRestClient();
    }

    public function imports(): string
    {
        $a = Fub::contract();
        if ($a === '') {
            throw new RuntimeException('No FUB contract configured (FUB_CONTRACT_ADDRESS)');
        }

        return "import FlovatarUniverseBuilder from $a\n";
    }

    public function run(string $body, array $args = [], ?int $height = null)
    {
        return $this->rest()->script($this->imports().$body, $args, $height);
    }

    public static function u64(int $v): array
    {
        return ['type' => 'UInt64', 'value' => (string) $v];
    }

    public static function addr(string $a): array
    {
        return ['type' => 'Address', 'value' => Fub::normalizeAddress($a) ?? $a];
    }

    public static function str(string $s): array
    {
        return ['type' => 'String', 'value' => $s];
    }

    public static function int(int $v): array
    {
        return ['type' => 'Int', 'value' => (string) $v];
    }

    /** Contract settings (cached; display limits come from here, never from code). */
    public function config(bool $fresh = false): array
    {
        $key = 'fub:chain:config:'.Fub::contractHex();
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, (int) config('fub.chain_cache_seconds', 60), function () {
            $r = $this->run(<<<'CDC'
access(all) fun main(): {String: AnyStruct} {
    return {"settings": FlovatarUniverseBuilder.getSettings(), "paused": FlovatarUniverseBuilder.getPaused(),
        "fubCount": FlovatarUniverseBuilder.fubCount, "totalSupply": FlovatarUniverseBuilder.totalSupply,
        "tankRefills": FlovatarUniverseBuilder.tankRefills, "tankBurned": FlovatarUniverseBuilder.tankBurned}
}
CDC);
            $s = (array) ($r['settings'] ?? []);
            $tiers = [];
            foreach (['common', 'epic', 'legendary'] as $t) {
                $tiers[$t] = [
                    'layers' => (int) ($s["layers.$t"] ?? 8),
                    'composed' => (int) ($s["composed.$t"] ?? 120000),
                    'largeItems' => (int) ($s["largeItems.$t"] ?? 3),
                ];
            }

            return [
                'contract' => Fub::contract(), 'settings' => $s, 'paused' => (array) ($r['paused'] ?? []), 'tiers' => $tiers,
                'itemMax' => (int) ($s['itemMaxBytes'] ?? 32768), 'largeItemMax' => (int) ($s['largeItemMaxBytes'] ?? 65536),
                'minPrice' => (float) ($s['minItemPrice'] ?? 1), 'maxPrice' => (float) ($s['maxItemPrice'] ?? 10000000),
                'maxIncrease' => (float) ($s['maxIncrease'] ?? 0.1), 'maxRoyalty' => (float) ($s['maxRoyalty'] ?? 0.1),
                'maxVouchers' => (int) ($s['maxVouchers'] ?? 30), 'svgOpen' => (string) ($s['svgOpen'] ?? ''),
                'tankPresets' => array_map('floatval', array_filter(explode(',', (string) ($s['tankPresets'] ?? '')))),
                'fubCount' => (int) ($r['fubCount'] ?? 0), 'totalSupply' => (int) ($r['totalSupply'] ?? 0),
            ];
        });
    }

    /** {fub, heaviest, composedCap, maxLayers, largeCap, itemIds, pendingIds} or null */
    public function fub(int $id, ?int $height = null): ?array
    {
        return $this->run(<<<'CDC'
access(all) fun main(id: UInt64): {String: AnyStruct}? {
    if let f = FlovatarUniverseBuilder.getFub(id) {
        return {"fub": f, "heaviest": f.heaviest(), "composedCap": FlovatarUniverseBuilder.composedCap(f.tier),
            "maxLayers": FlovatarUniverseBuilder.maxLayers(f.tier), "largeCap": FlovatarUniverseBuilder.largeCap(f.tier),
            "itemIds": FlovatarUniverseBuilder.getItemIds(fubId: id), "pendingIds": FlovatarUniverseBuilder.getPendingItemIds(fubId: id)}
    }
    return nil
}
CDC, [self::u64($id)], $height);
    }

    /** ItemInfo list (no SVG), paged */
    public function items(int $fubId, int $from = 0, int $upTo = 200, ?int $height = null): array
    {
        return (array) $this->run(<<<'CDC'
access(all) fun main(fubId: UInt64, from: Int, upTo: Int): [FlovatarUniverseBuilder.ItemInfo] {
    return FlovatarUniverseBuilder.getItemInfos(fubId: fubId, from: from, upTo: upTo)
}
CDC, [self::u64($fubId), self::int($from), self::int($upTo)], $height);
    }

    /** All items of a FUB (pages of 200). */
    public function allItems(int $fubId, int $count, ?int $height = null): array
    {
        $out = [];
        for ($i = 0; $i < $count; $i += 200) {
            $out = array_merge($out, $this->items($fubId, $i, $i + 200, $height));
        }

        return $out;
    }

    /** item id => svg (batch; keep batches small, a script result is capped). */
    public function itemSvgs(array $ids): array
    {
        $out = [];
        foreach (array_chunk($ids, 10) as $chunk) {
            $r = $this->run(<<<'CDC'
access(all) fun main(ids: [UInt64]): {UInt64: String} {
    let out: {UInt64: String} = {}
    for id in ids { if let s = FlovatarUniverseBuilder.getItemSvg(id) { out[id] = s } }
    return out
}
CDC, [['type' => 'Array', 'value' => array_map(fn ($i) => self::u64((int) $i), $chunk)]]);
            foreach ((array) $r as $k => $v) {
                $out[(int) $k] = (string) $v;
            }
        }

        return $out;
    }

    public function fubCount(): int
    {
        return (int) $this->run('access(all) fun main(): UInt64 { return FlovatarUniverseBuilder.fubCount }');
    }

    public function quote(int $fubId, array $picks): array
    {
        return (array) $this->run(<<<'CDC'
access(all) fun main(fubId: UInt64, layerKeys: [String], itemIds: [UInt64]): FlovatarUniverseBuilder.Quote {
    let picks: {String: UInt64} = {}
    var i = 0
    while i < layerKeys.length { picks[layerKeys[i]] = itemIds[i]; i = i + 1 }
    return FlovatarUniverseBuilder.quote(fubId: fubId, picks: picks)
}
CDC, [self::u64($fubId), ['type' => 'Array', 'value' => array_map(fn ($k) => self::str((string) $k), array_keys($picks))],
            ['type' => 'Array', 'value' => array_map(fn ($v) => self::u64((int) $v), array_values($picks))]]);
    }

    /** On-chain composition from the stored bytes (draft items included): the creator's true preview. */
    public function previewSvg(int $fubId, array $picks): string
    {
        return (string) $this->run(<<<'CDC'
access(all) fun main(fubId: UInt64, layerKeys: [String], itemIds: [UInt64]): String {
    let picks: {String: UInt64} = {}
    var i = 0
    while i < layerKeys.length { picks[layerKeys[i]] = itemIds[i]; i = i + 1 }
    return FlovatarUniverseBuilder.previewSvg(fubId: fubId, picks: picks)
}
CDC, [self::u64($fubId), ['type' => 'Array', 'value' => array_map(fn ($k) => self::str((string) $k), array_keys($picks))],
            ['type' => 'Array', 'value' => array_map(fn ($v) => self::u64((int) $v), array_values($picks))]]);
    }

    /** F.U.N.s in a wallet (FunData list). */
    public function funs(string $address): array
    {
        return (array) $this->run(<<<'CDC'
access(all) fun main(address: Address): [FlovatarUniverseBuilder.FunData] {
    let out: [FlovatarUniverseBuilder.FunData] = []
    if let col = getAccount(address).capabilities.borrow<&{FlovatarUniverseBuilder.CollectionPublic}>(FlovatarUniverseBuilder.CollectionPublicPath) {
        for id in col.getIDs() {
            if let f = col.borrowFun(id: id) {
                out.append(f.resolveView(Type<FlovatarUniverseBuilder.FunData>())! as! FlovatarUniverseBuilder.FunData)
            }
        }
    }
    return out
}
CDC, [self::addr($address)]);
    }

    /** Transaction result via REST: ['status', 'error', 'events' => [['type', 'values']]] */
    public function txResult(string $txId): array
    {
        $gw = new \App\Services\Passkey\FlowGateway();

        return $gw->result(preg_replace('/^0x/', '', strtolower($txId)));
    }

    public function txHeight(string $txId): ?int
    {
        return $this->rest()->transactionHeight($txId);
    }
}
