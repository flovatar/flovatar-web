/**
 * Asteroid Islands — pure composition helpers shared by the builder, the island page and the unit tests
 * (tests/js/islands-compose.test.mjs). Mirrors app/Services/Islands/IslandScene.php and app/Support/Islands.php.
 *
 * composeScene() is a byte-identical port of FlovatarAsteroidIsland.composeSvg (contract getSvg / preview_svg).
 */

export const RARITIES = ['common', 'epic', 'legendary'];
export const NEXT_TIER = {common: 'epic', epic: 'legendary'};
export const FOOT = [1500, 2725];
export const RESIDENT_CANVAS = 3000;

export function isHexColor(s) {
    return typeof s === 'string' && /^#[0-9a-fA-F]{6}$/.test(s);
}

/**
 * @param cfg        {svgOpen, sceneOrder}
 * @param layers     {layer: templateId}
 * @param tint       '#rrggbb' | null
 * @param islandId   number | null
 * @param templates  {id: {svg, svgFront, svgLength, svgFrontLength}}
 */
export function composeScene(cfg, layers, tint, islandId, templates) {
    const parts = [String(cfg.svgOpen || '')];
    if (tint !== null && tint !== undefined && isHexColor(tint)) {
        parts.push(' style="--island-tint:' + tint + '"');
    }
    if (islandId !== null && islandId !== undefined) {
        parts.push(' data-island="' + islandId + '"');
    }
    parts.push('>');
    for (const raw of (cfg.sceneOrder || [])) {
        const entry = String(raw);
        if (entry.length > 1 && entry[0] === '@') {
            parts.push('<g id="' + entry.slice(1) + '"/>');
            continue;
        }
        const isFront = entry.length > 6 && entry.endsWith(':front');
        const layer = isFront ? entry.slice(0, -6) : entry;
        if (!(layer in (layers || {}))) { continue; }
        const t = templates[layers[layer]];
        if (!t) { continue; }
        const len = Number(isFront ? t.svgFrontLength : t.svgLength) || 0;
        if (len > 0) {
            parts.push('<g id="layer-' + layer + (isFront ? '-front' : '') + '">');
            parts.push(String(isFront ? (t.svgFront || '') : (t.svg || '')));
            parts.push('</g>');
        }
    }
    parts.push('</svg>');
    return parts.join('');
}

/** Bytes of the chosen templates counted against the rarity budget (contract validateScene: svgLength + svgFrontLength). */
/** Display-only: declare xmlns:xlink when templates use xlink:href (chain svgOpen doesn't). Same as IslandScene::displayFix. */
export function displayFix(svg) {
    if (svg.includes('xlink:') && !/^<svg\b[^>]*\sxmlns:xlink=/.test(svg)) {
        return svg.replace(/^<svg\b/, '<svg xmlns:xlink="http://www.w3.org/1999/xlink"');
    }
    return svg;
}

export function sceneBytes(layers, infos) {
    let total = 0;
    for (const id of Object.values(layers || {})) {
        const t = infos[id];
        if (t) { total += (Number(t.svgLength) || 0) + (Number(t.svgFrontLength) || 0); }
    }
    return total;
}

/** Island rarity from the deed rarity and count (1 = same, 5 = next tier). null when invalid. */
export function islandRarity(deedRarity, count) {
    if (count === 1) { return RARITIES.includes(deedRarity) ? deedRarity : null; }
    if (count === 5) { return NEXT_TIER[deedRarity] || null; }
    return null;
}

/**
 * The contract's per-rarity size budget (bytes) is an internal art-production limit: users never see a size or byte
 * figure. When a combination is ever over budget, the builder shows this generic message instead (KB islands-build §E).
 */
export const OVER_BUDGET_MESSAGE = 'This combination can\u2019t be terraformed. Try a different part.';

/** Is the scene within the contract budget for its rarity (true when no budget is known)? */
export function withinBudget(cfg, rarity, layers, infos) {
    const budget = ((cfg && cfg.budget) || {})[rarity];
    return !budget || sceneBytes(layers, infos) <= budget;
}

