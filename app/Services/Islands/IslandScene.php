<?php

namespace App\Services\Islands;

use App\Models\Island;
use App\Models\IslandTemplate;
use App\Services\Render\SvgFlattener;
use App\Support\Islands;

/**
 * Island SVG composition.
 *
 * scene()  = byte-identical port of FlovatarAsteroidIsland.composeSvg (contract getSvg / preview_svg): svgOpen, optional
 *            tint style + data-island, then `sceneOrder`: "<layer>" → <g id="layer-<layer>">svg</g>, "<layer>:front" →
 *            <g id="layer-<layer>-front">svgFront</g>, "@name" → <g id="name"/> placeholder. The browser builder runs the
 *            same algorithm (resources/js/islands/compose.mjs); tests pin both against the chain output.
 * full()   = scene + PREMIUM residents (Flovatar family, valid only) placed at the art's anchors inside the
 *            "residents-back"/"residents-front" placeholders as nested SVG images (data: URIs, so each resident keeps its
 *            own CSS/ids isolated). For the static renderer ($flatten) every resident is pre-flattened (SvgFlattener),
 *            because resvg does not run CSS combos/animations.
 *
 * Anchors: template metadata of the scene's structure (wins) and base: `anchors` = JSON [{slot,x,y,scale,z,flip}], or
 * per-slot `anchor.P1` = "x,y,scale[,z[,flip]]". Missing → a default row on the island surface.
 */
class IslandScene
{
    public const RESIDENT_CANVAS = 3000;

    public const FOOT = [1500, 2725];

    public function __construct(protected IslandChain $chain, protected ResidentArt $art, protected SvgFlattener $flattener)
    {
    }

    /** chain config (cached) — overridable for tests */
    public ?array $config = null;

    public function config(): array
    {
        return $this->config ??= $this->chain->config();
    }

    /** @param  array<int|string,int>  $ids  template ids  @return array<int, IslandTemplate> */
    public function templates(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (! $ids) {
            return [];
        }

        return IslandTemplate::whereIn('flow_id', $ids)->get()->keyBy('flow_id')->all();
    }

    public static function isHexColor(?string $s): bool
    {
        return is_string($s) && (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $s);
    }

    /** Contract composeSvg port. $templates: flow_id => IslandTemplate|array{svg, svg_front, svg_length, svg_front_length} */
    public function scene(array $layers, ?string $tint, ?int $islandId, ?array $templates = null): string
    {
        $cfg = $this->config();
        $templates ??= $this->templates(array_values($layers));
        $parts = [(string) $cfg['svgOpen']];
        if ($tint !== null && self::isHexColor($tint)) {
            $parts[] = ' style="--island-tint:'.$tint.'"';
        }
        if ($islandId !== null) {
            $parts[] = ' data-island="'.$islandId.'"';
        }
        $parts[] = '>';
        foreach ((array) $cfg['sceneOrder'] as $entry) {
            $entry = (string) $entry;
            if (strlen($entry) > 1 && $entry[0] === '@') {
                $parts[] = '<g id="'.substr($entry, 1).'"/>';

                continue;
            }
            $isFront = strlen($entry) > 6 && str_ends_with($entry, ':front');
            $layer = $isFront ? substr($entry, 0, -6) : $entry;
            if (! isset($layers[$layer])) {
                continue;
            }
            $t = $templates[(int) $layers[$layer]] ?? null;
            if (! $t) {
                continue;
            }
            $len = (int) ($isFront ? data_get($t, 'svg_front_length') : data_get($t, 'svg_length'));
            if ($len > 0) {
                $parts[] = '<g id="layer-'.$layer.($isFront ? '-front' : '').'">';
                $parts[] = (string) ($isFront ? data_get($t, 'svg_front') : data_get($t, 'svg'));
                $parts[] = '</g>';
            }
        }
        $parts[] = '</svg>';

        return implode('', $parts);
    }

    // ------------------------------------------------------------------------------------------------ anchors

