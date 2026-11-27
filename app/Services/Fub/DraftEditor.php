<?php

namespace App\Services\Fub;

use App\Models\FubDraft;
use App\Models\FubDraftItem;
use App\Models\FubDraftLayer;
use App\Models\FubToolCall;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The ONE domain layer for FUB drafts (FUB-PLAN §d.3 "one domain layer, three doors"): the manual studio (session),
 * the draft-tool API used by the design assistant (server token + actor) and tests all call these methods with the
 * acting wallet address. Every write is authorised against the draft owner, validated with the contract's own rules,
 * SVG goes through SvgSanitizer, and every call is logged (fub_tool_calls) with an undo hint.
 *
 * Nothing here can commit, publish, spend or touch another wallet's draft: those are wallet signatures in the browser.
 */
class DraftEditor
{
    public const RARITIES = ['common', 'rare', 'epic', 'legendary'];

    public const LICENSES = ['holder-commercial', 'personal', 'cc0'];

    public function __construct(protected SvgSanitizer $sanitizer, protected ?FubChain $chain = null)
    {
    }

    // ------------------------------------------------------------------------------------------------- guards

    public function authorize(?string $actor, FubDraft $d): void
    {
        if (! $d->isOwnedBy($actor)) {
            throw new AuthorizationException('This draft belongs to another wallet');
        }
    }

    private function editable(FubDraft $d, bool $structure = true): void
    {
        if ($d->status === 'abandoned') {
            throw ValidationException::withMessages(['draft' => 'This draft was deleted']);
        }
        if ($structure && $d->status === 'published') {
            throw ValidationException::withMessages(['draft' => 'Published FUBs are frozen: you can only add new items to free slots in existing layers']);
        }
    }

    private function log(FubDraft $d, string $actor, string $source, string $tool, bool $ok, string $summary, ?array $undo = null): void
    {
        FubToolCall::create(['fub_draft_id' => $d->id, 'actor' => strtolower($actor), 'source' => $source, 'tool' => $tool, 'ok' => $ok,
            'summary' => substr($summary, 0, 300), 'undo' => $undo]);
    }

    private function touch(FubDraft $d): void
    {
        $d->revision++;
        if ($d->chain_fub_id && $d->status !== 'abandoned') {
            $d->sync_state = 'pending';
        }
        $d->save();
    }