/**
 * Chain / wallet error text -> what the builder shows. Size-budget errors never leak byte figures to the user.
 * Everything else is passed through (contract messages are already written for people).
 */
export function friendlyChainError(text) {
    const s = String(text || '');
    if (/size budget|exceeds the size|too large|svg.*length/i.test(s)) { return OVER_BUDGET_MESSAGE; }
    return s.replace(/\b\d[\d,.]*\s*(?:k?B|KB|kB|bytes?)\b/g, '').trim();
}

// ------------------------------------------------------------------------------------------------ round 4 layers
// Contract settings (KB islands-build.md "Round 4 spec"): `sharedLayers` = layers without a rarity lock (sky, fx,
// display; templates carry rarity "any"), `mutableLayers` = layers the owner may change any time with set_layer (contract setting,
// e.g. sky + fx; always read from the config, never hardcoded).
// The display layer may also be chosen ONCE by islands minted before it existed.

export const DISPLAY_LAYER = 'display';

export function isSharedLayer(cfg, layer) {
    return ((cfg && cfg.sharedLayers) || []).includes(layer);
}

export function isMutableLayer(cfg, layer) {
    return ((cfg && cfg.mutableLayers) || []).includes(layer);
}

const LAYER_NOUNS = {sky: 'sky', fx: 'effect', fub: 'FUB', display: 'display', base: 'base', structure: 'structure', foreground: 'foreground'};

/** Singular noun of a layer for UI copy ("Change effect", "You can change the effect any time"). */
export function mutableNoun(layer) {
    return LAYER_NOUNS[layer] || String(layer || '');
}

/** "a", "a and b", "a, b and c" */
export function listText(items) {
    const l = (items || []).filter(Boolean);
    if (l.length < 2) { return l.join(''); }
    return l.slice(0, -1).join(', ') + ' and ' + l[l.length - 1];
}

/** Can this template be used for `layer` on an island of `rarity`? (rarity lock unless the layer is shared / "any") */
export function templateFits(cfg, t, layer, rarity) {
    if (!t || t.layer !== layer || !t.enabled) { return false; }
    return t.rarity === rarity || t.rarity === 'any' || isSharedLayer(cfg, layer);
}

const RARITY_SORT = {any: 0, common: 1, epic: 2, legendary: 3};

/** Options of a layer for a rarity: shared layers list every enabled template (rarity "any" first, then by rarity, id). */
export function layerOptions(cfg, layer, rarity) {
    return ((cfg && cfg.templates) || []).filter((t) => templateFits(cfg, t, layer, rarity))
        .slice().sort((a, b) => (RARITY_SORT[a.rarity] ?? 9) - (RARITY_SORT[b.rarity] ?? 9) || a.id - b.id);
}

/** Scene layers in scene order; `display` sits just before `foreground` even when the chain order doesn't list it yet. */
export function orderedLayers(cfg) {
    const order = ((cfg && cfg.sceneOrder) || []).filter((e) => e[0] !== '@' && !e.endsWith(':front'));
    const out = order.slice();
    if ((cfg && cfg.layers && cfg.layers[DISPLAY_LAYER]) && !out.includes(DISPLAY_LAYER)) {
        const at = out.indexOf('foreground');
        out.splice(at >= 0 ? at : out.length, 0, DISPLAY_LAYER);
    }
    return out;
}

/**
 * Display template metadata (contract parseScreen): `screen` = "x,y,w,h" + optional `quad` = "x1,y1 x2,y2 x3,y3 x4,y4"
 * (corners TL,TR,BR,BL in scene coordinates; an invalid quad = no screen). Also "x,y,w,h,x1,…,y4" and JSON
 * {x,y,w,h,quad:[[x,y]x4]}. The API pre-parses it ({x,y,w,h,quad:[8]}). Returns {x, y, w, h, quad: [8] | []} or null.
 */
