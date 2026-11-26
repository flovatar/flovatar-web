<?php

namespace App\Services\Islands;

use App\Models\IslandTemplate;
use App\Services\Flow\FlowRestClient;
use App\Support\Islands;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Read-only chain access for Asteroid Islands (Flow REST scripts against the site's access node).
 * The Cadence is inline (contract address from config/islands.php) and returns plain strings/numbers only,
 * so the JSON-Cadence decoding stays simple.
 */
class IslandChain
{
    public function __construct(protected ?FlowRestClient $rest = null)
    {
    }

    protected function rest(): FlowRestClient
    {
        return $this->rest ??= new FlowRestClient();
    }

    protected function import(): string
    {
        $addr = Islands::contract();
        if ($addr === '') {
            throw new RuntimeException('No island contract configured (ISLANDS_CONTRACT_ADDRESS)');
        }

        return 'import FlovatarAsteroidIsland from '.$addr."\n";
    }

    public function run(string $body, array $args = [])
    {
        return $this->rest()->script($this->import().$body, $args);
    }

    // ------------------------------------------------------------------------------------------- config

    /** Contract settings + derived knobs (cached). */
    public function config(bool $fresh = false): array
    {
        $key = 'islands:chain:config:'.Islands::contractHex();
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, (int) config('islands.chain_cache_seconds', 300), fn () => $this->readConfig());
    }

    public function readConfig(): array
    {
        $r = $this->run(<<<'CDC'
access(all) fun main(): {String: AnyStruct} {
    let layers: {String: {String: [String]}} = {}
    let cfgs = FlovatarAsteroidIsland.getLayerConfigs()
    for name in cfgs.keys {
        let c = cfgs[name]!
        let req: [String] = []
        let allow: [String] = []
        for r in c.required.keys { if c.required[r] == true { req.append(r) } }
        for r in c.allowed.keys { if c.allowed[r] == true { allow.append(r) } }
        layers[name] = {"required": req, "allowed": allow}
    }
    let rules: {String: {String: Bool}} = FlovatarAsteroidIsland.getTypeRules()
    let fees: [String] = FlovatarAsteroidIsland.getFees().keys
    return {
        "totalSupply": FlovatarAsteroidIsland.totalSupply,
        "settings": FlovatarAsteroidIsland.getSettings(),
        "paused": FlovatarAsteroidIsland.getPaused(),
        "sceneOrder": FlovatarAsteroidIsland.getSceneOrder(),
        "layers": layers,
        "typeRules": rules,
        "fees": fees,
        "psModel": FlovatarAsteroidIsland.isValidSlot(rarity: "common", slot: "P1")
    }
}
CDC);
        $settings = (array) ($r['settings'] ?? []);
        $premium = [];
        $budget = [];
        foreach (Islands::RARITIES as $rar) {
            $cap = Islands::capacity($rar);
            $n = $settings['premiumSlots.'.$rar] ?? null;
            $premium[$rar] = max(0, min($cap, is_numeric($n) ? (int) $n : (int) config('islands.premium_default.'.$rar)));
            $b = $settings['sceneBudget.'.$rar] ?? null;
            $budget[$rar] = is_numeric($b) ? (int) $b : null;
        }
        $premiumTypes = Islands::premiumTypes();
        foreach ((array) (($r['typeRules'] ?? [])['premium'] ?? []) as $typeId => $allowed) {
            if ($allowed === true && ! isset($premiumTypes[$typeId])) {
                $parts = explode('.', (string) $typeId);
                $premiumTypes[$typeId] = $parts[2] ?? $typeId;
            } elseif ($allowed === false) {
                unset($premiumTypes[$typeId]);
            }
        }

        return [
            'contract' => Islands::contract(),
            'totalSupply' => (int) ($r['totalSupply'] ?? 0),
            'svgOpen' => (string) ($settings['svgOpen'] ?? ''),
            'tintRarities' => array_values(array_filter(explode(',', (string) ($settings['tintRarities'] ?? '')))),
            'sceneOrder' => array_values((array) ($r['sceneOrder'] ?? [])),
            'layers' => (array) ($r['layers'] ?? []),
            'paused' => (array) ($r['paused'] ?? []),
            'fees' => array_values((array) ($r['fees'] ?? [])),
            'slotModel' => ! empty($r['psModel']) ? 'ps' : 'pad',
            'premium' => $premium,
            'capacity' => array_combine(Islands::RARITIES, array_map(fn ($x) => Islands::capacity($x), Islands::RARITIES)),
            'budget' => $budget,
            'premiumTypes' => $premiumTypes,
            'residentPolicy' => (string) ($settings['residentPolicy'] ?? 'open'),
            'residentScale' => (function () use ($settings) {
                $out = [];
                foreach ($settings as $k => $v) {
                    if (str_starts_with((string) $k, 'residentScale.') && is_numeric($v)) {
                        $out[substr((string) $k, 14)] = (float) $v;
                    }
                }

                return $out;
            })(),
            'maxName' => (int) ($settings['nameMaxLength'] ?? 32),
            'maxDescription' => (int) ($settings['descriptionMaxLength'] ?? 500),
        ];
    }

    // ------------------------------------------------------------------------------------------- templates

    /** Template infos (no SVG): [{id, layer, name, description, rarity, enabled, svgLength, svgFrontLength, usage, metadata}] */
    public function templateInfos(): array
    {
        $r = $this->run(<<<'CDC'
access(all) fun main(): [{String: AnyStruct}] {
    let out: [{String: AnyStruct}] = []
    for t in FlovatarAsteroidIsland.getTemplateInfos() {
        out.append({"id": t.id, "layer": t.layer, "name": t.name, "description": t.description, "rarity": t.rarity,
            "enabled": t.enabled, "svgLength": t.svgLength, "svgFrontLength": t.svgFrontLength, "usage": t.usage,
            "metadata": t.metadata})
    }
    return out
}
CDC);

        return array_values((array) $r);
    }

    public function templateSvg(int $id, bool $front): string
    {
        return (string) $this->run(<<<'CDC'
access(all) fun main(id: UInt64, front: Bool): String {
    return FlovatarAsteroidIsland.getTemplateSvg(id: id, front: front) ?? ""
}
CDC, [['type' => 'UInt64', 'value' => (string) $id], ['type' => 'Bool', 'value' => $front]]);
    }

    /**
     * Refresh the island_templates cache from chain. SVG is (re)fetched when the lengths changed or it is missing.
     * @return array{templates:int, fetched:int}
     */
    public function syncTemplates(bool $force = false, array $forceIds = []): array
    {
        $forceIds = array_map('intval', $forceIds);
        $fetched = 0;
        $infos = $this->templateInfos();
        foreach ($infos as $t) {
            $row = IslandTemplate::firstOrNew(['flow_id' => (int) $t['id']]);
            $needSvg = $force || in_array((int) $t['id'], $forceIds, true) || ! $row->exists || (int) $row->svg_length !== (int) $t['svgLength']
                || (int) $row->svg_front_length !== (int) $t['svgFrontLength'] || $row->svg === null;
            $row->fill([
                'layer' => (string) $t['layer'],
                'rarity' => (string) $t['rarity'],
                'name' => mb_substr((string) $t['name'], 0, 120),
                'description' => (string) ($t['description'] ?? ''),
                'enabled' => (bool) $t['enabled'],
                'svg_length' => (int) $t['svgLength'],
                'svg_front_length' => (int) $t['svgFrontLength'],
                'metadata' => (array) ($t['metadata'] ?? []),
                'usage' => (int) ($t['usage'] ?? 0),
            ]);
            if ($needSvg) {
                $svg = (int) $t['svgLength'] > 0 ? $this->templateSvg((int) $t['id'], false) : '';
                $front = (int) $t['svgFrontLength'] > 0 ? $this->templateSvg((int) $t['id'], true) : '';
                $row->svg = $svg;
                $row->svg_front = $front;
                $row->svg_hash = sha1($svg."\0".$front);
                $fetched++;
            }
            $row->save();
        }
        Cache::forget('islands:templates:list');

        return ['templates' => count($infos), 'fetched' => $fetched];
    }

    // ------------------------------------------------------------------------------------------- islands

    /**
     * One island as stored in $owner's collection, with LIVE resident validation (checkResidents), or null when the
     * owner doesn't hold it.
     */
    public function island(string $owner, int $id): ?array
    {
        $r = $this->run(<<<'CDC'
access(all) fun main(address: Address, id: UInt64): {String: AnyStruct}? {
    if let island = FlovatarAsteroidIsland.borrowIsland(address: address, id: id) {
        let residents: [{String: AnyStruct}] = []
        for s in island.checkResidents() {
            residents.append({"slot": s.slot, "nftType": s.nftType, "nftId": s.nftId, "setBy": s.setBy, "setAt": s.setAt,
                "valid": s.valid, "reason": s.reason})
        }
        return {"id": island.id, "rarity": island.rarity, "name": island.name, "description": island.description,
            "layers": island.getLayers(), "tint": island.tint, "deedIds": island.getDeedIds(), "deedRarity": island.deedRarity,
            "terraformedAt": island.terraformedAt, "owner": address, "residents": residents}
    }
    return nil
}
CDC, [['type' => 'Address', 'value' => $owner], ['type' => 'UInt64', 'value' => (string) $id]]);

        return is_array($r) ? $r : null;
    }

    /** Island ids in an account's collection. */
    public function islandIds(string $owner): array
    {
        $r = $this->run(<<<'CDC'
access(all) fun main(address: Address): [UInt64] {
    if let col = getAccount(address).capabilities.borrow<&{FlovatarAsteroidIsland.CollectionPublic}>(FlovatarAsteroidIsland.CollectionPublicPath) {
        return col.getIDs()
    }
    return []
}
CDC, [['type' => 'Address', 'value' => $owner]]);

        return array_map('intval', (array) $r);
    }
}
