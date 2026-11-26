<?php

namespace App\Services\Islands;

use App\Jobs\WarmIslandBelt;
use App\Models\Island;
use App\Models\RenderedImage;
use App\Support\Islands;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Data + textures of the WebGL belt on /islands (resources/js/islands/belt/*; KB notes/islands-build.md section D).
 *
 *  manifest()  compact list of every island (id, rarity, name, residents, owner, updated, atlas flag, sprite key) plus
 *              the texture atlas pages. Cached; one request feeds the whole scene (2,000 islands = about 25 KB gzip).
 *  atlas       far LOD: the transparent island renders (static renderer: nobg.png = scene without sky when the island
 *              has a sky layer, else full.png) scaled into 96x128 cells of
 *              2048x2048 WebP pages, 336 islands per page. The cell of an island is fixed by its id (page = (id-1)/336),
 *              so a page only changes when one of ITS islands is re-rendered. File name = content hash -> immutable.
 *  sprite      near LOD: one island at 512 px wide, built on demand, immutable per render hash.
 *
 * Same-origin on purpose: the render bucket sends no CORS headers, so WebGL can't sample those images directly.
 * Cells and atlases are built by warm() (job WarmIslandBelt / `islands:belt-warm`), never while a visitor waits,
 * except for a small backlog (config islands.belt.sync_cells) so a fresh island shows up at once.
 */
class IslandBelt
{
    public const VERSION = 1;

    public const RARITY_INDEX = ['common' => 0, 'epic' => 1, 'legendary' => 2];

    private const MANIFEST_KEY = 'islands:belt:manifest:v1';

    // ------------------------------------------------------------------------------------------------ geometry

    /** @return array{w:int,h:int,size:int,cols:int,rows:int,per:int,pad:int} */
    public function grid(): array
    {
        $w = max(16, (int) config('islands.belt.cell.0', 96));
        $h = max(16, (int) config('islands.belt.cell.1', 128));
        $size = max(256, (int) config('islands.belt.atlas_size', 2048));
        $cols = intdiv($size, $w);
        $rows = intdiv($size, $h);

        return ['w' => $w, 'h' => $h, 'size' => $size, 'cols' => $cols, 'rows' => $rows, 'per' => $cols * $rows, 'pad' => 2];
    }

    public function pageOf(int $id): int
    {
        return intdiv(max(1, $id) - 1, $this->grid()['per']);
    }

    public function dir(string $sub = ''): string
    {
        $d = rtrim((string) config('islands.belt.dir', storage_path('app/islands-belt')), '/').($sub !== '' ? '/'.$sub : '');
        if (! is_dir($d)) {
            @mkdir($d, 0775, true);
        }

        return $d;
    }

    public static function imageSupport(): bool
    {
        return function_exists('imagecreatefromstring') && function_exists('imagewebp');
    }

    // ------------------------------------------------------------------------------------------------ manifest

    public function manifest(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::MANIFEST_KEY);
        }

        return Cache::remember(self::MANIFEST_KEY, max(1, (int) config('islands.belt.cache_seconds', 30)), fn () => $this->build());
    }

    public function forget(): void
    {
        Cache::forget(self::MANIFEST_KEY);
    }

    protected function build(): array
    {
        $g = $this->grid();
        $renders = $this->renderKeys();
        $state = $this->state();
        $backlog = 0;
        foreach ($renders as $id => $key) {
            if (($state['pages'][$this->pageOf($id)]['cells'][$id] ?? null) !== $key) {
                $backlog++;
            }
        }
        if ($backlog > 0 && self::imageSupport()) {
            if ($backlog <= (int) config('islands.belt.sync_cells', 12)) {
                try {
                    $this->warm($backlog, 8.0);
                    $state = $this->state();
                } catch (Throwable $e) {
                    report($e);
                }
            } elseif (Cache::add('islands:belt:warm-queued', 1, 60)) {
                WarmIslandBelt::dispatch();
            }
        }

        $owners = [];
        $rows = [];
        $counts = ['common' => 0, 'epic' => 0, 'legendary' => 0];
        $updated = 0;
        foreach (Island::query()->orderBy('flow_id')->get(['flow_id', 'rarity', 'name', 'flow_address', 'residents_count', 'updated_at']) as $i) {
            $id = (int) $i->flow_id;
            $owner = (string) $i->flow_address;
            $o = -1;
            if ($owner !== '') {
                $o = $owners[$owner] ??= count($owners);
            }
            $ts = (int) optional($i->updated_at)->getTimestamp();
            $updated = max($updated, $ts);
            $counts[$i->rarity] = ($counts[$i->rarity] ?? 0) + 1;
            $rows[] = [
                $id, self::RARITY_INDEX[$i->rarity] ?? 0, trim((string) $i->name), (int) $i->residents_count, $o, $ts,
                isset($state['pages'][$this->pageOf($id)]['cells'][$id]) ? 1 : 0,
                $renders[$id] ?? '',
            ];
        }
        $atlases = [];
        foreach ($state['pages'] as $page => $p) {
            if (($p['hash'] ?? '') !== '' && is_file($this->dir().'/atlas-'.$page.'-'.$p['hash'].'.webp')) {
                $atlases[(string) $page] = '/api/islands/belt/atlas/'.$page.'-'.$p['hash'].'.webp';
            }
        }

        return [
            'v' => self::VERSION,
            'prefix' => Islands::designationPrefix(),
            'count' => count($rows),
            'counts' => $counts,
            'updated' => $updated,
            'capacity' => array_combine(Islands::RARITIES, array_map(fn ($r) => Islands::capacity($r), Islands::RARITIES)),
            'cell' => ['w' => $g['w'], 'h' => $g['h'], 'cols' => $g['cols'], 'rows' => $g['rows'], 'per' => $g['per'], 'size' => $g['size']],
            'atlases' => (object) $atlases,
            'sprite' => '/api/islands/belt/sprite/{id}-{key}.webp',
            'owners' => array_keys($owners),
            // [id, rarity (0 common, 1 epic, 2 legendary), name ('' = unnamed), residents, owner index (-1 none),
            //  updated (unix), 1 = has a cell in its atlas page, sprite key ('' = no render yet)]
            'islands' => $rows,
        ];
    }

    /** flow_id => render key (directory name of the current static render) for every island that has a full.png. */
    public function renderKeys(): array
    {
        $out = [];
        try {
            $rows = RenderedImage::query()->where('item_type', 'island')->whereNotNull('rendered_at')->get(['flow_id', 'path', 'variants']);
        } catch (Throwable $e) {
            return $out; // table missing before migrate
        }
        foreach ($rows as $r) {
            if (! $r->path || ! $r->hasVariant('full.png')) {
                continue;
            }
            $key = preg_replace('/[^a-z0-9]/', '', strtolower(basename((string) $r->path)));
            if ($key !== '') {
                $out[(int) $r->flow_id] = substr($key, 0, 20);
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------------------------------------ state

    /** @return array{v:int,grid:string,pages:array<int,array{hash:string,cells:array<int,string>}>} */
    public function state(): array
    {
        $empty = ['v' => self::VERSION, 'grid' => $this->gridKey(), 'pages' => []];
        $f = $this->dir().'/state.json';
        if (! is_file($f)) {
            return $empty;
        }
        $s = json_decode((string) @file_get_contents($f), true);
        if (! is_array($s) || ($s['v'] ?? 0) !== self::VERSION || ($s['grid'] ?? '') !== $this->gridKey() || ! is_array($s['pages'] ?? null)) {
            return $empty;
        }

        return $s;
    }

    protected function gridKey(): string
    {
        $g = $this->grid();

        return $g['w'].'x'.$g['h'].'@'.$g['size'].'p'.$g['pad'];
    }

    // ------------------------------------------------------------------------------------------------ warm

    /**
     * Create missing cells (at most $maxFetch render downloads, within $budget seconds) and (re)compose the atlas pages
     * whose content changed. Safe to run concurrently (lock) and repeatedly (no-op when nothing changed).
     *
     * @return array{cells:int,atlases:int,pending:int,skipped?:string}
     */
    public function warm(int $maxFetch = 400, float $budget = 45.0): array
    {
        if (! self::imageSupport()) {
            return ['cells' => 0, 'atlases' => 0, 'pending' => 0, 'skipped' => 'gd-webp-missing'];
        }
        $lock = Cache::lock('islands:belt:warm', 180);
        if (! $lock->get()) {
            return ['cells' => 0, 'atlases' => 0, 'pending' => 0, 'skipped' => 'locked'];
        }
        try {
            $t0 = microtime(true);
            $g = $this->grid();
            $renders = $this->renderKeys();
            $cellsDir = $this->dir('cells');
            $made = 0;
            $pending = 0;
            $have = [];
            foreach ($renders as $id => $key) {
                $file = $cellsDir.'/'.$id.'-'.$key.'.png';
                if (! is_file($file)) {
                    if ($made >= $maxFetch || (microtime(true) - $t0) > $budget) {
                        $pending++;

                        continue;
                    }
                    if (! $this->makeCell($id, $file, $g)) {
                        continue;
                    }
                    $made++;
                    foreach (glob($cellsDir.'/'.$id.'-*.png') ?: [] as $old) {
                        if ($old !== $file) {
                            @unlink($old);
                        }
                    }
                }
                $have[$this->pageOf($id)][$id] = $key;
            }

            $old = $this->state();
            $pages = [];
            $built = 0;
            foreach ($have as $page => $cells) {
                ksort($cells);
                $hash = substr(sha1(self::VERSION.'|'.$this->gridKey().'|'.json_encode($cells)), 0, 12);
                $file = $this->dir().'/atlas-'.$page.'-'.$hash.'.webp';
                if (! is_file($file)) {
                    $this->composeAtlas($page, $cells, $file, $g);
                    $built++;
                }
                $pages[$page] = ['hash' => $hash, 'cells' => $cells];
                // keep the previous file for a day (cached manifests may still point at it)
                foreach (glob($this->dir().'/atlas-'.$page.'-*.webp') ?: [] as $f) {
                    if ($f !== $file && filemtime($f) < time() - 86400) {
                        @unlink($f);
                    }
                }
            }
            ksort($pages);
            if ($pages !== $old['pages']) {
                $tmp = $this->dir().'/state.json.'.getmypid().'.tmp';
                file_put_contents($tmp, json_encode(['v' => self::VERSION, 'grid' => $this->gridKey(), 'pages' => $pages]));
                rename($tmp, $this->dir().'/state.json');
                $this->forget();
            }

            return ['cells' => $made, 'atlases' => $built, 'pending' => $pending];
        } finally {
            $lock->release();
        }
    }

    protected function renderBytes(int $id): ?string
    {
        $r = RenderedImage::query()->where('item_type', 'island')->where('flow_id', $id)->whereNotNull('rendered_at')->first();
        if (! $r || ! $r->path || ! $r->hasVariant('full.png')) {
            return null;
        }
        try {
            // nobg.png = the scene without its sky layer (only rendered for islands that have one): the cut-out
            $bytes = Storage::disk(config('render.disk'))->get($r->path.'/'.($r->hasVariant('nobg.png') ? 'nobg.png' : 'full.png'));
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        return is_string($bytes) && $bytes !== '' ? $bytes : null;
    }

    /** Transparent canvas with the render scaled to fit (aspect kept, centred, $pad px gutter). */
    protected function scaled(string $bytes, int $w, int $h, int $pad)
    {
        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            return null;
        }
        imagepalettetotruecolor($src);
        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        $sw = imagesx($src);
        $sh = imagesy($src);
        $k = min(($w - 2 * $pad) / $sw, ($h - 2 * $pad) / $sh);
        $dw = max(1, (int) round($sw * $k));
        $dh = max(1, (int) round($sh * $k));
        imagecopyresampled($dst, $src, intdiv($w - $dw, 2), intdiv($h - $dh, 2), 0, 0, $dw, $dh, $sw, $sh);
        imagedestroy($src);

        return $dst;
    }

    protected function makeCell(int $id, string $file, array $g): bool
    {
        $bytes = $this->renderBytes($id);
        $im = $bytes ? $this->scaled($bytes, $g['w'], $g['h'], $g['pad']) : null;
        if (! $im) {
            return false;
        }
        $ok = imagepng($im, $file.'.tmp', 6) && rename($file.'.tmp', $file);
        imagedestroy($im);

        return $ok;
    }

    protected function composeAtlas(int $page, array $cells, string $file, array $g): void
    {
        $im = imagecreatetruecolor($g['size'], $g['size']);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        foreach ($cells as $id => $key) {
            $cell = @imagecreatefrompng($this->dir('cells').'/'.$id.'-'.$key.'.png');
            if (! $cell) {
                continue;
            }
            $slot = ((int) $id - 1) % $g['per'];
            imagecopy($im, $cell, ($slot % $g['cols']) * $g['w'], intdiv($slot, $g['cols']) * $g['h'], 0, 0, $g['w'], $g['h']);
            imagedestroy($cell);
        }
        imagewebp($im, $file.'.tmp', (int) config('islands.belt.webp_quality', 90));
        imagedestroy($im);
        rename($file.'.tmp', $file);
    }

    // ------------------------------------------------------------------------------------------------ files

    public function atlasFile(int $page, string $hash): ?string
    {
        $f = $this->dir().'/atlas-'.$page.'-'.$hash.'.webp';

        return is_file($f) ? $f : null;
    }

    /** Near-LOD sprite of one island for its CURRENT render ($key must match; old keys 404). Built on first request. */
    public function spriteFile(int $id, string $key): ?string
    {
        if (! self::imageSupport() || ($this->renderKeys()[$id] ?? null) !== $key) {
            return null;
        }
        $dir = $this->dir('sprites');
        $file = $dir.'/'.$id.'-'.$key.'.webp';
        if (is_file($file)) {
            return $file;
        }
        $lock = Cache::lock('islands:belt:sprite:'.$id, 30);
        try {
            $lock->block(10);
        } catch (Throwable $e) {
            return is_file($file) ? $file : null;
        }
        try {
            if (is_file($file)) {
                return $file;
            }
            $w = max(64, (int) config('islands.belt.sprite_width', 512));
            $bytes = $this->renderBytes($id);
            $im = $bytes ? $this->scaled($bytes, $w, (int) round($w * 4 / 3), 0) : null;
            if (! $im) {
                return null;
            }
            $tmp = $file.'.'.getmypid().'.tmp';
            imagewebp($im, $tmp, (int) config('islands.belt.webp_quality', 90));
            imagedestroy($im);
            rename($tmp, $file);
            foreach (glob($dir.'/'.$id.'-*.webp') ?: [] as $old) {
                if ($old !== $file) {
                    @unlink($old);
                }
            }

            return $file;
        } finally {
            $lock->release();
        }
    }
}
