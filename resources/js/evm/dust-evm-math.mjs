/**
 * "Buy ĐUST from any chain" — pure math / route selection (KB notes/flow-evm-program.md EVM-B). No DOM, no FCL:
 * shared by resources/js/evm/dust-evm.js, the FUB refill dialog, the Buy ĐUST widget and tests/js/dust-evm-math.test.mjs.
 *
 * Token amounts on the EVM side are BigInt base units (USDC 6 decimals, ĐUST/WFLOW 18). Rounding favours SAFETY:
 * maximum inputs round up. Price impact INCLUDES pool fees (same definition as dust-swap-math.js priceImpact): how much
 * worse than the spot rate along the route the trade is.
 */

export const ROUTE_LABELS = {direct: 'ĐUST pool on Flow EVM', hop: 'via WFLOW on Flow EVM', via: 'via FLOW (Increment)'};

/** BigInt from a decimal/int string or number (base units). Invalid → 0n. */
export function big(v) {
    try {
        if (typeof v === 'bigint') { return v; }
        if (v === null || v === undefined || v === '') { return 0n; }
        const s = String(v).split('.')[0];
        return /^\d+$/.test(s) ? BigInt(s) : 0n;
    } catch (e) { return 0n; }
}

/** 10^dec as BigInt. No `**` on BigInt anywhere in the site code: Babel rewrites `**` to Math.pow, which throws on BigInt
 *  ("Cannot convert a BigInt value to a number") — that broke every stablecoin quote in the first testnet build. */
export function pow10(dec) {
    let r = 1n;
    for (let i = 0; i < Number(dec); i++) { r *= 10n; }
    return r;
}

/** Decimal string (e.g. "1000.5") → base units BigInt with `dec` decimals (truncates extra digits). */
export function toUnits(value, dec) {
    const s = String(value).trim();
    if (!/^\d+(\.\d+)?$/.test(s)) { return 0n; }
    const [i, f = ''] = s.split('.');
    return BigInt(i) * pow10(dec) + BigInt((f + '0'.repeat(dec)).slice(0, dec) || '0');
}

/** Base units → Number (display only). */
export function fromUnits(units, dec) {
    const u = big(units);
    const d = pow10(dec);
    return Number(u / d) + Number(u % d) / Number(d);
}

/** "0.0975" style: at least 2 decimals, up to `max` significant decimals, trailing zeros trimmed. */
export function fmtUnits(units, dec, max) {
    const n = fromUnits(units, dec);
    const m = max === undefined ? (n >= 100 ? 2 : (n >= 1 ? 4 : 6)) : max;
    return n.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: Math.max(2, m)});
}

/** maxIn for an exact-output swap: quote × (1 + slippage), rounded UP (+1 unit). */
export function maxInUnits(amountIn, slippagePct) {
    const a = big(amountIn);
    const bps = BigInt(Math.round((Number(slippagePct) || 0) * 100));
    return (a * (10000n + bps) + 9999n) / 10000n + 1n;
}

/** Spot output per 1 base unit of input along the hops ([[reserveIn, reserveOut], ...], base units). 0 when a hop is missing. */
export function spotRate(hops) {
    if (!Array.isArray(hops) || !hops.length) { return 0; }
    let r = 1;
    for (const h of hops) {
        if (!Array.isArray(h) || h.length < 2) { return 0; }
        const a = Number(h[0]);
        const b = Number(h[1]);
        if (!(a > 0) || !(b > 0)) { return 0; }
        r *= b / a;
    }
    return r;
}

/** Impact % (incl. fees) of paying amountIn for amountOut along hops. Infinity when unknown. */
export function hopsImpact(amountIn, amountOut, hops) {
    const spot = spotRate(hops);
    const i = Number(big(amountIn));
    const o = Number(big(amountOut));
    if (!(spot > 0) || !(i > 0) || !(o > 0)) { return Infinity; }
    return Math.max(0, (1 - o / (i * spot)) * 100);
}

/** Increment leg impact % (incl. 0.3% fee): FLOW in for exactly `dust` ĐUST at reserves (token units). */
export function incrementImpact(flowIn, dust, reserveFlow, reserveDust) {
    const fi = Number(flowIn); const d = Number(dust); const rf = Number(reserveFlow); const rd = Number(reserveDust);
    if (!(fi > 0) || !(d > 0) || !(rf > 0) || !(rd > 0)) { return Infinity; }
    return Math.max(0, (1 - d / (fi * rd / rf)) * 100);
}

export function impactLevel(impactPct, warnPct, blockPct) {
    if (!Number.isFinite(impactPct) || impactPct > (blockPct || 10)) { return 'block'; }
    if (impactPct > (warnPct || 5)) { return 'warn'; }
    return 'ok';
}

/**
 * Normalise the quote script's routes ({path, viaFlow, ok, amountIn, hops, flowNeeded}) for an exact `amount` ĐUST and
 * pick the cheapest usable one. names[i] labels routes[i] ('direct' | 'hop' | 'via').
 * Returns {routes: [{name, path, viaFlow, ok, amountIn: BigInt, impact, error}], best: route|null}.
 */