    /**
     * Anchors for the premium slots of a scene: ['P1' => [x, y, scale, z, flip], ...] for P1..Pn.
     * Structure metadata overrides base metadata; missing slots get the default row.
     */
    public function anchors(string $rarity, array $layers, ?array $templates = null): array
    {
        $cfg = $this->config();
        $n = (int) ($cfg['premium'][$rarity] ?? config('islands.premium_default.'.$rarity, 3));
        $templates ??= $this->templates(array_values($layers));
        $found = [];
        foreach (['base', 'structure'] as $layer) { // later wins (contract anchorLayers = structure,base)
            $t = isset($layers[$layer]) ? ($templates[(int) $layers[$layer]] ?? null) : null;
            foreach (self::parseAnchors((array) (data_get($t, 'metadata') ?? [])) as $slot => $a) {
                $found[$slot] = $a;
            }
        }
        $out = [];
        $defaults = self::defaultAnchors($rarity, $n);
        for ($i = 1; $i <= $n; $i++) {
            $out['P'.$i] = $found['P'.$i] ?? $defaults['P'.$i];
        }
        if (isset($found['caretaker'])) {
            $out['caretaker'] = $found['caretaker'];
        }

        return $out;
    }

    /** @return array<string, array{0: float, 1: float, 2: float, 3: string, 4: bool}> */
    public static function parseAnchors(array $meta): array
    {
        $out = [];
        $norm = function ($slot, $x, $y, $s, $z, $flip) use (&$out) {
            $slot = strtoupper(trim((string) $slot));
            $slot = $slot === 'CARETAKER' ? 'caretaker' : $slot;
            if (! (preg_match('/^P\d{1,3}$/', $slot) || $slot === 'caretaker') || ! is_numeric($x) || ! is_numeric($y)) {
                return;
            }
            $out[$slot] = [(float) $x, (float) $y, is_numeric($s) && (float) $s > 0 ? (float) $s : 0.0,
                strtolower((string) $z) === 'back' ? 'back' : 'front', filter_var($flip, FILTER_VALIDATE_BOOLEAN)];
        };
        if (isset($meta['anchors'])) {
            $list = json_decode((string) $meta['anchors'], true);
            if (is_array($list)) {
                foreach ($list as $k => $a) {
                    if (! is_array($a)) {
                        continue;
                    }
                    $norm($a['slot'] ?? (is_string($k) ? $k : ''), $a['x'] ?? null, $a['y'] ?? null, $a['scale'] ?? ($a['s'] ?? null), $a['z'] ?? 'front', $a['flip'] ?? false);
                }
            }
        }
        foreach ($meta as $k => $v) {
            if (preg_match('/^anchor\.(P\d{1,3}|caretaker)$/i', (string) $k, $m)) {
                $p = array_map('trim', explode(',', (string) $v));
                $norm($m[1], $p[0] ?? null, $p[1] ?? null, $p[2] ?? null, $p[3] ?? 'front', $p[4] ?? false);
            }
        }

        return $out;
    }

    /** One row on the island surface (2023 art: deck ≈ y 2660 between x ≈ 1000 and 2150). Scale 0 = tier default. */
    public static function defaultAnchors(string $rarity, int $n): array
    {
        $out = [];
        $x0 = 1000.0;
        $x1 = 2150.0;
        for ($i = 1; $i <= $n; $i++) {
            $x = $n === 1 ? ($x0 + $x1) / 2 : $x0 + ($x1 - $x0) * ($i - 1) / ($n - 1);
            $out['P'.$i] = [round($x, 1), 2660.0, 0.0, 'front', false];
        }

        return $out;
    }

    // ------------------------------------------------------------------------------------------------ residents

    /**
     * Premium residents to draw: [{slot, type, id, family}] — valid residents in P slots with a Flovatar-family type.
     */
    public function premiumResidents(Island $island): array
    {
        $cfg = $this->config();
        $n = (int) ($cfg['premium'][$island->rarity] ?? 0);
        $model = (string) ($cfg['slotModel'] ?? 'ps');
        $out = [];
        foreach ($island->residents()->where('valid', true)->get() as $r) {
            $logical = Islands::logicalSlot($island->rarity, $r->slot, $n, $model);
            $family = Islands::familyOf($r->nft_type);
            if ((Islands::isPremiumSlot($logical) || ($r->slot === 'caretaker' && $family === 'Flobot')) && $family) {
                $out[] = ['slot' => $logical, 'type' => $r->nft_type, 'id' => (int) $r->nft_id, 'family' => $family];
            }
        }
        usort($out, fn ($a, $b) => (int) substr($a['slot'], 1) <=> (int) substr($b['slot'], 1));

        return $out;
    }

