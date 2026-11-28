<?php

namespace App\Support;

use App\Models\DustAccessory;
use App\Models\DustCollectible;
use App\Models\DustSeries;
use App\Models\DustTemplate;
use App\Models\Flobot;
use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;

/**
 * ERC-721 metadata JSON (OpenSea / EIP-721 metadata standard) for Flovatar NFTs bridged to Flow EVM (EVM-A, 2026-11).
 *
 * These are the documents behind tokenURI(): the Cadence contracts' EVMBridgedMetadata view points to
 * {host}/flovatars/json/{id}, /components/json/{id}, /flobots/json/{id}, /collectibles/json/{id},
 * /accessories/json/{id} (the bridge copies that URI into the ERC-721 when an item is bridged), and the project
 * ERC-721s use the same paths as baseURI. Collection-level contractURI: /evm/collections/{slug}.json.
 * Output: name, description, image, external_url, attributes[{trait_type, value}], plus a small `properties` block.
 * Never includes SVG strings (images are URLs) so responses stay small and edge-cacheable.
 */
class EvmMetadata
{
    public const DESCRIPTION = 'Flovatar is a co-created, composable avatar collection born on the Flow blockchain. '
        .'This item lives in Cadence and was moved to Flow EVM through the official Flow VM bridge; it can be bridged back at any time.';

    protected static function site(string $path = ''): string
    {
        return rtrim(config('app.url'), '/').'/'.ltrim($path, '/');
    }

    protected static function image(string $model, int $id, string $svgRoute): string
    {
        if (config('evm.image_cdn')) {
            return rtrim(config('flovatar.imagesUrl'), '/')."/{$model}/png/{$id}.png";
        }

        return $svgRoute;
    }

    protected static function trait(string $type, $value, ?string $display = null): array
    {
        $t = ['trait_type' => $type, 'value' => $value];
        if ($display) {
            $t['display_type'] = $display;
        }

        return $t;
    }

    /** @return array<int, FlovatarTemplate> keyed by template flow_id */
    protected static function templates(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (! $ids) {
            return [];
        }

        return FlovatarTemplate::whereIn('flow_id', $ids)->get(['flow_id', 'name', 'category', 'rarity', 'series'])
            ->keyBy('flow_id')->all();
    }

    public static function flovatar(int $id): ?array
    {
        $f = Flovatar::where('flow_id', $id)->first(['flow_id', 'name', 'rare_count', 'epic_count', 'legendary_count', 'flow_metadata']);
        if (! $f) {
            return null;
        }
        $m = collect($f->flow_metadata ?? []);
        $meta = collect($m->get('metadata', []));
        $components = (array) $meta->get('components', []);
        $extra = ['accessory' => $m->get('accessoryId'), 'hat' => $m->get('hatId'), 'eyeglasses' => $m->get('eyeglassesId'), 'background' => $m->get('backgroundId')];
        $tpl = self::templates(array_merge(array_values($components), array_values($extra)));
        $labels = config('flovatar.categories');
        $attrs = [];
        foreach (array_merge($components, $extra) as $cat => $tid) {
            if ($tid === null || $tid === '') {
                continue;
            }
            $t = $tpl[(int) $tid] ?? null;
            $attrs[] = self::trait($labels[$cat] ?? ucfirst($cat), $t?->name ?? ('#'.$tid));
            if ($t && in_array($t->rarity, ['rare', 'epic', 'legendary'])) {
                $attrs[] = self::trait(($labels[$cat] ?? ucfirst($cat)).' rarity', ucfirst($t->rarity));
            }
        }
        foreach (['Rare boosters' => $f->rare_count, 'Epic boosters' => $f->epic_count, 'Legendary boosters' => $f->legendary_count] as $k => $v) {
            if ((int) $v > 0) {
                $attrs[] = self::trait($k, (int) $v, 'number');
            }
        }
        if ($meta->get('mint')) {
            $attrs[] = self::trait('Mint', (int) $meta->get('mint'), 'number');
        }
        $attrs[] = self::trait('Series', (int) ($meta->get('series') ?: 1), 'number');
        $name = trim((string) ($m->get('name') ?: $f->name));

        return [
            'name' => $name !== '' ? "{$name} · Flovatar #{$id}" : "Flovatar #{$id}",
            'description' => self::DESCRIPTION,
            'image' => self::image('flovatar', $id, self::site("api/image/{$id}")),
            'external_url' => self::site("flovatars/{$id}"),
            'attributes' => $attrs,
            'properties' => ['collection' => 'Flovatar', 'cadence_id' => $id, 'combination' => $meta->get('combination'), 'creator' => $meta->get('creatorAddress')],
        ];
    }

