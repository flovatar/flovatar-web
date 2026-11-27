<?php

namespace App\Services\Fub;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * FUB SVG gate (FUB-PLAN §c.2 profile, Luca Q24: capped animation + short filter list, no text, ASCII only).
 *
 * Allowlist REBUILD, never "remove the bad parts": the input is parsed with libxml (no network, no entity substitution,
 * DOCTYPE/ENTITY/processing instructions rejected before parsing), then a NEW fragment is serialised from allowlisted
 * elements and attributes only. Every id / class / keyframe is namespaced to "<ns>-…" so composed F.U.N.s never collide,
 * CSS is re-tokenised (property allowlist, selectors scoped to the item root, url(#local) only, no @import/@font-face/…),
 * numbers are rounded to 2 decimals and the output is a single `<g id="<ns>-root">…</g>` that also passes the contract's
 * byte checks (ASCII only; none of ! & ? \ ` or control bytes; starts with "<g ", ends with "</g>").
 *
 * One sanitiser for every door: the manual studio, the draft-tool API used by the design assistant, and the nightly
 * re-check. Output is rendered by us only as <img> / sandboxed (FUB-PLAN §c.5): this class is not the last line.
 */
final class SvgSanitizer
{
    public const VERSION = 'fub-svg-1.0';

    public const MAX_INPUT = 524288;          // bytes accepted before parsing

    public const ITEM_MAX = 32768;            // contract itemMaxBytes

    public const LARGE_MAX = 65536;           // contract largeItemMaxBytes

    public const LIMITS = ['elements' => 2000, 'depth' => 24, 'animations' => 40, 'filters' => 8, 'stops' => 32, 'uses' => 200];

    private const SVG_NS = 'http://www.w3.org/2000/svg';

    private const XLINK_NS = 'http://www.w3.org/1999/xlink';

    public const ELEMENTS = [
        'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'defs', 'linearGradient', 'radialGradient',
        'stop', 'clipPath', 'mask', 'pattern', 'symbol', 'use', 'style', 'animate', 'animateTransform', 'animateMotion', 'mpath',
        'set', 'filter', 'feGaussianBlur', 'feOffset', 'feMerge', 'feMergeNode', 'feColorMatrix', 'feComposite', 'feFlood',
        'feBlend', 'feDropShadow',
    ];

    private const ANIMATIONS = ['animate', 'animateTransform', 'animateMotion', 'set'];

    private const FILTER_PRIMITIVES = ['feGaussianBlur', 'feOffset', 'feMerge', 'feColorMatrix', 'feComposite', 'feFlood', 'feBlend', 'feDropShadow'];

    /** Never allowed; reported as dangerous when present. */
    private const DANGEROUS = ['script', 'foreignObject', 'iframe', 'embed', 'object', 'image', 'a', 'audio', 'video', 'canvas',
        'feImage', 'handler', 'listener', 'html', 'body', 'meta', 'link', 'base'];

    private const ATTRS = [
        'id', 'class', 'transform', 'fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width', 'stroke-opacity',
        'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'opacity',
        'clip-path', 'clip-rule', 'mask', 'filter', 'style', 'display', 'visibility', 'color', 'd', 'x', 'y', 'x1', 'y1', 'x2',
        'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'width', 'height', 'points', 'pathLength', 'offset', 'stop-color', 'stop-opacity',
        'gradientUnits', 'gradientTransform', 'spreadMethod', 'fx', 'fy', 'fr', 'patternUnits', 'patternContentUnits',
        'patternTransform', 'viewBox', 'preserveAspectRatio', 'clipPathUnits', 'maskUnits', 'maskContentUnits', 'filterUnits',
        'primitiveUnits', 'in', 'in2', 'result', 'stdDeviation', 'dx', 'dy', 'mode', 'operator', 'k1', 'k2', 'k3', 'k4', 'values',
        'type', 'flood-color', 'flood-opacity', 'href', 'xlink:href', 'attributeName', 'attributeType', 'from', 'to', 'by',
        'begin', 'dur', 'end', 'repeatCount', 'repeatDur', 'calcMode', 'keyTimes', 'keySplines', 'keyPoints', 'additive',
        'accumulate', 'rotate', 'path', 'restart', 'min', 'max', 'vector-effect', 'paint-order', 'shape-rendering',
        'mix-blend-mode', 'isolation', 'transform-origin', 'transform-box',
    ];

    /** attributeName an animation may target (never href / event / style). */
    private const ANIMATABLE = ['transform', 'opacity', 'fill', 'stroke', 'fill-opacity', 'stroke-opacity', 'd', 'r', 'cx', 'cy', 'x',
        'y', 'width', 'height', 'rx', 'ry', 'stroke-width', 'stroke-dashoffset', 'stroke-dasharray', 'visibility', 'display',
        'offset', 'stop-color', 'stop-opacity', 'points', 'x1', 'x2', 'y1', 'y2', 'flood-color', 'flood-opacity', 'stdDeviation'];

    private const CSS_PROPS = ['fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width', 'stroke-opacity', 'stroke-linecap',
        'stroke-linejoin', 'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'opacity', 'clip-path', 'clip-rule', 'mask',
        'filter', 'display', 'visibility', 'color', 'stop-color', 'stop-opacity', 'flood-color', 'flood-opacity', 'transform',
        'transform-origin', 'transform-box', 'mix-blend-mode', 'isolation', 'paint-order', 'vector-effect', 'shape-rendering',
        'animation', 'animation-name', 'animation-duration', 'animation-delay', 'animation-iteration-count',
        'animation-timing-function', 'animation-direction', 'animation-fill-mode', 'animation-play-state', 'offset'];

    private const NUMERIC_ROUND_ATTRS = ['d', 'points', 'transform', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry',
        'width', 'height', 'stroke-width', 'gradientTransform', 'patternTransform', 'values', 'path', 'from', 'to', 'by', 'fx', 'fy'];

    private string $ns = 'u0-';

    private array $report = [];

    private array $counts = [];

    private array $idMap = [];

    private array $keyframes = [];

    /**
     * @param  string  $ns  namespace prefix (letters/digits, e.g. "u42"): ids become "u42-…", the root is "u42-root"
     * @return array{ok: bool, svg: string, size: int, sha3: string, large: bool, errors: string[], removed: array, dangerous: bool, warnings: string[], stats: array, version: string}
     */
    public function sanitize(string $input, string $ns, bool $large = false): array
    {
        $this->ns = $ns.'-';
        $this->report = ['errors' => [], 'removed' => [], 'warnings' => [], 'dangerous' => false];
        $this->counts = ['elements' => 0, 'depth' => 0, 'animations' => 0, 'filters' => 0, 'stops' => 0, 'uses' => 0];
        $this->idMap = [];
        $this->keyframes = [];

        if (! preg_match('/^[a-z][a-z0-9]{0,15}$/', $ns)) {
            return $this->fail('Invalid namespace');
        }
        if (strlen($input) === 0 || strlen($input) > self::MAX_INPUT) {
            return $this->fail('The file is empty or larger than 512 KB');
        }
        if (! mb_check_encoding($input, 'UTF-8')) {
            return $this->fail('The file is not valid UTF-8');
        }
        $s = preg_replace('/^\xEF\xBB\xBF/', '', $input);
        $s = preg_replace('/^\s*<\?xml[^>]*\?>/i', '', $s);
        // never let libxml see a DTD, entity declaration or processing instruction (XXE, billion laughs)
        if (preg_match('/<!DOCTYPE|<!ENTITY|<\?/i', $s)) {
            return $this->fail('DOCTYPE, ENTITY declarations and processing instructions are not allowed', true);
        }
        if (preg_match('/&(?!(amp|lt|gt|quot|apos|#[0-9]{1,7}|#x[0-9a-fA-F]{1,6});)/', $s)) {
            return $this->fail('Named entities are not allowed');
        }
        $trim = ltrim($s);
        if (! preg_match('/^<svg[\s>]/i', $trim)) {
            $s = '<svg xmlns="'.self::SVG_NS.'" xmlns:xlink="'.self::XLINK_NS.'">'.$s.'</svg>';
        }

        $dom = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($s, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (! $ok || ! $dom->documentElement) {
            return $this->fail('The SVG is not well-formed XML');
        }
        if ($dom->doctype !== null) {
            return $this->fail('DOCTYPE is not allowed', true);
        }
        $root = $dom->documentElement;
        if (strtolower($root->localName) !== 'svg') {
            return $this->fail('The root element must be <svg> or a fragment of SVG elements');
        }

        // pass 1: collect ids (for the namespace map + local reference checks)
        $this->collectIds($root);
        // pass 2: rebuild
        $body = '';
        foreach ($root->childNodes as $child) {
            $body .= $this->node($child, 1);
        }
        foreach (self::LIMITS as $k => $max) {
            if (($this->counts[$k] ?? 0) > $max) {
                return $this->fail("Too complex: more than $max $k");
            }
        }
        if (! preg_match('/<(path|rect|circle|ellipse|line|polyline|polygon|use)[\s\/>]/', $body)) {
            return $this->fail('Nothing drawable is left after sanitising');
        }
        $svg = '<g id="'.$this->ns.'root">'.$body.'</g>';
        $size = strlen($svg);
        $cap = $large ? self::LARGE_MAX : self::ITEM_MAX;
        if ($size > $cap) {
            return $this->fail(sprintf('The sanitised item is %s KB; the limit is %d KB%s', number_format($size / 1024, 1), $cap / 1024,
                $large ? '' : sprintf(' (or %d KB as a large item)', self::LARGE_MAX / 1024)), false, $svg);
        }
        // same byte rules as the contract (FlovatarUniverseBuilder.checkSvg)
        if (preg_match('/[^\x09\x0A\x0D\x20-\x7E]|[!&?\\\\`]/', $svg, $m)) {
            return $this->fail('Internal: forbidden byte left in the output ('.bin2hex($m[0]).')');
        }
        $this->lint($svg);

        return [
            'ok' => true, 'svg' => $svg, 'size' => $size, 'sha3' => hash('sha3-256', $svg), 'large' => $size > self::ITEM_MAX,
            'errors' => [], 'removed' => $this->report['removed'], 'dangerous' => $this->report['dangerous'],
            'warnings' => $this->report['warnings'], 'stats' => $this->counts, 'version' => self::VERSION,
        ];
    }

    private function fail(string $msg, bool $dangerous = false, string $svg = ''): array
    {
        return [
            'ok' => false, 'svg' => $svg, 'size' => strlen($svg), 'sha3' => '', 'large' => false, 'errors' => [$msg],
            'removed' => $this->report['removed'] ?? [], 'dangerous' => $dangerous || ($this->report['dangerous'] ?? false),
            'warnings' => $this->report['warnings'] ?? [], 'stats' => $this->counts, 'version' => self::VERSION,
        ];
    }

    private function removed(string $what, string $why, bool $dangerous = false): void
    {
        if ($dangerous) {
            $this->report['dangerous'] = true;
        }
        $key = $what.'|'.$why;
        if (count($this->report['removed']) < 100 && ! isset($this->report['removed'][$key])) {
            $this->report['removed'][$key] = ['what' => $what, 'why' => $why, 'dangerous' => $dangerous];
        }
    }

    private function collectIds(DOMNode $n): void
    {
        if ($n instanceof DOMElement) {
            $id = $n->getAttribute('id');
            if ($id !== '' && preg_match('/^[A-Za-z_][A-Za-z0-9_.\-]{0,63}$/', $id)) {
                $this->idMap[$id] = $this->ns.$this->clean($id);
            }
        }
        foreach ($n->childNodes ?? [] as $c) {
            $this->collectIds($c);
        }
    }

    private function clean(string $name): string
    {
        return substr(preg_replace('/[^A-Za-z0-9_\-]/', '_', $name), 0, 48);
    }

    private function node(DOMNode $n, int $depth): string
    {
        if ($n->nodeType === XML_TEXT_NODE || $n->nodeType === XML_CDATA_SECTION_NODE) {
            if (trim($n->nodeValue ?? '') !== '' && ! ($n->parentNode instanceof DOMElement && $n->parentNode->localName === 'style')) {
                $this->removed('text content', 'text is not allowed (convert lettering to paths)');
            }

            return '';
        }
        if ($n->nodeType === XML_COMMENT_NODE) {
            $this->removed('comment', 'comments are removed');

            return '';
        }
        if (! $n instanceof DOMElement) {
            return '';
        }
        $tag = $n->localName;
        if ($n->namespaceURI !== null && $n->namespaceURI !== self::SVG_NS) {
            $this->removed('<'.$n->nodeName.'>', 'foreign namespace', true);

            return '';
        }
        if (! in_array($tag, self::ELEMENTS, true)) {
            $danger = in_array($tag, self::DANGEROUS, true) || in_array(strtolower($tag), array_map('strtolower', self::DANGEROUS), true);
            $why = match (true) {
                in_array($tag, ['text', 'tspan', 'textPath'], true) => 'text is not allowed (convert lettering to paths)',
                $danger => 'never allowed (scripts, HTML, external or raster content)',
                default => 'element not in the FUB profile',
            };
            $this->removed('<'.$tag.'>', $why, $danger);

            return '';
        }
        $this->counts['elements']++;
        $this->counts['depth'] = max($this->counts['depth'], $depth);
        if (in_array($tag, self::ANIMATIONS, true)) {
            $this->counts['animations']++;
        }
        if (in_array($tag, self::FILTER_PRIMITIVES, true)) {
            $this->counts['filters']++;
        }
        if ($tag === 'stop') {
            $this->counts['stops']++;
        }
        if ($tag === 'use') {
            $this->counts['uses']++;
        }
        if ($depth > self::LIMITS['depth']) {
            return '';
        }
        if ($tag === 'style') {
            $css = $this->css($n->textContent ?? '');

            return $css === '' ? '' : '<style>'.$css.'</style>';
        }

        $attrs = $this->attributes($n, $tag);
        if ($attrs === null) {
            return ''; // element dropped (e.g. animation of a forbidden attribute)
        }
        $children = '';
        foreach ($n->childNodes as $c) {
            $children .= $this->node($c, $depth + 1);
        }

        return '<'.$tag.$attrs.($children === '' ? '/>' : '>'.$children.'</'.$tag.'>');
    }

    /** Serialised allowlisted attributes, or null when the element itself must go. */
    private function attributes(DOMElement $n, string $tag): ?string
    {
        $out = [];
        foreach ($n->attributes as $a) {
            $name = $a->prefix ? $a->prefix.':'.$a->localName : $a->localName;
            $v = trim((string) $a->value);
            if (str_starts_with(strtolower($a->localName), 'on')) {
                $this->removed($name, 'event handlers are never allowed', true);
                continue;
            }
            if ($a->namespaceURI !== null && $a->namespaceURI !== self::XLINK_NS) {
                $this->removed($name, 'foreign attribute', $a->localName === 'base');
                continue;
            }
            if (! in_array($name, self::ATTRS, true)) {
                $this->removed($name, 'attribute not in the FUB profile');
                continue;
            }
            if (preg_match('/javascript:|data:|vbscript:|expression\(|@import|behavior|binding/i', $v)) {
                $this->removed($name, 'script or external reference', true);
                continue;
            }
            if ($name === 'href' || $name === 'xlink:href') {
                if (! preg_match('/^#([A-Za-z_][A-Za-z0-9_.\-]{0,63})$/', $v, $m) || ! isset($this->idMap[$m[1]])) {
                    $this->removed($name, 'only links to local ids (#id) are allowed', ! str_starts_with($v, '#'));
                    if (in_array($tag, ['use', 'mpath'], true)) {
                        return null;
                    }
                    continue;
                }
                $out['href'] = '#'.$this->idMap[$m[1]];
                continue;
            }
            if ($name === 'id') {
                if (! isset($this->idMap[$v])) {
                    $this->removed('id', 'invalid id');
                    continue;
                }
                $out['id'] = $this->idMap[$v];
                continue;
            }
            if ($name === 'class') {
                $cls = array_filter(array_map(fn ($c) => preg_match('/^[A-Za-z_][A-Za-z0-9_\-]{0,47}$/', $c) ? $this->ns.$c : null, preg_split('/\s+/', $v)));
                if ($cls) {
                    $out['class'] = implode(' ', $cls);
                }
                continue;
            }
            if ($name === 'style') {
                $decls = $this->declarations($v, true);
                if ($decls !== '') {
                    $out['style'] = $decls;
                }
                continue;
            }
            if ($name === 'attributeName') {
                if (! in_array($v, self::ANIMATABLE, true)) {
                    $this->removed('<'.$tag.' attributeName="'.substr($v, 0, 20).'">', 'animations may not target this attribute', in_array(strtolower($v), ['href', 'xlink:href', 'style'], true) || str_starts_with(strtolower($v), 'on'));

                    return null;
                }
                $out[$name] = $v;
                continue;
            }
            if ($name === 'begin' || $name === 'end') {
                if (! preg_match('/^(indefinite|-?[0-9]{1,6}(\.[0-9]{1,3})?(ms|s|min|h)?)(\s*;\s*-?[0-9]{1,6}(\.[0-9]{1,3})?(ms|s|min|h)?)*$/', $v)) {
                    $this->removed($name, 'begin/end accept time offsets only (no events or syncbases)');
                    continue;
                }
                $out[$name] = preg_replace('/\s+/', '', $v);
                continue;
            }
            $v = $this->urlRefs($v, $name);
            if ($v === null) {
                continue;
            }
            if (in_array($name, self::NUMERIC_ROUND_ATTRS, true)) {
                $v = self::roundNumbers($v);
            }
            if (! self::safeValue($v) || strlen($v) > 20000) {
                $this->removed($name, 'value contains characters that are not allowed');
                continue;
            }
            $out[$name] = $v;
        }
        if (in_array($tag, ['use', 'mpath'], true) && ! isset($out['href'])) {
            $this->removed('<'.$tag.'>', 'needs a local href');

            return null;
        }
        $s = '';
        foreach ($out as $k => $v) {
            $s .= ' '.$k.'="'.$v.'"';
        }

        return $s;
    }

    /** url(#id) -> url(#ns-id); any other url() drops the value (null). */
    private function urlRefs(string $v, string $what): ?string
    {
        if (stripos($v, 'url(') === false) {
            return $v;
        }
        $bad = false;
        $r = preg_replace_callback('/url\(\s*[\'"]?([^)\'"]*)[\'"]?\s*\)/i', function ($m) use (&$bad) {
            if (preg_match('/^#([A-Za-z_][A-Za-z0-9_.\-]{0,63})$/', trim($m[1]), $mm) && isset($this->idMap[$mm[1]])) {
                return 'url(#'.$this->idMap[$mm[1]].')';
            }
            $bad = true;

            return '';
        }, $v);
        if ($bad) {
            $this->removed($what, 'url() may only point to a local id', true);

            return null;
        }

        return $r;
    }

    public static function roundNumbers(string $v): string
    {
        return preg_replace_callback('/-?[0-9]*\.[0-9]{3,}(e-?[0-9]+)?/i', function ($m) {
            $f = round((float) $m[0], 2);
            $s = rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.');

            return $s === '-0' ? '0' : $s;
        }, $v);
    }

    public static function safeValue(string $v): bool
    {
        return ! preg_match('/[^\x20-\x7E]|[<>"&\\\\!?`]/', $v);
    }

    /** "a: b; c: d" -> rebuilt declarations (allowlisted properties, safe values). */
    private function declarations(string $s, bool $inline): string
    {
        $out = [];
        foreach (explode(';', $s) as $d) {
            if (trim($d) === '') {
                continue;
            }
            if (! preg_match('/^\s*([a-zA-Z\-]+)\s*:\s*(.+?)\s*$/s', $d, $m)) {
                $this->removed('css', 'unparsable declaration');
                continue;
            }
            $prop = strtolower($m[1]);
            $val = preg_replace('/\s*!\s*important\s*$/i', '', $m[2]);
            if (! in_array($prop, self::CSS_PROPS, true)) {
                $this->removed('css '.$prop, 'CSS property not in the FUB profile');
                continue;
            }
            if (preg_match('/expression|javascript|@|\\\\|image-set|-moz-binding|behavior|attr\(|var\(|env\(/i', $val)) {
                $this->removed('css '.$prop, 'unsafe CSS value', true);
                continue;
            }
            $val = $this->urlRefs($val, 'css '.$prop);
            if ($val === null) {
                continue;
            }
            if (in_array($prop, ['animation', 'animation-name'], true)) {
                $val = preg_replace_callback('/\b([A-Za-z_][A-Za-z0-9_\-]*)\b/', function ($mm) {
                    return isset($this->keyframes[$mm[1]]) ? $this->keyframes[$mm[1]] : $mm[1];
                }, $val);
            }
            $val = self::roundNumbers($val);
            if (! self::safeValue($val) || str_contains($val, ';') || str_contains($val, '{') || str_contains($val, '}')) {
                $this->removed('css '.$prop, 'value contains characters that are not allowed');
                continue;
            }
            $out[] = $prop.':'.$val;
        }

        return implode(';', $out);
    }

    /**
     * <style> rebuild: rules scoped to the item root, @keyframes (namespaced), @media (prefers-reduced-motion) only.
     * Everything else (@import, @font-face, @namespace, @charset, @supports, unknown at-rules) is dropped.
     */
    private function css(string $css): string
    {
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);
        if (preg_match('/[^\x09\x0A\x0D\x20-\x7E]/', $css)) {
            $this->removed('<style>', 'non-ASCII CSS');

            return '';
        }
        // keyframe names first (so animation references can be rewritten)
        if (preg_match_all('/@(?:-webkit-)?keyframes\s+([A-Za-z_][A-Za-z0-9_\-]*)/i', $css, $mm)) {
            foreach ($mm[1] as $k) {
                $this->keyframes[$k] = $this->ns.$this->clean($k);
            }
        }
        $out = '';
        $i = 0;
        $n = strlen($css);
        while ($i < $n) {
            $open = strpos($css, '{', $i);
            if ($open === false) {
                break;
            }
            $prelude = trim(substr($css, $i, $open - $i));
            $close = $this->matchBrace($css, $open);
            if ($close === null) {
                $this->removed('<style>', 'unbalanced braces');
                break;
            }
            $block = substr($css, $open + 1, $close - $open - 1);
            $i = $close + 1;
            if (str_starts_with($prelude, '@')) {
                if (preg_match('/^@(?:-webkit-)?keyframes\s+([A-Za-z_][A-Za-z0-9_\-]*)$/i', $prelude, $m)) {
                    $frames = '';
                    if (preg_match_all('/([0-9.%,\sa-z]+)\{([^{}]*)\}/i', $block, $fm, PREG_SET_ORDER)) {
                        foreach ($fm as $f) {
                            $sel = trim($f[1]);
                            if (! preg_match('/^((from|to|[0-9]{1,3}(\.[0-9]{1,3})?%)\s*,?\s*)+$/i', $sel)) {
                                continue;
                            }
                            $frames .= preg_replace('/\s+/', '', $sel).'{'.$this->declarations($f[2], false).'}';
                        }
                    }
                    $out .= '@keyframes '.$this->keyframes[$m[1]].'{'.$frames.'}';
                } elseif (preg_match('/^@media\s*\(\s*prefers-reduced-motion\s*:\s*(reduce|no-preference)\s*\)$/i', $prelude, $m)) {
                    $inner = $this->cssRules($block);
                    if ($inner !== '') {
                        $out .= '@media (prefers-reduced-motion:'.strtolower($m[1]).'){'.$inner.'}';
                    }
                } else {
                    $this->removed('css '.strtok($prelude, " \t("), 'at-rule not allowed', (bool) preg_match('/@import|@font-face|@namespace/i', $prelude));
                }
                continue;
            }
            $out .= $this->rule($prelude, $block);
        }

        return $out;
    }

    private function cssRules(string $css): string
    {
        $out = '';
        if (preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER)) {
            foreach ($m as $r) {
                $out .= $this->rule(trim($r[1]), $r[2]);
            }
        }

        return $out;
    }

    private function rule(string $selectors, string $block): string
    {
        $sels = [];
        foreach (explode(',', $selectors) as $sel) {
            $sel = trim(preg_replace('/\s+/', ' ', $sel));
            // simple selectors only: element, #id, .class, combinators, :hover/:nth-child; no attribute/functional tricks
            if ($sel === '' || ! preg_match('/^[A-Za-z0-9_\-#.:\s>~+*()]+$/', $sel) || preg_match('/::|:(?!hover|nth-child|first-child|last-child)[a-z]/i', $sel)) {
                $this->removed('css selector', 'selector not allowed');
                continue;
            }
            $sel = preg_replace_callback('/([#.])([A-Za-z_][A-Za-z0-9_\-]*)/', function ($m) {
                if ($m[1] === '#') {
                    return isset($this->idMap[$m[2]]) ? '#'.$this->idMap[$m[2]] : '#'.$this->ns.$this->clean($m[2]);
                }

                return '.'.$this->ns.$m[2];
            }, $sel);
            $root = '#'.$this->ns.'root';
            $sels[] = str_starts_with($sel, $root) ? $sel : $root.' '.$sel;
        }
        if (! $sels) {
            return '';
        }
        $decls = $this->declarations($block, false);

        return $decls === '' ? '' : implode(',', $sels).'{'.$decls.'}';
    }

    private function matchBrace(string $s, int $open): ?int
    {
        $depth = 0;
        for ($i = $open, $n = strlen($s); $i < $n; $i++) {
            if ($s[$i] === '{') {
                $depth++;
            } elseif ($s[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /** Soft brand checks (brand guide §11.2 / §12.7): warnings only, never blocking. */
    private function lint(string $svg): void
    {
        if (! preg_match('/stroke(=|:)"?#0{3}(0{3})?\b/i', $svg)) {
            $this->report['warnings'][] = 'No black outline found: Flovatar art uses black (#000) outlines.';
        } elseif (! preg_match('/stroke-width(=|:)"?13\b/', $svg)) {
            $this->report['warnings'][] = 'Main contours in the Flovatar style are 13 units wide.';
        }
        if (substr_count($svg, 'Gradient') > 4) {
            $this->report['warnings'][] = 'Many gradients: the house style uses flat fills with one 20% black shadow.';
        }
        if ($this->counts['animations'] > 0 && ! str_contains($svg, 'prefers-reduced-motion')) {
            $this->report['warnings'][] = 'Animated: make sure the first frame looks complete on its own.';
        }
    }
}