export function parseScreen(raw, quad) {
    if (raw === undefined || raw === null || raw === '') { return null; }
    if (quad !== undefined && quad !== null && quad !== '' && typeof raw === 'string') {
        const q = String(quad).replace(/ /g, ',').split(',').filter((x) => x !== '');
        if (q.length !== 8) { return null; }
        raw = raw.replace(/[, ]+$/, '') + ',' + q.join(',');
    }
    let v = null;
    if (typeof raw === 'object' && !Array.isArray(raw)) {
        v = [raw.x, raw.y, raw.w ?? raw.width, raw.h ?? raw.height];
        const q = raw.quad || [];
        if (q.length === 8) { v.push(...q); } else if (q.length === 4) { for (const p of q) { v.push((p || [])[0], (p || [])[1]); } }
    } else if (Array.isArray(raw)) { v = raw; } else {
        const s = String(raw).trim();
        if (s[0] === '{' || s[0] === '[') { try { return parseScreen(JSON.parse(s)); } catch (e) { return null; } }
        v = s.split(',');
    }
    if (v.length !== 4 && v.length !== 12) { return null; }
    const n = v.map((x) => (typeof x === 'string' ? (x.trim() === '' ? NaN : Number(x)) : Number(x)));
    if (!n.every((x) => Number.isFinite(x) && x >= 0 && x <= 100000) || n[2] <= 0 || n[3] <= 0) { return null; }
    return {x: n[0], y: n[1], w: n[2], h: n[3], quad: n.length === 12 ? n.slice(4) : []};
}

/** Screen of a template info from /api/islands/config (pre-parsed `screen`, else metadata.screen). */
export function templateScreen(t) {
    if (!t) { return null; }
    return parseScreen(t.screen) || parseScreen(t.metadata && t.metadata.screen, t.metadata && t.metadata.quad);
}

function fmt(n) { return String(Math.round(n * 10000) / 10000); }

/**
 * Screen fragment = contract screenSvg (App\Services\Islands\IslandScene::screenSvg): clip path (rect or the quad
 * polygon) + the image filling the screen (preserveAspectRatio slice); a quad maps the w x h image box onto TL/TR/BL.
 */
export function screenFragment(screen, href, islandId, slot) {
    if (!screen || !href) { return ''; }
    const cid = 'screen-clip-' + (islandId ?? 'preview');
    const q = screen.quad || [];
    const h = String(href).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    const clip = q.length === 8
        ? '<polygon points="' + fmt(q[0]) + ',' + fmt(q[1]) + ' ' + fmt(q[2]) + ',' + fmt(q[3]) + ' ' + fmt(q[4]) + ',' + fmt(q[5]) + ' ' + fmt(q[6]) + ',' + fmt(q[7]) + '"/>'
        : '<rect x="' + fmt(screen.x) + '" y="' + fmt(screen.y) + '" width="' + fmt(screen.w) + '" height="' + fmt(screen.h) + '"/>';
    let img;
    if (q.length === 8) {
        const m = [(q[2] - q[0]) / screen.w, (q[3] - q[1]) / screen.w, (q[6] - q[0]) / screen.h, (q[7] - q[1]) / screen.h, q[0], q[1]].map(fmt).join(' ');
        img = '<image href="' + h + '" x="0" y="0" width="' + fmt(screen.w) + '" height="' + fmt(screen.h) + '" preserveAspectRatio="xMidYMid slice" transform="matrix(' + m + ')"/>';
    } else {
        img = '<image href="' + h + '" x="' + fmt(screen.x) + '" y="' + fmt(screen.y) + '" width="' + fmt(screen.w) + '" height="' + fmt(screen.h) + '" preserveAspectRatio="xMidYMid slice"/>';
    }
    const s = slot || 'S1';
    return '<defs><clipPath id="' + cid + '">' + clip + '</clipPath></defs><g id="screen-' + s + '" class="resident standard screen" data-slot="' + s + '" clip-path="url(#' + cid + ')">' + img + '</g>';
}