    public static function component(int $id): ?array
    {
        $c = FlovatarComponent::with('flovatarTemplate')->where('flow_id', $id)->first();
        if (! $c) {
            return null;
        }
        $t = $c->flovatarTemplate;
        $tid = $t?->flow_id ?? (int) (collect($c->flow_metadata ?? [])->get('templateId'));
        $category = $t?->category;
        $label = config('flovatar.categories.'.$category) ?? config('flovatar.marketplace_categories.'.$category) ?? ucfirst((string) $category);
        $attrs = array_values(array_filter([
            $category ? self::trait('Category', $label) : null,
            $t?->rarity ? self::trait('Rarity', ucfirst($t->rarity)) : null,
            $t?->series ? self::trait('Series', (int) $t->series, 'number') : null,
            $t?->color ? self::trait('Color', $t->color) : null,
            $c->mint ? self::trait('Mint', (int) $c->mint, 'number') : null,
        ]));

        return [
            'name' => ($t?->name ?: 'Flobit').' · Flobit #'.$id,
            'description' => trim(($t?->description ? $t->description.' ' : '').'Flobits are the components (and Sparks/boosters) used to build and upgrade Flovatars.'),
            'image' => self::site('api/image/template/'.$tid),
            'external_url' => self::site('builder'),
            'attributes' => $attrs,
            'properties' => ['collection' => 'Flovatar Component', 'cadence_id' => $id, 'template_id' => $tid],
        ];
    }

    public static function flobot(int $id): ?array
    {
        $f = Flobot::where('flow_id', $id)->first(['flow_id', 'name', 'rarity', 'flow_metadata']);
        if (! $f) {
            return null;
        }
        $m = collect($f->flow_metadata ?? []);
        $meta = collect($m->get('metadata', []));
        $components = (array) $meta->get('components', []);
        $tpl = self::templates(array_merge(array_values($components), [$m->get('backgroundId')]));
        $attrs = [];
        foreach ($components as $cat => $tid) {
            $attrs[] = self::trait(ucfirst($cat), ($tpl[(int) $tid] ?? null)?->name ?? ('#'.$tid));
        }
        if ($bg = $m->get('backgroundId')) {
            $attrs[] = self::trait('Background', ($tpl[(int) $bg] ?? null)?->name ?? ('#'.$bg));
        }
        $rarity = $meta->get('rarity') ?: $f->rarity;
        if ($rarity) {
            $attrs[] = self::trait('Rarity', ucfirst($rarity));
        }
        if ($meta->get('mint')) {
            $attrs[] = self::trait('Mint', (int) $meta->get('mint'), 'number');
        }
        $name = trim((string) ($m->get('name') ?: $f->name));

        return [
            'name' => $name !== '' ? "{$name} · Flobot #{$id}" : "Flobot #{$id}",
            'description' => 'Flobots are the robot companions of the Flovatar universe, assembled from Flobot kits. '.self::DESCRIPTION,
            'image' => self::image('flobot', $id, self::site("api/image/flobot/{$id}")),
            'external_url' => self::site("flobots/{$id}"),
            'attributes' => $attrs,
            'properties' => ['collection' => 'Flobot', 'cadence_id' => $id, 'combination' => $meta->get('combination')],
        ];
    }

