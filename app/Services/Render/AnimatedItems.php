<?php

namespace App\Services\Render;

use App\Models\DustCollectible;
use App\Models\Flobot;
use App\Models\Flovatar;
use App\Models\FlovatarTemplate;

/**
 * Which items carry CSS @keyframes / SMIL (their PNG/JPG should come from the browser renderer;
 * the server render is the instant fallback). SQL LIKE on the stored SVGs + template membership via
 * flow_metadata - no SVG composing, a few seconds for the whole catalogue.
 */
class AnimatedItems
{
    private const LIKE = ['%@keyframes%', '%@-webkit-keyframes%', '%<animate%', '%<set %'];

    private static function whereAnimated($query, string $col = 'svg')
    {
        return $query->where(function ($q) use ($col) {
            foreach (self::LIKE as $like) {
                $q->orWhere($col, 'like', $like);
            }
        });
    }

    /** @return int[] template flow ids */
    public static function templates(): array
    {
        return self::whereAnimated(FlovatarTemplate::query()->whereNotNull('flow_id'))->orderBy('flow_id')->pluck('flow_id')->map(fn ($v) => (int) $v)->all();
    }

    /** @param int[]|null $only restrict to these flow ids (cheap check for a few candidates) @return int[] */
    public static function ids(string $type, ?array $only = null): array
    {
        $limit = fn ($q) => $only === null ? $q : $q->whereIn('flow_id', $only ?: [0]);
        if ($type === 'template') {
            $all = self::templates();

            return $only === null ? $all : array_values(array_intersect($all, $only));
        }
        if ($type === 'collectible') {
            return self::whereAnimated($limit(DustCollectible::query()->whereNotNull('flow_id')))->orderBy('flow_id')->pluck('flow_id')->map(fn ($v) => (int) $v)->all();
        }
        $model = $type === 'flobot' ? Flobot::class : Flovatar::class;
        $keys = $type === 'flobot' ? ['backgroundId', 'accessoryId'] : ['backgroundId', 'accessoryId', 'hatId', 'eyeglassesId'];
        $animatedTemplates = array_flip(self::templates());
        $ids = self::whereAnimated($limit($model::query()->whereNotNull('flow_id')))->pluck('flow_id')->map(fn ($v) => (int) $v)->all();
        $limit($model::query()->whereNotNull('flow_id'))->select(['id', 'flow_id', 'flow_metadata'])->chunkById(1000, function ($rows) use (&$ids, $keys, $animatedTemplates) {
            foreach ($rows as $row) {
                $meta = $row->flow_metadata ?? [];
                foreach ($keys as $k) {
                    if (isset($meta[$k]) && is_numeric($meta[$k]) && isset($animatedTemplates[(int) $meta[$k]])) {
                        $ids[] = (int) $row->flow_id;
                        break;
                    }
                }
            }
        });
        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }
}