/**
 * Put a screen fragment into a composed scene: the `@display-screen` placeholder when the scene order has one, else
 * right after the display layer body (its length is known from the template), else before </svg>.
 * Same as IslandScene::insertScreen / the contract's composeSvgWithResidents.
 */
export function insertScreen(svg, fragment, displayTemplate) {
    if (!fragment) { return svg; }
    const ph = '<g id="display-screen"/>';
    let i = svg.indexOf(ph);
    if (i >= 0) { return svg.slice(0, i) + '<g id="display-screen">' + fragment + '</g>' + svg.slice(i + ph.length); }
    const open = '<g id="layer-display">';
    i = svg.indexOf(open);
    if (i >= 0 && displayTemplate) {
        const end = i + open.length + String(displayTemplate.svg || '').length + 4;
        if (svg.slice(end - 4, end) === '</g>') { return svg.slice(0, end) + fragment + svg.slice(end); }
    }
    const e = svg.lastIndexOf('</svg>');
    return e >= 0 ? svg.slice(0, e) + fragment + svg.slice(e) : svg;
}

/** Bounding box of a screen in scene coordinates (the zoom target). */
export function screenBox(screen) {
    if (!screen) { return null; }
    const q = screen.quad || [];
    if (q.length === 8) {
        const xs = [q[0], q[2], q[4], q[6]], ys = [q[1], q[3], q[5], q[7]];
        const x = Math.min(...xs), y = Math.min(...ys);
        return {x, y, w: Math.max(...xs) - x, h: Math.max(...ys) - y};
    }
    return {x: screen.x, y: screen.y, w: screen.w, h: screen.h};
}

/** Placeholder art for the screen in the builder ("your first NFT shows here"). */
export const SCREEN_PLACEHOLDER = 'data:image/svg+xml;base64,' + (typeof btoa === 'function' ? btoa : (s) => Buffer.from(s, 'binary').toString('base64'))(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
    + '<stop offset="0" stop-color="#5F37CB"/><stop offset="1" stop-color="#EF2AC1"/></linearGradient></defs>'
    + '<rect width="400" height="300" fill="url(#g)"/><circle cx="200" cy="118" r="52" fill="#AED7F7" stroke="#1A1E2D" stroke-width="8"/>'
    + '<path d="M104 262c10-58 50-86 96-86s86 28 96 86z" fill="#AED7F7" stroke="#1A1E2D" stroke-width="8"/>'
    + '<circle cx="182" cy="112" r="7" fill="#1A1E2D"/><circle cx="218" cy="112" r="7" fill="#1A1E2D"/>'
    + '<rect x="12" y="12" width="376" height="276" rx="14" fill="none" stroke="#FFEE50" stroke-width="6" stroke-dasharray="18 12"/></svg>');

/**
 * Slot changes -> one transaction's operations. `current` / `plan`: {chainSlot: {type, id} | null}.
 * Returns {clears: [slot], sets: [{slot, type, id}]}: clears first (frees capacity and the "already lives here" index),
 * then sets. A resident moved between slots = clear + set.
 */
export function planOps(current, plan) {
    const key = (r) => (r ? r.type + '|' + Number(r.id) : '');
    const clears = [];
    const sets = [];
    const slots = new Set([...Object.keys(current || {}), ...Object.keys(plan || {})]);
    for (const s of slots) {
        const a = key((current || {})[s]);
        const b = key((plan || {})[s]);
        if (a === b) { continue; }
        if (a) { clears.push(s); }
        if (b) { sets.push({slot: s, type: plan[s].type, id: Number(plan[s].id)}); }
    }
    const order = (s) => { const m = /^([A-Za-z]+)(\d+)$/.exec(s); return m ? [m[1], Number(m[2])] : [s, 0]; };
    const cmp = (x, y) => { const a = order(x), b = order(y); return a[0] < b[0] ? -1 : a[0] > b[0] ? 1 : a[1] - b[1]; };
    clears.sort(cmp);
    sets.sort((x, y) => cmp(x.slot, y.slot));
    return {clears, sets};
}