    /** Placement group for one resident (shadow + nested SVG image), or '' without art. */
    public function residentGroup(string $slot, array $anchor, string $family, string $svg, string $rarity, ?string $typeId = null): string
    {
        [$ax, $ay, $scale, , $flip] = $anchor;
        // family scale: contract setting residentScale.<typeId> (what getSvg uses), else config/islands.php families
        $fam = $typeId !== null ? (($this->config()['residentScale'] ?? [])[$typeId] ?? null) : null;
        $k = ($scale > 0 ? $scale : (float) config('islands.tier_scale.'.$rarity, 0.16))
            * (is_numeric($fam) ? (float) $fam : (float) config('islands.families.'.$family.'.scale', 1.0));
        $k = round($k, 5);
        $tx = round($ax - self::FOOT[0] * $k, 2);
        $ty = round($ay - self::FOOT[1] * $k, 2);
        $flipT = $flip ? ' translate('.(self::RESIDENT_CANVAS).',0) scale(-1,1)' : '';
        $shadow = '<ellipse cx="'.$ax.'" cy="'.$ay.'" rx="'.round(560 * $k, 2).'" ry="'.round(58 * $k, 2).'" fill="#000" opacity=".27"/>';
        $href = 'data:image/svg+xml;base64,'.base64_encode($svg);

        return '<g class="island-resident" data-slot="'.$slot.'">'.$shadow
            .'<g transform="translate('.$tx.' '.$ty.') scale('.$k.')'.$flipT.'">'
            .'<image width="'.self::RESIDENT_CANVAS.'" height="'.self::RESIDENT_CANVAS.'" href="'.$href.'"/></g></g>';
    }

    /** Scene + premium residents. $flatten = pre-resolve resident CSS for resvg (static renderer). */
    /**
     * Browsers reject the whole SVG when a template uses xlink:href but the root lacks xmlns:xlink
     * (svgOpen on chain doesn't declare it). Display-only fix; the chain bytes stay as they are.
     */
    public static function displayFix(string $svg): string
    {
        if (str_contains($svg, 'xlink:') && ! preg_match('/^<svg\b[^>]*\sxmlns:xlink=/', $svg)) {
            $svg = preg_replace('/^<svg\b/', '<svg xmlns:xlink="http://www.w3.org/1999/xlink"', $svg, 1);
        }

        return $svg;
    }

    public function full(Island $island, bool $flatten = false, array $withoutLayers = []): string
    {
        // $withoutLayers: e.g. ['sky'] for the cut-out render the WebGL belt uses (ItemRenderer nobg.png)
        $layers = array_diff_key((array) $island->layers, array_flip($withoutLayers));
        $templates = $this->templates(array_values($layers));
        $svg = $this->scene($layers, $island->tint, (int) $island->flow_id, $templates);
        // round 4: the first standard resident on the display screen (like the contract's getSvg screenParts)
        $display = $this->display($layers, $templates);
        if ($display && $display['screen'] && ($r = $this->screenResident($island))) {
            $href = $this->art->screenImage($r->nft_type, (int) $r->nft_id, $r->thumbnail ?? null);
            if ($href) {
                $svg = self::insertScreen($svg, self::screenSvg((int) $island->flow_id, $display['screen'], $href, $r->slot),
                    (int) data_get($templates[$display['templateId']] ?? null, 'svg_length'));
            }
        }
        $anchors = $this->anchors($island->rarity, $layers, $templates);
        $groups = ['back' => [], 'front' => [], 'caretaker' => []];
        foreach ($this->premiumResidents($island) as $r) {
            $anchor = $anchors[$r['slot']] ?? null;
            $art = $anchor ? $this->art->svg($r['type'], $r['id']) : null;
            if (! $art) {
                continue;
            }
            if ($flatten) {
                $art = $this->flattener->flatten($art);
            }
            $g = $this->residentGroup($r['slot'], $anchor, $r['family'], $art, $island->rarity, $r['type']);
            $groups[$r['slot'] === 'caretaker' ? 'caretaker' : $anchor[3]][] = [$anchor[1], $g];
        }
        // like the contract: residents sorted by anchor y inside a group (farther = drawn first)
        $join = function (array $list) {
            usort($list, fn ($a, $b) => $a[0] <=> $b[0]);

            return implode('', array_column($list, 1));
        };

        return self::inject($svg, $join($groups['back']), $join($groups['front']), $join($groups['caretaker']));
    }

