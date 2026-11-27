<?php

namespace App\Services\Fub;

use App\Models\FubDraft;
use App\Models\FubDraftItem;
use RuntimeException;

/**
 * Pushes a committed draft to the chain through the operator: diff(draft, chain) -> Uploader transactions, in an order
 * the contract accepts (remove items first, then info/layers/options, then put/replace/update). Only FUBs that are still
 * "building" are written; additions after publish are staged the same way (putItem only) and need the creator's
 * commitAdditions signature. Never publishes, never touches live items (the contract would refuse anyway).
 */
class DraftSync
{
    public function __construct(protected FubChain $chain, protected FubOperator $operator)
    {
    }

    public static function ufix(float $v): array
    {
        return ['type' => 'UFix64', 'value' => number_format($v, 8, '.', '')];
    }

    /** Plan the ops (pure; testable). $chainFub = FubChain::fub(), $chainItems = FubChain::allItems(). */
    public static function plan(FubDraft $d, array $chainFub, array $chainItems): array
    {
        $f = (array) $chainFub['fub'];
        $building = $f['status'] === 'building';
        $ops = [];
        $chainById = [];
        foreach ($chainItems as $ci) {
            $chainById[(int) $ci['item']['id']] = $ci;
        }
        $items = $d->items()->get();
        $layers = $d->layers()->get();
        $keys = $layers->pluck('key')->all();

        // 1. removals (draft-deleted items, or items whose layer is gone) — only non-live chain items
        foreach ($items as $it) {
            if ($it->chain_item_id && ($it->chain_state === 'deleted' || ! in_array($it->layer_key, $keys, true))) {
                $ci = $chainById[(int) $it->chain_item_id] ?? null;
                if ($ci && ! $ci['live']) {
                    $ops[] = ['op' => 'removeItem', 'item' => $it->id, 'args' => [FubChain::u64((int) $it->chain_item_id)]];
                }
            }
        }
        if ($building) {
            if ((string) $f['name'] !== $d->name || (string) ($f['description'] ?? '') !== (string) $d->description) {
                $ops[] = ['op' => 'setInfo', 'args' => [FubChain::u64($d->chain_fub_id), FubChain::str($d->name), FubChain::str((string) $d->description)]];
            }
            $chainLayers = array_map(fn ($l) => [$l['key'], $l['name'], (bool) $l['required']], (array) ($f['layers'] ?? []));
            $draftLayers = $layers->map(fn ($l) => [$l->key, $l->name, (bool) $l->required])->all();
            if ($chainLayers !== $draftLayers && $draftLayers) {
                $ops[] = ['op' => 'setLayers', 'args' => [
                    FubChain::u64($d->chain_fub_id),
                    ['type' => 'Array', 'value' => array_map(fn ($l) => FubChain::str($l[0]), $draftLayers)],
                    ['type' => 'Array', 'value' => array_map(fn ($l) => FubChain::str($l[1]), $draftLayers)],
                    ['type' => 'Array', 'value' => array_map(fn ($l) => ['type' => 'Bool', 'value' => $l[2]], $draftLayers)],
                ]];
            }
            $roy = (float) $d->setting('royalty', 0);
            $unique = (bool) $d->setting('unique', count($draftLayers) > 1);
            $max = (int) $d->setting('max_supply', (int) $f['mintCap']);
            $max = $max < 1 || $max > (int) $f['mintCap'] ? (int) $f['mintCap'] : $max;
            $lic = (string) $d->setting('license', 'holder-commercial');
            if (abs((float) $f['royaltyCut'] - $roy) > 1e-9 || (bool) $f['unique'] !== $unique || (int) $f['maxSupply'] !== $max || (string) $f['license'] !== $lic) {
                $ops[] = ['op' => 'setOptions', 'args' => [FubChain::u64($d->chain_fub_id), self::ufix($roy), ['type' => 'Bool', 'value' => $unique],
                    FubChain::u64($max), FubChain::str($lic)]];
            }
        }
        // 2. new / changed items (layers exist now)
        foreach ($items as $it) {
            if ($it->chain_state === 'deleted' || ! in_array($it->layer_key, $keys, true)) {
                continue;
            }
            $ci = $it->chain_item_id ? ($chainById[(int) $it->chain_item_id] ?? null) : null;
            $fields = self::itemFields($it);
            if (! $ci) {
                $ops[] = ['op' => 'putItem', 'item' => $it->id, 'args' => array_merge([FubChain::u64($d->chain_fub_id)], $fields, [FubChain::str($it->svg)])];
                continue;
            }
            if ($ci['live']) {
                continue; // published items never change
            }
            $c = (array) $ci['item'];
            if ((string) $c['hash'] !== $it->sha3 || (bool) $c['large'] !== (bool) $it->large) {
                $ops[] = ['op' => 'replaceItem', 'item' => $it->id, 'args' => array_merge([FubChain::u64((int) $it->chain_item_id)], $fields, [FubChain::str($it->svg)])];
            } elseif (self::fieldsDiffer($c, $it)) {
                $ops[] = ['op' => 'updateItem', 'item' => $it->id, 'args' => array_merge([FubChain::u64((int) $it->chain_item_id)], $fields)];
            }
        }

        return $ops;
    }

