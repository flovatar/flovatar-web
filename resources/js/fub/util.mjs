// FUB v1 browser helpers (pure; tests/js/fub-util.test.mjs). The client pre-check is a courtesy for fast feedback:
// the server sanitiser (App\Services\Fub\SvgSanitizer) and the contract are the gates.

export const RARITIES = ['common', 'rare', 'epic', 'legendary'];

const BAD = [
    [/<script/i, 'scripts are not allowed'],
    [/<foreignObject/i, 'foreignObject is not allowed'],
    [/\son[a-z]+\s*=/i, 'event handlers (on...) are not allowed'],
    [/<!DOCTYPE|<!ENTITY/i, 'DOCTYPE / entities are not allowed'],
    [/javascript:/i, 'javascript: links are not allowed'],
    [/(href|src)\s*=\s*["']\s*(https?:|\/\/|data:)/i, 'external or data: references are not allowed'],
    [/url\(\s*["']?\s*(https?:|\/\/|data:)/i, 'external url() references are not allowed'],
    [/@import|@font-face/i, '@import / @font-face are not allowed'],
    [/<(text|tspan|textPath)[\s>]/i, 'text is not allowed (draw letters as shapes)'],
    [/<(image|iframe|embed|object|audio|video)[\s>]/i, 'embedded images/media are not allowed'],
];

/** -> {ok, errors[], warnings[], bytes} */
export function precheckSvg(text, maxInput = 524288) {
    const errors = [];
    const warnings = [];
    const s = String(text || '');
    const bytes = new TextEncoder().encode(s).length;
    if (!s.trim()) { errors.push('The file is empty.'); }
    if (bytes > maxInput) { errors.push('The file is larger than 512 KB.'); }
    if (s && !/<(svg|g)[\s>]/i.test(s)) { errors.push('This does not look like an SVG.'); }
    for (const [re, msg] of BAD) {
        if (re.test(s)) { warnings.push(msg + ' - it will be removed or rejected.'); }
    }
    // eslint-disable-next-line no-control-regex
    if (/[^\x09\x0A\x0D\x20-\x7E]/.test(s)) { warnings.push('Non-ASCII characters will be removed.'); }
    return {ok: errors.length === 0, errors, warnings, bytes};
}

export function fmtDust(v) {
    const n = Number(v || 0);
    return '\u0110' + (Number.isInteger(n) ? n.toLocaleString('en-US') : n.toLocaleString('en-US', {maximumFractionDigits: 2}));
}

export function fmtBytes(n) {
    n = Number(n || 0);
    return n >= 1024 ? (n / 1024).toFixed(1) + ' KB' : n + ' B';
}

/** Contract price curve: price * (1 + increase)^minted, capped at priceCap when a curve is set. */
export function curvePrice(price, increase, cap, minted) {
    const p = Number(price);
    const inc = Number(increase || 0);
    if (inc <= 0) { return p; }
    const v = p * Math.pow(1 + inc, Number(minted || 0));
    return Math.min(v, Number(cap || v));
}

/** Total of picked items (current prices); picks = {layer: item} */
export function totalOf(picks, items) {
    let t = 0;
    for (const id of Object.values(picks || {})) {
        const it = items.find((x) => Number(x.id) === Number(id));
        if (it) { t += Number(it.currentPrice ?? it.price); }
    }
    return Math.round(t * 1e8) / 1e8;
}

export function soldOut(it) {
    return Number(it.maxMint || 0) > 0 && Number(it.minted || 0) >= Number(it.maxMint);
}

/** A complete selection: every required layer picked, every pick available. */
export function selectionProblems(layers, picks, items) {
    const out = [];
    for (const l of layers || []) {
        const id = (picks || {})[l.key];
        if (!id) {
            if (l.required) { out.push('Pick a ' + l.name + '.'); }
            continue;
        }
        const it = items.find((x) => Number(x.id) === Number(id));
        if (!it || !it.live) { out.push(l.name + ': this item is not available.'); } else if (soldOut(it)) { out.push(l.name + ': ' + it.name + ' is sold out.'); }
    }
    return out;
}

/** Random complete selection from available items (dice button). */
export function randomPicks(layers, items, rnd = Math.random) {
    const picks = {};
    for (const l of layers || []) {
        const pool = items.filter((i) => i.layer === l.key && i.live && !soldOut(i) && !i.suspended);
        if (pool.length && (l.required || rnd() < 0.7)) { picks[l.key] = pool[Math.floor(rnd() * pool.length)].id; }
    }
    return picks;
}

/** Ids in layer order for the composition URL. */
export function pickIds(layers, picks) {
    return (layers || []).map((l) => (picks || {})[l.key]).filter(Boolean).map(Number);
}

export function txErrorText(e) {
    const s = String((e && (e.message || e.errorMessage)) || e || '');
    if (/declined|rejected|cancel/i.test(s)) { return 'You cancelled the transaction in your wallet.'; }
    const m = /error: (?:assertion failed|panic): ([^\n]+)/i.exec(s) || /message: "?([^"\n]+)/i.exec(s);
    return m ? m[1].trim() : (s.length > 300 ? s.slice(0, 300) + '\u2026' : s) || 'The transaction failed.';
}

/** Laravel validation / API error -> text */
export function apiErrorText(e) {
    const d = e && e.response && e.response.data;
    if (d && d.errors) { return Object.values(d.errors).flat().join(' '); }
    if (d && d.message) { return d.message; }
    return String((e && e.message) || e || 'Request failed');
}

/** Voucher capacity (public FUB numbers): slots / mints per rarity, tier from total slots. */
export const VOUCHER = {common: {slots: 25, mints: 500}, epic: {slots: 125, mints: 2500}, legendary: {slots: 625, mints: 12500}};

export function capacityOf(counts) {
    let slots = 0;
    let mints = 0;
    for (const r of Object.keys(VOUCHER)) {
        slots += VOUCHER[r].slots * Number((counts || {})[r] || 0);
        mints += VOUCHER[r].mints * Number((counts || {})[r] || 0);
    }
    const tier = slots >= 625 ? 'legendary' : (slots >= 125 ? 'epic' : 'common');
    return {slots, mints, tier};
}