    // ------------------------------------------------------------------------------------------------ display (round 4)

    /**
     * Display template metadata (contract parseScreen): `screen` = "x,y,w,h" + optional `quad` = "x1,y1 x2,y2 x3,y3 x4,y4"
     * (corners TL,TR,BR,BL; an invalid quad = no screen, like the contract). Also accepted: "x,y,w,h,x1,y1,…,y4" and JSON
     * {x,y,w,h,quad:[[x,y]x4]}. Returns ['x','y','w','h','quad' => 8 numbers | []] or null.
     */
    public static function parseScreen($value, $quad = null): ?array
    {
        if ($quad !== null && $quad !== '' && is_string($value)) {
            $q = array_values(array_filter(explode(',', str_replace(' ', ',', (string) $quad)), fn ($x) => $x !== ''));
            if (count($q) !== 8) {
                return null;
            }
            $value = rtrim($value, ', ').','.implode(',', $q);
        }
        if ($value === null || $value === '') {
            return null;
        }
        $v = null;
        if (is_string($value) && in_array(ltrim($value)[0] ?? '', ['{', '['], true)) {
            $j = json_decode($value, true);
            if (is_array($j) && isset($j['x'])) {
                $v = [$j['x'], $j['y'] ?? null, $j['w'] ?? ($j['width'] ?? null), $j['h'] ?? ($j['height'] ?? null)];
                foreach ((array) ($j['quad'] ?? []) as $q) {
                    $v[] = $q[0] ?? null;
                    $v[] = $q[1] ?? null;
                }
            } elseif (is_array($j)) {
                $v = $j;
            }
        } elseif (is_string($value)) {
            $v = array_map('trim', explode(',', $value));
        }
        if (! is_array($v) || (count($v) !== 4 && count($v) !== 12)) {
            return null;
        }
        foreach ($v as $n) {
            if (! is_numeric($n) || (float) $n < 0 || (float) $n > 100000) {
                return null;
            }
        }
        $v = array_map('floatval', array_values($v));
        if ($v[2] <= 0 || $v[3] <= 0) {
            return null;
        }

        return ['x' => $v[0], 'y' => $v[1], 'w' => $v[2], 'h' => $v[3], 'quad' => count($v) === 12 ? array_slice($v, 4, 8) : []];
    }

    /** The scene's display: ['templateId', 'name', 'screen' => parseScreen|null] or null without a display layer. */
    public function display(array $layers, ?array $templates = null): ?array
    {
        $layer = (string) ($this->config()['displayLayer'] ?? 'display');
        if (! isset($layers[$layer])) {
            return null;
        }
        $tid = (int) $layers[$layer];
        $templates ??= $this->templates([$tid]);
        $t = $templates[$tid] ?? null;

        return ['templateId' => $tid, 'name' => (string) data_get($t, 'name', '#'.$tid),
            'screen' => self::parseScreen(((array) (data_get($t, 'metadata') ?? []))['screen'] ?? null, ((array) (data_get($t, 'metadata') ?? []))['quad'] ?? null)];
    }

    /** Resident drawn on the screen: the lowest standard slot (S1, S2, …) with a valid resident (contract screenResident). */
    public function screenResident(Island $island): ?\App\Models\IslandResident
    {
        $cfg = $this->config();
        $n = (int) ($cfg['premium'][$island->rarity] ?? 0);
        $model = (string) ($cfg['slotModel'] ?? 'ps');
        $best = null;
        $bestN = PHP_INT_MAX;
        foreach ($island->residents()->where('valid', true)->get() as $r) {
            if ($r->set_by !== $island->flow_address) {
                continue;
            }
            $logical = Islands::logicalSlot($island->rarity, $r->slot, $n, $model);
            if (preg_match('/^S(\d+)$/', $logical, $m) && (int) $m[1] < $bestN) {
                $bestN = (int) $m[1];
                $best = $r;
            }
        }

        return $best;
    }

    private static function num(float $n): string
    {
        $s = rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');

        return $s === '-0' ? '0' : $s;
    }

