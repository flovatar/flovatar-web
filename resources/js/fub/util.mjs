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
    // Increment swap guards (pay with FLOW): the tx reverted, nothing was spent
    if (/EXCESSIVE_INPUT_AMOUNT/.test(s)) { return 'The FLOW price moved above your maximum (slippage). Nothing was spent: try again.'; }
    if (/SwapRouter: expired/i.test(s)) { return 'The FLOW price quote expired. Nothing was spent: try again.'; }
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

// ----- design assistant: token-metered ĐUST (Luca 2026-11-27) -----------------------------------------------------

/** Đ with exactly 2 decimals (token-metered burns are fractions): 12.3 -> "Đ12.30". */
export function fmtDust2(v) {
    const n = Number(v || 0);
    return '\u0110' + n.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

/** m:ss for a number of seconds (recording timer, limits). */
export function mmss(sec) {
    const s = Math.max(0, Math.floor(Number(sec) || 0));
    return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
}

/** Refill amount check (whole ĐUST within the contract's tankMin..tankMax): '' when fine, else the reason. */
export function refillProblem(amount, min, max) {
    const raw = String(amount ?? '').replace(/[,\s_]/g, '');
    if (raw === '') { return 'Enter how much \u0110UST to burn into your tank.'; }
    const n = Number(raw);
    if (!Number.isFinite(n) || !Number.isInteger(n)) { return 'Use a whole number of \u0110UST.'; }
    if (n < Number(min)) { return 'The minimum refill is ' + fmtDust(min) + '.'; }
    if (n > Number(max)) { return 'The maximum refill is ' + fmtDust(max) + '.'; }
    return '';
}

/** Whole ĐUST from a refill input ("10,000" -> 10000), NaN when not a number. */
export function parseDust(amount) {
    return Number(String(amount ?? '').replace(/[,\s_]/g, ''));
}

/** Seconds of voice allowed now: the API's affordable seconds, capped at the maximum (10 min). */
export function voiceLimit(tankVoice, metaVoice) {
    const max = Number((metaVoice && metaVoice.maxSeconds) || 600);
    const can = tankVoice && tankVoice.affordableSeconds !== undefined ? Number(tankVoice.affordableSeconds) : max;
    return Math.max(0, Math.min(max, Math.floor(can)));
}

/** Live voice cost while recording: seconds x Đ per second. */
export function voiceCost(sec, dustPerSecond) {
    return Math.max(0, Number(sec) || 0) * Number(dustPerSecond || 0);
}

/** Receipt line of an assistant reply: Đ burned (2 decimals), steps, tokens, model, time, tank. */
export function receiptText(rc, levelName) {
    if (!rc) { return ''; }
    const parts = [];
    if (rc.status === 'running') { parts.push(fmtDust2(rc.charged) + ' burned so far'); }
    else if (rc.status === 'refunded') { parts.push('refunded, nothing charged'); }
    else if (rc.status === 'blocked') { parts.push('not run, nothing charged'); }
    else { parts.push(fmtDust2(rc.charged) + ' burned'); }
    parts.push(levelName || rc.level);
    if (rc.steps) { parts.push(rc.steps + (rc.steps === 1 ? ' step' : ' steps')); }
    const t = rc.tokens || {};
    const tok = Number(t.input || 0) + Number(t.output || 0);
    if (tok) { parts.push(tok.toLocaleString('en-US') + ' tokens (' + Number(t.input || 0).toLocaleString('en-US') + ' in, ' + Number(t.output || 0).toLocaleString('en-US') + ' out' + (t.cached ? ', ' + Number(t.cached).toLocaleString('en-US') + ' cached' : '') + ')'); }
    if (rc.status === 'failed') { parts.push('stopped part-way'); }
    if (rc.status === 'stopped') { parts.push(rc.stopReason === 'time' ? 'stopped at the time limit' : 'tank ran dry'); }
    if (rc.seconds) { parts.push(Math.round(rc.seconds) + ' s'); }
    if (rc.balanceAfter !== undefined) { parts.push('tank ' + fmtDust2(rc.balanceAfter)); }
    return 'Receipt #' + rc.run + ': ' + parts.join(' \u00b7 ');
}

/** Short amount for a small button: Đ1K, Đ10K, Đ1.5K, Đ1M (whole amounts below 1,000 as they are). */
export function fmtDustShort(v) {
    const n = Number(v || 0);
    const cut = (x) => String(Math.round(x * 10) / 10);
    if (n >= 1000000) { return '\u0110' + cut(n / 1000000) + 'M'; }
    if (n >= 1000) { return '\u0110' + cut(n / 1000) + 'K'; }
    return fmtDust(n);
}

/** One compact line for a receipt in the conversation (the full text is behind it): "5 steps · 18 s". */
export function receiptShort(rc) {
    if (!rc) { return ''; }
    const parts = [];
    if (rc.status === 'refunded') { parts.push('refunded, nothing charged'); }
    if (rc.status === 'blocked') { parts.push('not run, nothing charged'); }
    if (rc.status === 'failed') { parts.push('stopped part-way'); }
    if (rc.status === 'stopped') { parts.push(rc.stopReason === 'time' ? 'stopped at the time limit' : 'tank ran dry'); }
    if (rc.steps) { parts.push(rc.steps + (rc.steps === 1 ? ' step' : ' steps')); }
    if (rc.seconds) { parts.push(Math.round(rc.seconds) + ' s'); }
    return parts.join(' \u00b7 ');
}

/** Tank gauge: percent of the last fill that is left (0-100; a non-empty tank always shows a sliver). */
export function tankPercent(balance, peak) {
    const b = Math.max(0, Number(balance) || 0);
    const p = Math.max(Number(peak) || 0, b);
    if (!(b > 0) || !(p > 0)) { return 0; }
    return Math.max(4, Math.min(100, Math.round(100 * b / p)));
}

/** "About 30 Sip messages in your tank" from the indicative burn of the chosen size ('' when unknown). */
export function messagesLeft(balance, indicative, levelName) {
    const ind = Number(indicative) || 0;
    if (!(ind > 0)) { return ''; }
    const n = Math.floor(Math.max(0, Number(balance) || 0) / ind);
    if (n < 1) { return 'Not enough for a ' + levelName + ' message: refill first'; }
    return 'About ' + n.toLocaleString('en-US') + ' ' + levelName + (n === 1 ? ' message' : ' messages') + ' in your tank';
}

/** "Island #8 (common)" / with its name when it has one. */
export function islandLabel(i) {
    if (!i) { return ''; }
    return 'Island #' + i.id + (i.name ? ' \u00b7 ' + i.name : '') + (i.rarity ? ' (' + i.rarity + ')' : '');
}

/** Why an island can't take a new FUB (commit step), '' when it can. */
export function islandBlockReason(i) {
    if (!i || i.free) { return ''; }
    if (i.reason === 'hosts-fub') { return i.fubId ? 'Already hosts FUB #' + i.fubId : 'Already hosts a FUB'; }
    if (i.reason === 'no-building') { return 'No FUB building for ' + (i.rarity || 'this') + ' islands yet'; }
    return 'Not available';
}

/**
 * Tank refill dialog: which payment options can be used right now.
 *   amount   ĐUST to burn; dust = wallet ĐUST balance
 *   plan     dust-swap-math payPlan() result for "FLOW in for exactly `amount` ĐUST" (null while loading / on error)
 *   swapOn   the site has the ĐUST/FLOW swap configured; quoteError = text when the quote failed
 * -> {dustOk, dustReason, flowOk, flowReason}
 */
export function refillOptions({amount, dust, plan, swapOn, quoteError}) {
    const a = Number(amount) || 0;
    const d = Number(dust) || 0;
    const dustOk = a > 0 && d >= a;
    const dustReason = dustOk ? '' : 'Not enough \u0110UST: you have ' + fmtDust(Math.floor(d * 100) / 100) + '.';
    let flowReason = '';
    if (!swapOn) {
        flowReason = 'Paying with FLOW is not available on this site.';
    } else if (quoteError) {
        flowReason = quoteError;
    } else if (!plan) {
        flowReason = 'Getting the FLOW price\u2026';
    } else if (plan.level === 'block') {
        flowReason = 'The \u0110UST/FLOW pool is too thin for this amount (price impact ' + fmtPct(plan.impact) + '). Refill less or pay with \u0110UST.';
    } else if (!plan.enough) {
        flowReason = 'Not enough FLOW: this needs up to ' + fmtFlow(plan.maxIn) + ' FLOW.';
    }
    return {dustOk, dustReason, flowOk: flowReason === '', flowReason};
}

export function fmtFlow(v) {
    const n = Number(v) || 0;
    return n.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: n < 10 ? 4 : 2});
}

export function fmtPct(v) {
    const n = Number(v);
    if (!Number.isFinite(n)) { return '\u2014'; }
    return (n < 10 ? n.toFixed(2) : n.toFixed(1)) + '%';
}