/** Contract validateScene, client side (for UX; the chain re-checks everything). Returns a list of problems. */
export function validateScene(cfg, rarity, layers, tint, infos) {
    const errors = [];
    const layerCfg = cfg.layers || {};
    for (const [name, c] of Object.entries(layerCfg)) {
        if ((c.required || []).includes(rarity) && !(name in layers)) { errors.push('Pick a ' + name); }
    }
    for (const [name, id] of Object.entries(layers)) {
        const c = layerCfg[name];
        const t = infos[id];
        if (!c) { errors.push('Unknown layer ' + name); continue; }
        if (!(c.allowed || []).includes(rarity)) { errors.push(name + ' is not available for ' + rarity + ' islands'); }
        if (!t) { errors.push('Unknown template for ' + name); continue; }
        if (t.layer !== name) { errors.push('Template belongs to another layer'); }
        if (t.rarity !== rarity && t.rarity !== 'any' && !isSharedLayer(cfg, name)) { errors.push(name + ': template rarity doesn\u2019t match'); }
        if (!t.enabled) { errors.push(name + ': template is disabled'); }
    }
    if (!withinBudget(cfg, rarity, layers, infos)) { errors.push(OVER_BUDGET_MESSAGE); }
    if (tint) {
        if (!isHexColor(tint)) { errors.push('Tint must be #RRGGBB'); }
        if (!(cfg.tintRarities || []).includes(rarity)) { errors.push('Tint is not available for ' + rarity + ' islands'); }
    }
    return errors;
}

// ------------------------------------------------------------------------------------------------ slots

export function padSlots(rarity) {
    const pads = ['A', 'B', 'C', 'D', 'E'];
    const idx = [1, 2, 3, 4, 5];
    const out = [];
    if (rarity === 'common') { idx.forEach((i) => out.push('A' + i)); }
    else if (rarity === 'epic') { pads.forEach((p) => idx.forEach((i) => out.push(p + i))); }
    else if (rarity === 'legendary') { idx.forEach((d) => pads.forEach((p) => idx.forEach((i) => out.push('D' + d + p + i)))); }
    return out;
}

/** [{slot: 'P1'|'S1', chain: chain slot id, premium}] (app/Support/Islands::slots). */
export function slotList(rarity, capacity, premium, model) {
    const pad = padSlots(rarity);
    const n = Math.max(0, Math.min(premium, capacity));
    const out = [];
    for (let i = 0; i < capacity; i++) {
        const isP = i < n;
        const logical = isP ? 'P' + (i + 1) : 'S' + (i - n + 1);
        out.push({slot: logical, chain: model === 'pad' ? (pad[i] || logical) : logical, premium: isP});
    }
    return out;
}

// ------------------------------------------------------------------------------------------------ residents

/** Default premium anchors (IslandScene::defaultAnchors). */
export function defaultAnchors(n) {
    const out = {};
    const x0 = 1000;
    const x1 = 2150;
    for (let i = 1; i <= n; i++) {
        const x = n === 1 ? (x0 + x1) / 2 : x0 + (x1 - x0) * (i - 1) / (n - 1);
        out['P' + i] = [Math.round(x * 10) / 10, 2660, 0, 'front', false];
    }
    return out;
}

/** Placement transform of a resident on the 3000×3000 resident canvas (foot 1500,2725). */
export function residentTransform(anchor, tierScale, familyScale) {
    const [ax, ay, scale, , flip] = anchor;
    const k = Math.round(((scale > 0 ? scale : tierScale) * familyScale) * 1e5) / 1e5;
    const tx = Math.round((ax - FOOT[0] * k) * 100) / 100;
    const ty = Math.round((ay - FOOT[1] * k) * 100) / 100;
    return {k, tx, ty, transform: 'translate(' + tx + ' ' + ty + ') scale(' + k + ')' + (flip ? ' translate(' + RESIDENT_CANVAS + ',0) scale(-1,1)' : '')};
}