    /** Names: printable, no < > & or control characters (the contract rule), UTF-8 allowed. */
    public static function textRule(int $max, bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'max:'.$max, 'not_regex:/[<>&\x00-\x1F\x7F]/u'];
    }

    // ------------------------------------------------------------------------------------------------- drafts

    public function createDraft(string $actor, array $in, string $source = 'studio'): FubDraft
    {
        $v = Validator::make($in, ['name' => self::textRule(40), 'description' => self::textRule(500, false)])->validate();
        if (FubDraft::where('owner_address', strtolower($actor))->whereIn('status', ['sketch', 'committed'])->count() >= 20) {
            throw ValidationException::withMessages(['draft' => 'Too many open drafts (20)']);
        }
        $d = FubDraft::create(['owner_address' => strtolower($actor), 'name' => $v['name'], 'description' => $v['description'] ?? '',
            'settings' => ['royalty' => 0.05, 'unique' => true, 'license' => 'holder-commercial']]);
        $this->log($d, $actor, $source, 'create_draft', true, 'Created draft "'.$d->name.'"');

        return $d;
    }

    public function updateDraft(string $actor, FubDraft $d, array $in, string $source = 'studio'): FubDraft
    {
        $this->authorize($actor, $d);
        $this->editable($d);
        $v = Validator::make($in, [
            'name' => self::textRule(40, false), 'description' => self::textRule(500, false),
            'royalty' => ['nullable', 'numeric', 'min:0', 'max:0.1'], 'unique' => ['nullable', 'boolean'],
            'max_supply' => ['nullable', 'integer', 'min:1', 'max:375000'], 'license' => ['nullable', 'in:'.implode(',', self::LICENSES)],
        ])->validate();
        $before = ['name' => $d->name, 'description' => $d->description, 'settings' => $d->settings];
        foreach (['name', 'description'] as $k) {
            if (array_key_exists($k, $v) && $v[$k] !== null) {
                $d->$k = $v[$k];
            }
        }
        $s = $d->settings ?? [];
        foreach (['royalty', 'unique', 'max_supply', 'license'] as $k) {
            if (array_key_exists($k, $v) && $v[$k] !== null) {
                $s[$k] = $k === 'royalty' ? round((float) $v[$k], 4) : ($k === 'unique' ? (bool) $v[$k] : ($k === 'max_supply' ? (int) $v[$k] : $v[$k]));
            }
        }
        $d->settings = $s;
        $this->touch($d);
        $this->log($d, $actor, $source, 'update_settings', true, 'Updated settings', ['restore' => $before]);

        return $d;
    }

    /** Delete-now (Q22): the draft and its private originals are removed; a committed FUB stays on chain unpublished. */
    public function abandon(string $actor, FubDraft $d, bool $confirmed, string $source = 'studio'): void
    {
        $this->authorize($actor, $d);
        if (! $confirmed) {
            throw ValidationException::withMessages(['confirm' => 'Deleting a draft needs confirmation']);
        }
        DB::transaction(function () use ($d) {
            $d->items()->delete();
            $d->layers()->delete();
            $d->update(['status' => 'abandoned', 'description' => null, 'settings' => null, 'sync_state' => 'idle']);
        });
        $this->log($d, $actor, $source, 'delete_draft', true, 'Deleted draft');
    }

    // ------------------------------------------------------------------------------------------------- layers

    public function addLayer(string $actor, FubDraft $d, array $in, string $source = 'studio'): FubDraftLayer
    {
        $this->authorize($actor, $d);
        $this->editable($d);
        $v = Validator::make($in, ['name' => self::textRule(24), 'required' => ['nullable', 'boolean'], 'key' => ['nullable', 'regex:/^[a-z0-9_-]{1,16}$/']])->validate();
        $n = $d->layers()->count();
        if ($n >= $this->maxLayers($d)) {
            throw ValidationException::withMessages(['layers' => 'No more layers for this FUB tier']);
        }
        $key = $v['key'] ?? null;
        if (! $key) {
            $i = $n + 1;
            while ($d->layers()->where('key', 'l'.$i)->exists()) {
                $i++;
            }
            $key = 'l'.$i;
        }
        if ($d->layers()->where('key', $key)->exists()) {
            throw ValidationException::withMessages(['key' => 'This layer key exists already']);
        }
        $l = $d->layers()->create(['key' => $key, 'name' => $v['name'], 'required' => (bool) ($v['required'] ?? true), 'position' => $n]);
        $this->touch($d);
        $this->log($d, $actor, $source, 'add_layer', true, 'Added layer "'.$l->name.'"', ['delete_layer' => $key]);

        return $l;
    }

    public function updateLayer(string $actor, FubDraft $d, string $key, array $in, string $source = 'studio'): FubDraftLayer
    {
        $this->authorize($actor, $d);
        $this->editable($d);
        $l = $d->layers()->where('key', $key)->firstOrFail();
        $v = Validator::make($in, ['name' => self::textRule(24, false), 'required' => ['nullable', 'boolean']])->validate();
        $before = ['name' => $l->name, 'required' => $l->required];
        $l->fill(array_filter(['name' => $v['name'] ?? null, 'required' => $v['required'] ?? null], fn ($x) => $x !== null))->save();
        $this->touch($d);
        $this->log($d, $actor, $source, 'update_layer', true, 'Updated layer "'.$l->name.'"', ['restore_layer' => [$key => $before]]);

        return $l;
    }

    /**
     * Reorder from request input (studio and assistant tool API): either the full order {keys: [back ... front]} or a
     * single move {key, position} (0 = drawn first = the back). Layers are drawn in order, so the first layer is the back.
     */
    public function reorder(string $actor, FubDraft $d, array $in, string $source = 'studio'): void
    {
        if (array_key_exists('keys', $in)) {
            if (! is_array($in['keys'])) {
                throw ValidationException::withMessages(['keys' => 'keys must be the list of every layer key, back to front']);
            }
            $this->reorderLayers($actor, $d, $in['keys'], $source);

            return;
        }
        $v = Validator::make($in, ['key' => ['required', 'string', 'max:16'], 'position' => ['required', 'integer']])->validate();
        $this->moveLayer($actor, $d, $v['key'], (int) $v['position'], $source);
    }

    /** Full new drawing order: every layer key exactly once, first = back. Same layer count, so tier caps can't be exceeded. */
    public function reorderLayers(string $actor, FubDraft $d, array $keys, string $source = 'studio'): void
    {
        $this->authorize($actor, $d);
        $this->editable($d);
        $old = $d->layers()->pluck('key')->all();
        if (! array_is_list($keys) || collect($keys)->contains(fn ($k) => ! is_string($k))) {
            throw ValidationException::withMessages(['keys' => 'keys must be a list of layer keys (strings), back to front']);
        }
        $have = collect($old)->sort()->values()->all();
        $want = collect($keys)->sort()->values()->all();
        if ($have !== $want) {
            throw ValidationException::withMessages(['keys' => 'Give every layer key exactly once (have: '.implode(', ', $old).')']);
        }
        if ($keys === $old) {
            return; // already in this order: no revision bump, no chain sync
        }
        DB::transaction(function () use ($d, $keys) {
            foreach ($keys as $i => $k) {
                $d->layers()->where('key', $k)->update(['position' => $i]);
            }
        });
        $this->touch($d);
        $this->log($d, $actor, $source, 'reorder_layers', true, 'Reordered layers: '.implode(', ', $keys), ['reorder' => $old]);
    }

    /** Move one layer to a 0-based position in the drawing order (0 = back, layer count - 1 = front). */
    public function moveLayer(string $actor, FubDraft $d, string $key, int $position, string $source = 'studio'): void
    {
        $this->authorize($actor, $d);
        $keys = $d->layers()->pluck('key')->all();
        $from = array_search($key, $keys, true);
        if ($from === false) {
            throw ValidationException::withMessages(['key' => 'No layer "'.$key.'" in this draft (have: '.implode(', ', $keys).')']);
        }
        if ($position < 0 || $position >= count($keys)) {
            throw ValidationException::withMessages(['position' => 'position must be between 0 (back) and '.(count($keys) - 1).' (front)']);
        }
        array_splice($keys, $from, 1);
        array_splice($keys, $position, 0, [$key]);
        $this->reorderLayers($actor, $d, $keys, $source);
    }

    public function deleteLayer(string $actor, FubDraft $d, string $key, bool $confirmed, string $source = 'studio'): void
    {
        $this->authorize($actor, $d);
        $this->editable($d);
        if (! $confirmed) {
            throw ValidationException::withMessages(['confirm' => 'Deleting a layer (and its items) needs confirmation']);
        }
        $l = $d->layers()->where('key', $key)->firstOrFail();
        $n = $d->items()->where('layer_key', $key)->where('chain_state', '!=', 'deleted')->count();
        DB::transaction(function () use ($d, $l, $key) {
            $d->items()->where('layer_key', $key)->whereNull('chain_item_id')->delete();
            $d->items()->where('layer_key', $key)->whereNotNull('chain_item_id')->update(['chain_state' => 'deleted']);
            $l->delete();
            foreach ($d->layers()->get() as $i => $x) {
                $x->update(['position' => $i]);
            }
        });
        $this->touch($d);
        $this->log($d, $actor, $source, 'delete_layer', true, 'Deleted layer "'.$l->name.'" and '.$n.' items');
    }

    // ------------------------------------------------------------------------------------------------- items

    private function itemRules(bool $partial): array
    {
        $r = $partial ? 'sometimes' : 'required';

        return [
            'layer' => [$r, 'regex:/^[a-z0-9_-]{1,16}$/'],
            'name' => array_merge([$r], array_slice(self::textRule(32), 1)),
            'description' => self::textRule(200, false),
            'rarity' => ['nullable', 'in:'.implode(',', self::RARITIES)],
            'price' => [$r, 'numeric', 'min:1', 'max:10000000'],
            'max_mint' => ['nullable', 'integer', 'min:0', 'max:375000'],
            'increase' => ['nullable', 'numeric', 'min:0', 'max:0.1'],
            'price_cap' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'large' => ['nullable', 'boolean'],
            'ai_assisted' => ['nullable', 'boolean'],
        ];
    }

    private function checkCurve(array $v, ?FubDraftItem $it = null): void
    {
        $price = (float) ($v['price'] ?? $it?->price ?? 0);
        $inc = (float) ($v['increase'] ?? $it?->increase ?? 0);
        $cap = (float) ($v['price_cap'] ?? $it?->price_cap ?? 0);
        if ($inc > 0 && ($cap < $price)) {
            throw ValidationException::withMessages(['price_cap' => 'A price curve needs a ceiling at or above the price']);
        }
    }

    public function createItem(string $actor, FubDraft $d, array $in, string $source = 'studio'): array
    {
        $this->authorize($actor, $d);
        $this->editable($d, false);
        $v = Validator::make($in, $this->itemRules(false) + ['svg' => ['required', 'string', 'max:'.SvgSanitizer::MAX_INPUT]])->validate();
        $this->checkCurve($v);
        if (! $d->layers()->where('key', $v['layer'])->exists()) {
            throw ValidationException::withMessages(['layer' => 'Unknown layer']);
        }
        $live = $d->items()->where('chain_state', '!=', 'deleted')->count();
        if ($live >= $this->slots($d)) {
            throw ValidationException::withMessages(['items' => 'No free slots left in this FUB']);
        }

        return DB::transaction(function () use ($actor, $d, $v, $source, $live) {
            $it = $d->items()->create([
                'layer_key' => $v['layer'], 'name' => $v['name'], 'description' => $v['description'] ?? '', 'rarity' => $v['rarity'] ?? 'common',
                'price' => $v['price'], 'max_mint' => (int) ($v['max_mint'] ?? 0), 'increase' => (float) ($v['increase'] ?? 0),
                'price_cap' => (float) ($v['price_cap'] ?? $v['price']), 'large' => (bool) ($v['large'] ?? false), 'svg' => '', 'sha3' => '', 'size' => 0,
                'ai_assisted' => (bool) ($v['ai_assisted'] ?? false) || $source === 'assistant', 'position' => $live,
            ]);
            $r = $this->sanitizer->sanitize($v['svg'], $it->ns(), (bool) ($v['large'] ?? false));
            if (! $r['ok']) {
                throw ValidationException::withMessages(['svg' => $r['errors']]);
            }
            $this->checkLargeAllowance($d, $r['large'], null);
            $it->update(['svg' => $r['svg'], 'svg_original' => $v['svg'], 'sha3' => $r['sha3'], 'size' => $r['size'], 'large' => $r['large'] || (bool) ($v['large'] ?? false),
                'report' => self::report($r)]);
            $this->touch($d);
            $this->log($d, $actor, $source, 'create_item', true, 'Added item "'.$it->name.'" ('.$r['size'].' B)', ['delete_item' => $it->id]);

            return ['item' => $it->fresh(), 'report' => self::report($r)];
        });
    }

    public function updateItem(string $actor, FubDraft $d, FubDraftItem $it, array $in, string $source = 'studio'): FubDraftItem
    {
        $this->authorize($actor, $d);
        $this->itemBelongs($d, $it);
        $this->editable($d, false);
        $this->notLive($d, $it);
        $v = Validator::make($in, $this->itemRules(true))->validate();
        $this->checkCurve($v, $it);
        if (isset($v['layer']) && ! $d->layers()->where('key', $v['layer'])->exists()) {
            throw ValidationException::withMessages(['layer' => 'Unknown layer']);
        }
        $before = $it->only(['layer_key', 'name', 'description', 'rarity', 'price', 'max_mint', 'increase', 'price_cap']);
        $map = ['layer' => 'layer_key', 'name' => 'name', 'description' => 'description', 'rarity' => 'rarity', 'price' => 'price',
            'max_mint' => 'max_mint', 'increase' => 'increase', 'price_cap' => 'price_cap', 'ai_assisted' => 'ai_assisted'];
        foreach ($map as $k => $col) {
            if (array_key_exists($k, $v) && $v[$k] !== null) {
                $it->$col = $v[$k];
            }
        }
        if ($source === 'assistant') {
            $it->ai_assisted = true;
        }
        if ($it->chain_item_id) {
            $it->chain_state = 'dirty';
        }
        $it->save();
        $this->touch($d);
        $this->log($d, $actor, $source, 'update_item', true, 'Updated item "'.$it->name.'"', ['restore_item' => [$it->id => $before]]);

        return $it;
    }

    public function replaceItemSvg(string $actor, FubDraft $d, FubDraftItem $it, string $svg, ?bool $large = null, string $source = 'studio'): array
    {
        $this->authorize($actor, $d);
        $this->itemBelongs($d, $it);
        $this->editable($d, false);
        $this->notLive($d, $it);
        if (strlen($svg) > SvgSanitizer::MAX_INPUT) {
            throw ValidationException::withMessages(['svg' => 'The file is larger than 512 KB']);
        }
        $r = $this->sanitizer->sanitize($svg, $it->ns(), (bool) ($large ?? $it->large));
        if (! $r['ok']) {
            throw ValidationException::withMessages(['svg' => $r['errors']]);
        }
        $this->checkLargeAllowance($d, $r['large'], $it);
        $prev = $it->svg;
        $it->update(['svg' => $r['svg'], 'svg_original' => $svg, 'sha3' => $r['sha3'], 'size' => $r['size'], 'large' => $r['large'] || (bool) $large,
            'report' => self::report($r), 'chain_state' => $it->chain_item_id ? 'dirty' : 'new', 'ai_assisted' => $it->ai_assisted || $source === 'assistant']);
        $this->touch($d);
        $this->log($d, $actor, $source, 'replace_item_svg', true, 'New art for "'.$it->name.'" ('.$r['size'].' B)', ['svg_sha3' => hash('sha3-256', $prev)]);

        return ['item' => $it->fresh(), 'report' => self::report($r)];
    }

    public function deleteItem(string $actor, FubDraft $d, FubDraftItem $it, bool $confirmed, string $source = 'studio'): void
    {
        $this->authorize($actor, $d);
        $this->itemBelongs($d, $it);
        $this->editable($d, false);
        $this->notLive($d, $it);
        if (! $confirmed) {
            throw ValidationException::withMessages(['confirm' => 'Deleting an item needs confirmation']);
        }
        if ($it->chain_item_id) {
            $it->update(['chain_state' => 'deleted']);
        } else {
            $it->delete();
        }
        $this->touch($d);
        $this->log($d, $actor, $source, 'delete_item', true, 'Deleted item "'.$it->name.'"');
    }

    public function validateSvg(string $svg, bool $large = false): array
    {
        $r = $this->sanitizer->sanitize($svg, 'u0', $large);

        return self::report($r) + ['svg' => $r['ok'] ? $r['svg'] : null];
    }

    public static function report(array $r): array
    {
        return ['ok' => $r['ok'], 'size' => $r['size'], 'sha3' => $r['sha3'], 'large' => $r['large'], 'errors' => array_values($r['errors']),
            'removed' => array_values($r['removed']), 'dangerous' => $r['dangerous'], 'warnings' => array_values($r['warnings']),
            'stats' => $r['stats'], 'version' => $r['version']];
    }

    private function itemBelongs(FubDraft $d, FubDraftItem $it): void
    {
        if ((int) $it->fub_draft_id !== (int) $d->id || $it->chain_state === 'deleted') {
            throw new AuthorizationException('Item not in this draft');
        }
    }

    /** Items written before publish are live after it: never editable again (only staged additions are). */
    private function notLive(FubDraft $d, FubDraftItem $it): void
    {
        if ($d->status === 'published' && $it->chain_item_id && $it->chain_state !== 'new') {
            $chainItem = \App\Models\FubItem::where('flow_id', $it->chain_item_id)->first();
            if (! $chainItem || $chainItem->live) {
                throw ValidationException::withMessages(['item' => 'Published items can never change']);
            }
        }
    }

    private function checkLargeAllowance(FubDraft $d, bool $large, ?FubDraftItem $except): void
    {
        if (! $large) {
            return;
        }
        $n = $d->items()->where('large', true)->where('chain_state', '!=', 'deleted')->when($except, fn ($q) => $q->where('id', '!=', $except->id))->count();
        if ($n >= $this->tierValue($d, 'largeItems', 3)) {
            throw ValidationException::withMessages(['svg' => 'No large-item allowance left for this FUB tier']);
        }
    }

    // ------------------------------------------------------------------------------------------------- budget / snapshot

    public function tier(FubDraft $d): string
    {
        $f = $d->chain_fub_id ? \App\Models\Fub::where('flow_id', $d->chain_fub_id)->first() : null;

        return $f?->tier ?? (string) ($d->setting('tier') ?? 'common');
    }

    private function chainConfig(): array
    {
        try {
            return ($this->chain ??= app(FubChain::class))->config();
        } catch (\Throwable $e) {
            return ['tiers' => [], 'itemMax' => SvgSanitizer::ITEM_MAX, 'largeItemMax' => SvgSanitizer::LARGE_MAX, 'svgOpen' => ''];
        }
    }

    private function tierValue(FubDraft $d, string $k, int $def): int
    {
        return (int) ($this->chainConfig()['tiers'][$this->tier($d)][$k] ?? $def);
    }

    public function maxLayers(FubDraft $d): int
    {
        return $this->tierValue($d, 'layers', 8);
    }

    public function slots(FubDraft $d): int
    {
        $f = $d->chain_fub_id ? \App\Models\Fub::where('flow_id', $d->chain_fub_id)->first() : null;

        return (int) ($f?->slots ?? $d->setting('slots', 25));
    }

    public function budget(FubDraft $d): array
    {
        $items = $d->items()->where('chain_state', '!=', 'deleted')->get();
        $layers = $d->layers()->get();
        $perLayer = [];
        foreach ($layers as $l) {
            $perLayer[$l->key] = (int) $items->where('layer_key', $l->key)->max('size');
        }
        $cfg = $this->chainConfig();
        $wrapper = strlen((string) ($cfg['svgOpen'] ?? '')) + 6 + 48 * $layers->count();

        return [
            'tier' => $this->tier($d), 'slots' => $this->slots($d), 'used' => $items->count(),
            'layers' => $layers->count(), 'maxLayers' => $this->maxLayers($d),
            'largeItems' => $items->where('large', true)->count(), 'largeCap' => $this->tierValue($d, 'largeItems', 3),
            'heaviest' => array_sum($perLayer) + $wrapper, 'composedCap' => $this->tierValue($d, 'composed', 120000),
            'perLayer' => $perLayer, 'itemMax' => (int) ($cfg['itemMax'] ?? SvgSanitizer::ITEM_MAX), 'largeItemMax' => (int) ($cfg['largeItemMax'] ?? SvgSanitizer::LARGE_MAX),
        ];
    }

    /** Full draft state (studio + assistant). SVG is NOT included (fetch per item); originals never leave the server. */
    public function snapshot(FubDraft $d): array
    {
        return [
            'id' => $d->id, 'name' => $d->name, 'description' => $d->description, 'status' => $d->status, 'chainFubId' => $d->chain_fub_id,
            'syncState' => $d->sync_state, 'syncError' => $d->sync_error, 'revision' => $d->revision, 'settings' => $d->settings ?? [],
            'layers' => $d->layers()->get()->map(fn ($l) => ['key' => $l->key, 'name' => $l->name, 'required' => (bool) $l->required])->all(),
            'items' => $d->items()->where('chain_state', '!=', 'deleted')->get()->map(fn ($i) => [
                'id' => $i->id, 'layer' => $i->layer_key, 'name' => $i->name, 'description' => $i->description, 'rarity' => $i->rarity,
                'price' => (float) $i->price, 'maxMint' => (int) $i->max_mint, 'increase' => (float) $i->increase, 'priceCap' => (float) $i->price_cap,
                'large' => (bool) $i->large, 'size' => (int) $i->size, 'sha3' => $i->sha3, 'aiAssisted' => (bool) $i->ai_assisted,
                'chainItemId' => $i->chain_item_id, 'chainState' => $i->chain_state, 'warnings' => $i->report['warnings'] ?? [],
            ])->all(),
            'budget' => $this->budget($d),
        ];
    }
}