    /** Screen fragment (contract screenSvg): clip path rect|polygon + the image filling the screen (slice). */
    public static function screenSvg(int $islandId, array $screen, string $href, string $slot): string
    {
        $cid = 'screen-clip-'.$islandId;
        $q = $screen['quad'] ?? [];
        $f = fn ($n) => self::num((float) $n);
        $clip = count($q) === 8
            ? '<polygon points="'.$f($q[0]).','.$f($q[1]).' '.$f($q[2]).','.$f($q[3]).' '.$f($q[4]).','.$f($q[5]).' '.$f($q[6]).','.$f($q[7]).'"/>'
            : '<rect x="'.$f($screen['x']).'" y="'.$f($screen['y']).'" width="'.$f($screen['w']).'" height="'.$f($screen['h']).'"/>';
        $h = htmlspecialchars($href, ENT_QUOTES | ENT_XML1);
        if (count($q) === 8) {
            $m = implode(' ', array_map($f, [($q[2] - $q[0]) / $screen['w'], ($q[3] - $q[1]) / $screen['w'], ($q[6] - $q[0]) / $screen['h'], ($q[7] - $q[1]) / $screen['h'], $q[0], $q[1]]));
            $img = '<image href="'.$h.'" x="0" y="0" width="'.$f($screen['w']).'" height="'.$f($screen['h']).'" preserveAspectRatio="xMidYMid slice" transform="matrix('.$m.')"/>';
        } else {
            $img = '<image href="'.$h.'" x="'.$f($screen['x']).'" y="'.$f($screen['y']).'" width="'.$f($screen['w']).'" height="'.$f($screen['h']).'" preserveAspectRatio="xMidYMid slice"/>';
        }

        return '<defs><clipPath id="'.$cid.'">'.$clip.'</clipPath></defs><g id="screen-'.$slot.'" class="resident standard screen" data-slot="'.$slot.'" clip-path="url(#'.$cid.')">'.$img.'</g>';
    }

    /**
     * Screen fragment into the scene: the `@display-screen` placeholder when the scene order has one, else right after
     * the display layer body (contract composeSvgWithResidents), else before </svg>. compose.mjs insertScreen.
     */
    public static function insertScreen(string $svg, string $fragment, int $displaySvgLength): string
    {
        if ($fragment === '') {
            return $svg;
        }
        $ph = '<g id="display-screen"/>';
        if (($pos = strpos($svg, $ph)) !== false) {
            return substr_replace($svg, '<g id="display-screen">'.$fragment.'</g>', $pos, strlen($ph));
        }
        $open = '<g id="layer-display">';
        if (($pos = strpos($svg, $open)) !== false) {
            $end = $pos + strlen($open) + $displaySvgLength + 4;
            if (substr($svg, $end - 4, 4) === '</g>') {
                return substr($svg, 0, $end).$fragment.substr($svg, $end);
            }
        }
        $end = strrpos($svg, '</svg>');

        return substr($svg, 0, $end).$fragment.substr($svg, $end);
    }

    /** Fill the residents-back / residents-front placeholders (appended before </svg> when the order has none). */
    public static function inject(string $svg, string $back, string $front, string $caretaker = ''): string
    {
        foreach (['residents-back' => $back, 'residents-front' => $front, 'caretaker' => $caretaker] as $id => $content) {
            if ($content === '') {
                continue;
            }
            $ph = '<g id="'.$id.'"/>';
            $pos = strpos($svg, $ph);
            if ($pos !== false) {
                $svg = substr_replace($svg, '<g id="'.$id.'">'.$content.'</g>', $pos, strlen($ph));
            } else {
                $end = strrpos($svg, '</svg>');
                $svg = substr($svg, 0, $end).'<g id="'.$id.'">'.$content.'</g></svg>';
            }
        }

        return $svg;
    }

    /** Hash of everything that changes the full composition (cache key / ETag). */
    public function fullHash(Island $island): string
    {
        $layers = (array) $island->layers;
        $tpl = IslandTemplate::whereIn('flow_id', array_values($layers))->pluck('svg_hash', 'flow_id')->all();
        ksort($tpl);
        $cols = \App\Models\IslandResident::hasDisplayColumns() ? ['slot', 'nft_type', 'nft_id', 'valid', 'set_by', 'thumbnail'] : ['slot', 'nft_type', 'nft_id', 'valid'];
        $res = $island->residents()->orderBy('slot')->get($cols)->toArray();

        return substr(sha1(json_encode([$layers, $island->tint, $tpl, $res, $this->config()['sceneOrder'] ?? [],
            $this->config()['premium'] ?? [], $this->config()['slotModel'] ?? ''])), 0, 16);
    }
}