    public static function itemFields(FubDraftItem $it): array
    {
        $curve = (float) $it->increase > 0;
        $meta = ['sanitizer' => SvgSanitizer::VERSION, 'aiAssisted' => $it->ai_assisted ? 'true' : 'false', 'draftItem' => (string) $it->id];

        return [
            FubChain::str($it->layer_key), FubChain::str($it->name), FubChain::str((string) $it->description), FubChain::str($it->rarity),
            self::ufix((float) $it->price), FubChain::u64((int) $it->max_mint), self::ufix((float) $it->increase),
            self::ufix($curve ? (float) $it->price_cap : (float) $it->price), ['type' => 'Bool', 'value' => (bool) $it->large],
            ['type' => 'Dictionary', 'value' => array_map(fn ($k, $v) => ['key' => FubChain::str($k), 'value' => FubChain::str($v)], array_keys($meta), $meta)],
        ];
    }

    private static function fieldsDiffer(array $c, FubDraftItem $it): bool
    {
        $cap = (float) $it->increase > 0 ? (float) $it->price_cap : (float) $it->price;

        return (string) $c['layer'] !== $it->layer_key || (string) $c['name'] !== $it->name || (string) ($c['description'] ?? '') !== (string) $it->description
            || (string) $c['rarity'] !== $it->rarity || abs((float) $c['price'] - (float) $it->price) > 1e-8 || (int) $c['maxMint'] !== (int) $it->max_mint
            || abs((float) $c['increase'] - (float) $it->increase) > 1e-8 || abs((float) $c['priceCap'] - $cap) > 1e-8
            || (($c['metadata']['aiAssisted'] ?? 'false') === 'true') !== (bool) $it->ai_assisted;
    }

    /** Execute the plan. Returns the list of [op, txId]. Throws on the first failed tx (state stays consistent: re-plan). */
    public function sync(FubDraft $d): array
    {
        if (! $d->chain_fub_id) {
            throw new RuntimeException('Commit the FUB first');
        }
        $done = [];
        for ($round = 0; $round < 3; $round++) {
            $cf = $this->chain->fub((int) $d->chain_fub_id) ?? throw new RuntimeException('FUB not found on chain');
            if ((array) $cf['fub'] && ($cf['fub']['creator'] ?? null) && strtolower((string) $cf['fub']['creator']) !== strtolower($d->owner_address)) {
                throw new RuntimeException('This FUB belongs to another wallet');
            }
            $items = $this->chain->allItems((int) $d->chain_fub_id, count((array) $cf['itemIds']));
            $ops = self::plan($d, $cf, $items);
            if (! $ops) {
                break;
            }
            foreach ($ops as $op) {
                $r = $this->operator->send(FubOperator::tx($op['op']), $op['args']);
                if (($r['error'] ?? '') !== '' || ($r['status'] ?? '') !== 'Sealed') {
                    throw new RuntimeException($op['op'].' failed: '.self::friendly((string) ($r['error'] ?? $r['status'] ?? 'unknown')));
                }
                $done[] = [$op['op'], $r['id']];
                if (isset($op['item'])) {
                    $it = FubDraftItem::find($op['item']);
                    if ($op['op'] === 'putItem') {
                        foreach ($r['events'] as $e) {
                            if (str_ends_with($e['type'], '.ItemPut')) {
                                $it->update(['chain_item_id' => (int) $e['values']['itemId'], 'chain_state' => 'synced', 'chain_hash' => $it->sha3]);
                            }
                        }
                    } elseif ($op['op'] === 'removeItem') {
                        if ($it && $it->chain_state === 'deleted') {
                            $it->delete();
                        } elseif ($it) {
                            $it->update(['chain_item_id' => null, 'chain_state' => 'new']);
                        }
                    } elseif ($it) {
                        $it->update(['chain_state' => 'synced', 'chain_hash' => $it->sha3]);
                    }
                }
            }
        }
        // draft-deleted items that never reached the chain
        $d->items()->where('chain_state', 'deleted')->whereNull('chain_item_id')->delete();

        return $done;
    }

    public static function friendly(string $err): string
    {
        if (preg_match('/(assertion failed|panic): ([^\n]+)/', $err, $m)) {
            return trim($m[2]);
        }

        return substr(preg_replace('/\s+/', ' ', $err), 0, 300);
    }
}