    public static function collectible(int $id): ?array
    {
        $d = DustCollectible::where('flow_id', $id)->first(['flow_id', 'name', 'dust_series_id', 'rarity_score', 'flow_metadata']);
        if (! $d) {
            return null;
        }
        $m = collect($d->flow_metadata ?? []);
        $series = DustSeries::where('flow_id', (int) $m->get('series', 1))->first(['name', 'flow_metadata']);
        $layerNames = collect(collect($series?->flow_metadata ?? [])->get('layers', []))->mapWithKeys(fn ($l, $k) => [(string) $k => $l['name'] ?? ('Layer '.$k)]);
        $layers = collect($m->get('layers', []))->map(fn ($v) => is_array($v) ? ($v['value'] ?? null) : $v)->filter();
        $tpl = DustTemplate::whereIn('flow_id', $layers->values()->map(fn ($v) => (int) $v)->all())->get(['flow_id', 'name', 'rarity'])->keyBy('flow_id');
        $attrs = [];
        foreach ($layers as $layer => $tid) {
            $t = $tpl[(int) $tid] ?? null;
            $attrs[] = self::trait($layerNames[(string) $layer] ?? ('Layer '.$layer), $t?->name ?? ('#'.$tid));
        }
        if ($m->get('mint')) {
            $attrs[] = self::trait('Mint', (int) $m->get('mint'), 'number');
        }
        if ($d->rarity_score) {
            $attrs[] = self::trait('Rarity score', (float) $d->rarity_score, 'number');
        }
        $seriesName = $series?->name ?: 'Dust Collectible';
        $name = trim((string) ($m->get('name') ?: $d->name));

        return [
            'name' => $name !== '' ? "{$name} · {$seriesName} #{$id}" : "{$seriesName} #{$id}",
            'description' => "{$seriesName}: a ĐUST collectible of the Flovatar universe. ".self::DESCRIPTION,
            'image' => self::image('collectible', $id, self::site("api/image/collectible/{$id}")),
            'external_url' => self::site("collectibles/{$id}"),
            'attributes' => $attrs,
            'properties' => ['collection' => 'Flovatar Dust Collectible', 'cadence_id' => $id, 'combination' => $m->get('combination')],
        ];
    }

    public static function accessory(int $id): ?array
    {
        $a = DustAccessory::with('dustTemplate')->where('flow_id', $id)->first();
        if (! $a) {
            return null;
        }
        $t = $a->dustTemplate;
        $attrs = array_values(array_filter([
            $t?->rarity ? self::trait('Rarity', ucfirst($t->rarity)) : null,
            $t?->layer ? self::trait('Layer', (int) $t->layer, 'number') : null,
            $a->mint ? self::trait('Mint', (int) $a->mint, 'number') : null,
        ]));

        return [
            'name' => ($t?->name ?: 'Accessory').' · Likee Accessory #'.$id,
            'description' => 'An accessory for the Flovatar ĐUST collectibles (Likee). '.self::DESCRIPTION,
            'image' => $t ? self::site('api/image/collectible/template/'.$t->flow_id) : self::site('images/logo-rounded.png'),
            'external_url' => self::site('dust/accessories/'.$id),
            'attributes' => $attrs,
            'properties' => ['collection' => 'Flovatar Dust Collectible Accessory', 'cadence_id' => $id, 'template_id' => $t?->flow_id],
        ];
    }

    /** contractURI document (OpenSea collection metadata) */
    public static function collection(string $slug): ?array
    {
        $c = config('evm.collections.'.$slug);
        if (! $c) {
            return null;
        }

        return [
            'name' => $c['name'],
            'symbol' => $c['symbol'],
            'description' => 'Flovatar on Flow EVM: '.$c['name'].' items bridged from Cadence through the official Flow VM bridge. '
                .'Royalties: 5% (ERC-2981 on the project ERC-721).',
            'image' => self::site('images/logo-rounded.png'),
            'banner_image' => rtrim(config('flovatar.imagesUrl'), '/').'/logo-horizontal.svg',
            'external_link' => self::site('evm'),
            'seller_fee_basis_points' => (int) config('evm.royalty_bps'),
            'cadence_type' => $c['type'],
        ];
    }
}