/**
 * One premium resident group (shadow + nested image), same markup as IslandScene::residentGroup. `href` = data: URI
 * (works inside an <img>-embedded SVG) or URL.
 */
export function residentGroupSvg(slot, anchor, href, tierScale, familyScale) {
    const {k, transform} = residentTransform(anchor, tierScale, familyScale);
    const [ax, ay] = anchor;
    const r2 = (n) => Math.round(n * 100) / 100;
    const safe = String(href).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
    return '<g class="island-resident" data-slot="' + slot + '"><ellipse cx="' + ax + '" cy="' + ay + '" rx="' + r2(560 * k) + '" ry="' + r2(58 * k) + '" fill="#000" opacity=".27"/>'
        + '<g transform="' + transform + '"><image width="' + RESIDENT_CANVAS + '" height="' + RESIDENT_CANVAS + '" href="' + safe + '"/></g></g>';
}

/** Fill residents-back / residents-front / caretaker placeholders (IslandScene::inject). */
export function injectResidents(svg, back, front, caretaker) {
    for (const [id, content] of [['residents-back', back], ['residents-front', front], ['caretaker', caretaker || '']]) {
        if (!content) { continue; }
        const ph = '<g id="' + id + '"/>';
        const i = svg.indexOf(ph);
        if (i >= 0) { svg = svg.slice(0, i) + '<g id="' + id + '">' + content + '</g>' + svg.slice(i + ph.length); } else {
            const e = svg.lastIndexOf('</svg>');
            svg = svg.slice(0, e) + '<g id="' + id + '">' + content + '</g></svg>';
        }
    }
    return svg;
}

/**
 * Hotspot box of a premium slot in scene coordinates (where a resident stands): roughly the Flovatar body on the
 * 3000×3000 resident canvas (x 750..2250, y 300..2725), scaled like the resident.
 */
export function slotBox(anchor, tierScale, familyScale) {
    const {k} = residentTransform(anchor, tierScale, familyScale || 1);
    const [ax, ay] = anchor;
    return {x: ax - 750 * k, y: ay - 2425 * k, w: 1500 * k, h: 2425 * k};
}

/** ipfs:// → gateway, everything else unchanged. */
export function httpUrl(uri) {
    if (!uri) { return ''; }
    const s = String(uri);
    if (s.startsWith('ipfs://')) { return 'https://ipfs.io/ipfs/' + s.slice(7).replace(/^ipfs\//, ''); }
    if (/^https?:\/\//.test(s) || s.startsWith('data:image/')) { return s; }
    if (/^(Qm[1-9A-HJ-NP-Za-km-z]{44}|b[a-z2-7]{50,})/.test(s)) { return 'https://ipfs.io/ipfs/' + s; }
    return '';
}

// Island designation = prefix + 4-digit serial (K313/0001: Kaax Asteroid Belt, Sector K313). The prefix is the contract
// setting `designationPrefix`; the page passes it in window.ISLANDS.designationPrefix (setDesignationPrefix).
let designationPrefix = 'K313/';

export function setDesignationPrefix(prefix) {
    if (typeof prefix === 'string' && /^[A-Za-z0-9][A-Za-z0-9 ./#_-]{0,15}$/.test(prefix.trim())) { designationPrefix = prefix.trim(); }
    return designationPrefix;
}

export function getDesignationPrefix() { return designationPrefix; }

export function designation(id) {
    return designationPrefix + String(id).padStart(4, '0');
}

export function normalizeAddress(a) {
    const s = String(a || '').trim().toLowerCase();
    const m = /^(0x)?([0-9a-f]{1,16})$/.exec(s);
    return m ? '0x' + m[2].padStart(16, '0') : null;
}
