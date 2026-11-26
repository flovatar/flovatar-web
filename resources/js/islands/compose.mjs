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
        if (t.rarity !== rarity) { errors.push(name + ': template rarity doesn\u2019t match'); }
        if (!t.enabled) { errors.push(name + ': template is disabled'); }
    }
    const budget = (cfg.budget || {})[rarity];
    if (budget && sceneBytes(layers, infos) > budget) { errors.push('The scene is over the ' + rarity + ' size budget'); }
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
    const x0 = 950;
    const x1 = 2100;
    for (let i = 1; i <= n; i++) {
        const x = n === 1 ? (x0 + x1) / 2 : x0 + (x1 - x0) * (i - 1) / (n - 1);
        out['P' + i] = [Math.round(x * 10) / 10, 2230, 0, 'front', false];
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

/** ipfs:// → gateway, everything else unchanged. */
export function httpUrl(uri) {
    if (!uri) { return ''; }
    const s = String(uri);
    if (s.startsWith('ipfs://')) { return 'https://ipfs.io/ipfs/' + s.slice(7).replace(/^ipfs\//, ''); }
    if (/^https?:\/\//.test(s) || s.startsWith('data:image/')) { return s; }
    if (/^(Qm[1-9A-HJ-NP-Za-km-z]{44}|b[a-z2-7]{50,})/.test(s)) { return 'https://ipfs.io/ipfs/' + s; }
    return '';
}

export function designation(id) {
    return 'K-334/' + String(id).padStart(4, '0');
}

export function normalizeAddress(a) {
    const s = String(a || '').trim().toLowerCase();
    const m = /^(0x)?([0-9a-f]{1,16})$/.exec(s);
    return m ? '0x' + m[2].padStart(16, '0') : null;
}