export function pickRoute(q, amount, names, opts) {
    const o = opts || {};
    const routes = ((q && q.routes) || []).map((r, i) => {
        const name = (names && names[i]) || (r.viaFlow ? 'via' : 'direct');
        const amountIn = big(r.amountIn);
        let impact = Infinity;
        if (r.ok && amountIn > 0n) {
            if (r.viaFlow) {
                const flowNeeded = Number(r.flowNeeded || q.flowNeeded || 0);
                const evm = hopsImpact(amountIn, toUnits(String(flowNeeded.toFixed(8)), 18), r.hops);
                const inc = incrementImpact(flowNeeded, amount, q.incReserveFlow, q.incReserveDust);
                impact = (1 - (1 - evm / 100) * (1 - inc / 100)) * 100;
            } else {
                impact = hopsImpact(amountIn, toUnits(String(amount), 18), r.hops);
            }
        }
        return {name, path: r.path || [], viaFlow: !!r.viaFlow, ok: !!r.ok && amountIn > 0n && Number.isFinite(impact), amountIn, impact, error: r.error || ''};
    });
    let best = null;
    for (const r of routes) {
        if (!r.ok) { continue; }
        if (o.blockPct && r.impact > o.blockPct) { continue; }
        if (!best || r.amountIn < best.amountIn) { best = r; }
    }
    if (!best) { // nothing under the block limit: still report the cheapest (the UI explains why it's blocked)
        for (const r of routes) { if (r.ok && (!best || r.amountIn < best.amountIn)) { best = r; } }
    }
    return {routes, best};
}

/**
 * "Pay with <stablecoin> on Flow EVM" plan: {maxIn: BigInt, enough, impact, level, short: BigInt (missing units)}.
 * maxIn = quote + slippage, capped at the COA balance when the balance still covers the quote.
 */
export function evmPlan({amountIn, balance, slippagePct, impact, warnPct, blockPct}) {
    const a = big(amountIn);
    const b = big(balance);
    let maxIn = maxInUnits(a, slippagePct);
    if (maxIn > b && b >= a) { maxIn = b; }
    return {maxIn, enough: a > 0n && b >= a, impact, level: impactLevel(impact, warnPct, blockPct), short: a > b ? a - b : 0n};
}

/** Why the stablecoin option can't be used ('' = usable). */
export function evmReason({symbol, plan, quoteError, coa, loading}) {
    if (quoteError) { return quoteError; }
    if (loading && !plan) { return 'Getting the ' + symbol + ' price\u2026'; }
    if (!plan) { return 'No ' + symbol + ' price right now.'; }
    if (plan.level === 'block') { return 'The pools are too thin for this amount (price impact ' + (Number.isFinite(plan.impact) ? plan.impact.toFixed(1) + '%' : 'unknown') + '). Try a smaller amount.'; }
    if (!coa) { return 'Your wallet has no Flow EVM account yet: get ' + symbol + ' into it first.'; }
    if (!plan.enough) { return 'Not enough ' + symbol + ' in your Flow EVM account.'; }
    return '';
}

/** Units the cross-chain step should deliver: the shortfall + a 2% buffer (min 0.01 of the token), rounded up. */
export function bridgeAmount(shortUnits, dec) {
    const s = big(shortUnits);
    if (s <= 0n) { return 0n; }
    const min = pow10(Math.max(0, dec - 2));
    const buf = (s * 2n + 99n) / 100n;
    return s + (buf > min ? buf : min);
}

/**
 * Relay (relay.link) quote request: deliver EXACTLY `amountOut` units of the Flow EVM stablecoin to the wallet's COA,
 * paid from `user`'s USDC (or native) on the origin chain. See https://docs.relay.link (POST /quote).
 */
export function relayQuoteBody({user, recipient, originChainId, originCurrency, destinationChainId, destinationCurrency, amountOut}) {
    if (!/^0x[0-9a-fA-F]{40}$/.test(String(user || '')) || !/^0x[0-9a-fA-F]{40}$/.test(String(recipient || ''))) {
        throw new Error('Invalid address');
    }
    const amt = big(amountOut);
    if (amt <= 0n) { throw new Error('Nothing to bridge'); }
    return {
        user, recipient,
        originChainId: Number(originChainId), destinationChainId: Number(destinationChainId),
        originCurrency, destinationCurrency,
        amount: amt.toString(), tradeType: 'EXACT_OUTPUT',
        referrer: 'flovatar.com',
    };
}

/**
 * Plain-language text for a failed EVM-route transaction ('' = not an EVM-specific failure; caller falls back).
 * The txs surface the router's revert reason ("EVM swap failed: PunchSwapV2Router: EXCESSIVE_INPUT_AMOUNT").
 */
export function evmTxErrorText(e, symbol) {
    const s = String((e && (e.message || e.errorMessage)) || e || '');
    const sym = symbol || 'USDC';
    if (/declined|rejected|cancel/i.test(s)) { return 'You cancelled the transaction in your wallet.'; }
    if (/EXCESSIVE_INPUT_AMOUNT/.test(s)) { return 'The ' + sym + ' price moved above your maximum (slippage). Nothing was spent: try again.'; }
    if (/EVM swap failed: .*EXPIRED|SwapRouter: expired/i.test(s)) { return 'The price quote expired. Nothing was spent: try again.'; }
    if (/transferFrom failed|exceeds balance|insufficient allowance/i.test(s)) { return 'Not enough ' + sym + ' in your Flow EVM account. Nothing was spent.'; }
    if (/has no Flow EVM account/i.test(s)) { return 'This wallet has no Flow EVM account yet: get ' + sym + ' into it first.'; }
    if (/swap path must end/i.test(s)) { return 'Route mismatch: reload the page and try again. Nothing was spent.'; }
    return '';
}

/** "0x0000…" COA address from the script (no 0x / lowercase) → checksum-free 0x form. */
export function evmHex(a) {
    const s = String(a || '').toLowerCase().replace(/^0x/, '');
    return /^[0-9a-f]{40}$/.test(s) ? '0x' + s : '';
}
