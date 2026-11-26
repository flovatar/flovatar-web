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

    /**
     * Contract config. Contract v3 (round 4) adds shared / mutable layers, the display layer and set_layer; on a v2
     * contract the v3 script fails to type-check, so the v2 script runs and the round-4 knobs are off (rarity lock
     * everywhere, no set_layer).
     */
    public function readConfig(): array
    {
        $v2 = <<<'CDC'
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
        "psModel": FlovatarAsteroidIsland.isValidSlot(rarity: "common", slot: "P1")/*V3*/
    }
}
CDC;
        $v3 = str_replace('/*V3*/', ',
        "sharedLayers": FlovatarAsteroidIsland.getSharedLayers(),
        "mutableLayers": FlovatarAsteroidIsland.getMutableLayers(),
        "displayLayer": FlovatarAsteroidIsland.displayLayer(),
        "setLayerPaused": FlovatarAsteroidIsland.isPaused("setlayer")', $v2);
        $version = 3;
        try {
            $r = $this->run($v3);
        } catch (RuntimeException $e) {
            if (! str_contains($e->getMessage(), 'has no member') && ! str_contains($e->getMessage(), 'cannot find')) {
                throw $e;
            }
            $version = 2;
            $r = $this->run(str_replace('/*V3*/', '', $v2));
        }
        $settings = (array) ($r['settings'] ?? []);
        // the contract names the islands (setting designationPrefix, e.g. "K313/"): keep it for every page/API
        Islands::rememberDesignationPrefix($settings['designationPrefix'] ?? null);
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
            'designationPrefix' => Islands::designationPrefix(),
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
            // round 4 (contract v3): layers without rarity lock, layers the owner may change any time (set_layer), the
            // display layer (also choosable ONCE by islands minted before it, setting displayMigration != off)
            'contractVersion' => $version,
            'sharedLayers' => array_values(array_map('strval', (array) ($r['sharedLayers'] ?? []))),
            'mutableLayers' => array_values(array_map('strval', (array) ($r['mutableLayers'] ?? []))),
            'displayLayer' => (string) ($r['displayLayer'] ?? 'display'),
            'displayMigration' => $version >= 3 && ($settings['displayMigration'] ?? '') !== 'off',
            'setLayerPaused' => (bool) ($r['setLayerPaused'] ?? ($version < 3)),
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
        $script = <<<'CDC'
access(all) fun main(address: Address, id: UInt64): {String: AnyStruct}? {
    if let island = FlovatarAsteroidIsland.borrowIsland(address: address, id: id) {
        let residents: [{String: AnyStruct}] = []
        for s in island.checkResidents() {
            residents.append({"slot": s.slot, "nftType": s.nftType, "nftId": s.nftId, "setBy": s.setBy, "setAt": s.setAt,
                "valid": s.valid, "reason": s.reason})
        }
        return {"id": island.id, "rarity": island.rarity, "name": island.name, "description": island.description,
            "layers": island.getLayers(), "tint": island.tint, "deedIds": island.getDeedIds(), "deedRarity": island.deedRarity,
            "terraformedAt": island.terraformedAt, "owner": address, "residents": residents/*V3*/}
    }
    return nil
}
CDC;
        $args = [['type' => 'Address', 'value' => $owner], ['type' => 'UInt64', 'value' => (string) $id]];
        if ((int) ($this->config()['contractVersion'] ?? 2) >= 3) {
            try {
                $r = $this->run(str_replace('/*V3*/', ', "settableLayers": island.settableLayers(), "screenResident": island.screenResident()', $script), $args);

                return is_array($r) ? $r : null;
            } catch (RuntimeException $e) {
                // contract rolled back / not yet updated: v2 shape below
            }
        }
        $r = $this->run(str_replace('/*V3*/', '', $script), $args);

        return is_array($r) ? $r : null;
    }

    /**
     * MetadataViews.Display (name + thumbnail URL) of every resident of an island, resolved from the owner's wallet like
     * the contract's ownership check: [slot => ['name' => …, 'thumbnail' => …]]. Separate from island() so one NFT
     * whose view resolver fails can't break the island refresh (callers catch).
     */
    public function residentDisplays(string $owner, int $id): array
    {
        $mv = (string) config('services.flow.nftaddress');
        $r = $this->rest()->script('import FlovatarAsteroidIsland from '.Islands::contract()."\nimport MetadataViews from ".$mv."\n".<<<'CDC'
access(all) fun main(address: Address, id: UInt64): {String: {String: String}} {
    let out: {String: {String: String}} = {}
    if let island = FlovatarAsteroidIsland.borrowIsland(address: address, id: id) {
        let residents = island.getResidents()
        for slot in residents.keys {
            let r = residents[slot]!
            if let nft = FlovatarAsteroidIsland.borrowResidentNFT(owner: address, nftType: r.nftType, nftId: r.nftId, collectionPath: r.collectionPath) {
                if let d = nft.resolveView(Type<MetadataViews.Display>()) as? MetadataViews.Display {
                    out[slot] = {"name": d.name, "thumbnail": d.thumbnail.uri()}
                }
            }
        }
    }
    return out
}
CDC, [['type' => 'Address', 'value' => $owner], ['type' => 'UInt64', 'value' => (string) $id]]);
        $out = [];
        foreach ((array) $r as $slot => $d) {
            $out[(string) $slot] = ['name' => mb_substr((string) ($d['name'] ?? ''), 0, 160), 'thumbnail' => mb_substr((string) ($d['thumbnail'] ?? ''), 0, 2000)];
        }

        return $out;
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
