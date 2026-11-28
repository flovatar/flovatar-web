// Pack Rewind (testnet) — pure helpers for resources/js/app/pack-rewind.js (unit-tested in tests/js/pack-rewind.test.mjs).

export function fmtNum(v, max = 2) {
    const n = Number(v) || 0;
    return n.toLocaleString('en-US', {maximumFractionDigits: max});
}

/** "9 FLOW" / "Đ3,000" */
export function fmtPrice(price, currency) {
    return currency === 'DUST' ? '\u0110' + fmtNum(price, 0) : fmtNum(price, 2) + ' FLOW';
}

/** Group the chronological series list into year chapters, keeping order. */
export function chapters(series) {
    const out = [];
    for (const s of series || []) {
        let c = out[out.length - 1];
        if (!c || c.year !== s.year) { c = {year: s.year, items: []}; out.push(c); }
        c.items.push(s);
    }
    return out;
}

/** "14 Dec 2021" */
export function fmtDate(iso) {
    const d = new Date(iso + 'T12:00:00Z');
    return isNaN(d) ? iso : d.toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC'});
}

/**
 * How can this wallet pay for a series?
 *   wallet: 'flow' (Flow Wallet / Blocto / passkey) | 'dapper' | ''
 *   flow: spendable FLOW, dust: ĐUST balance, quoteIn: FLOW needed to swap for the ĐUST price (null = no quote)
 * -> {mode: 'flow' | 'dust' | 'swap' | 'none', reason, alt}  (alt = the other possible mode for the ĐUST pack)
 */
export function buyPlan({series, wallet, flow, dust, quoteIn, slippagePct = 3, feeMargin = 0.01}) {
    if (!wallet) { return {mode: 'none', reason: 'connect'}; }
    if (wallet === 'dapper') { return {mode: 'none', reason: 'dapper'}; }
    if (!series || series.remaining <= 0) { return {mode: 'none', reason: 'soldout'}; }
    const f = Number(flow) || 0;
    const price = Number(series.price) || 0;
    if (series.currency !== 'DUST') {
        return f >= price + feeMargin ? {mode: 'flow', reason: ''} : {mode: 'none', reason: 'flow', need: price + feeMargin - f};
    }
    const canDust = (Number(dust) || 0) >= price;
    const maxIn = quoteIn ? Math.ceil(quoteIn * (1 + slippagePct / 100) * 1e8 - 1e-6) / 1e8 : null;
    const canSwap = maxIn !== null && f >= maxIn + feeMargin;
    if (canDust) { return {mode: 'dust', reason: '', alt: canSwap ? 'swap' : null, maxIn}; }
    if (canSwap) { return {mode: 'swap', reason: '', maxIn}; }
    return {mode: 'none', reason: quoteIn === null ? 'quote' : 'flow', maxIn, need: maxIn ? maxIn + feeMargin - f : null};
}

export function reasonText(plan, series) {
    switch (plan.reason) {
        case 'connect': return 'Connect a wallet or sign in with a passkey to replay this pack.';
        case 'dapper': return 'Dapper wallets can\u2019t pay FLOW on testnet: use Flow Wallet or a passkey account.';
        case 'soldout': return 'Sold out: every pack of this drop has been replayed.';
        case 'quote': return 'The \u0110UST/FLOW pool is unavailable right now.';
        case 'flow': return 'Not enough FLOW' + (plan.need ? ' (about ' + fmtNum(plan.need, 2) + ' more)' : '') + '.';
        default: return '';
    }
}

/** Image of one pack item (testnet template id). */
export function itemImage(item, collection, sportvatarUrl) {
    if (collection === 'sportvatar') { return (sportvatarUrl || '').replace(/\/$/, '') + '/api/image/template/' + item.t; }
    return '/api/image/template/' + item.t;
}

const RANK = {legendary: 4, epic: 3, rare: 2, common: 1};
/** Reveal order: commons first, the best item last (drum roll). */
export function revealOrder(items) {
    return [...(items || [])].map((x, i) => ({...x, _i: i})).sort((a, b) => (RANK[a.r] || 0) - (RANK[b.r] || 0) || a._i - b._i);
}

export function progress(series, mine) {
    const total = (series || []).length;
    const done = (series || []).filter((s) => (mine || {})[s.key] > 0).length;
    return {done, total, pct: total ? Math.round((done / total) * 100) : 0};
}
