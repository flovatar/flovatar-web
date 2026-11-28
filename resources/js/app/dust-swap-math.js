/**
 * ĐUST ⇄ FLOW swap math (KB notes/dust-swap.md). Pure functions, no DOM / FCL: shared by resources/js/app/dust-swap.js
 * and the unit test (tests/js/dust-swap-math.test.mjs, run by tests/Unit/DustSwapMathTest.php).
 *
 * Amounts are JS numbers in token units; Cadence arguments are made with ufix() (UFix64 = 8 decimals; the node rejects
 * "1" for UFix64, so always 8 decimals). Rounding is always in the user's favour of SAFETY: maximum inputs round up,
 * minimum outputs round down.
 */

export const UFIX_SCALE = 1e8;

/** UFix64 string with 8 decimals. mode 'down' (default) | 'up'. Throws on negative / non-finite / > UFix64 max. */
export function ufix(value, mode) {
    const v = Number(value);
    if (!isFinite(v) || v < 0 || v > 184467440737) {
        throw new Error('Invalid UFix64 amount: ' + value);
    }
    // work in integer units of 1e-8; toFixed(9) first to kill binary noise (0.1 + 0.2)
    const scaled = Number((v * UFIX_SCALE).toFixed(3));
    const units = mode === 'up' ? Math.ceil(scaled) : Math.floor(scaled);
    const s = String(units).padStart(9, '0');
    return s.slice(0, -8) + '.' + s.slice(-8);
}

/** Constant-product quote, same formula as SwapConfig.getAmountOutVolatile (fee in bps on the input). */
export function amountOut(amountIn, reserveIn, reserveOut, feeBps) {
    if (!(amountIn > 0) || !(reserveIn > 0) || !(reserveOut > 0)) return 0;
    const inAfterFee = amountIn * (1 - (feeBps === undefined ? 30 : feeBps) / 10000);
    return inAfterFee * reserveOut / (reserveIn + inAfterFee);
}

/** Input needed for an exact output, same formula as SwapConfig.getAmountInVolatile (+1e-8 like the contract). */
export function amountIn(amountOutWanted, reserveIn, reserveOut, feeBps) {
    if (!(amountOutWanted > 0) || !(reserveIn > 0) || !(reserveOut > amountOutWanted)) return Infinity;
    return amountOutWanted * reserveIn / (reserveOut - amountOutWanted) / (1 - (feeBps === undefined ? 30 : feeBps) / 10000) + 1 / UFIX_SCALE;
}

/**
 * Price impact in percent INCLUDING the pool fee: how much worse than the spot price (reserveOut / reserveIn) the trade
 * is. 0.3% for a tiny trade (just the fee), grows with size. Returns Infinity for impossible trades.
 */
export function priceImpact(amountInValue, amountOutValue, reserveIn, reserveOut) {
    if (!(amountInValue > 0) || !(amountOutValue > 0) || !(reserveIn > 0) || !(reserveOut > 0)) return Infinity;
    const atSpot = amountInValue * reserveOut / reserveIn;
    return Math.max(0, (1 - amountOutValue / atSpot) * 100);
}

/** Spot rate: ĐUST per 1 FLOW (the only rate the site shows; never USD). */
export function dustPerFlow(reserveDust, reserveFlow) {
    if (!(reserveDust > 0) || !(reserveFlow > 0)) return 0;
    return reserveDust / reserveFlow;
}

/** Clamp a slippage percentage to [0.1, max] (default 1%). */
export function clampSlippage(pct, max) {
    const m = max || 3;
    const v = Number(pct);
    if (!isFinite(v) || v <= 0) return Math.min(1, m);
    return Math.min(Math.max(v, 0.1), m);
}

/** Max input for an exact-output swap with a slippage tolerance (percent), rounded up. */
export function maxInWithSlippage(quoteIn, slippagePct) {
    return Number(ufix(quoteIn * (1 + slippagePct / 100), 'up'));
}

/** Minimum output for an exact-input swap with a slippage tolerance (percent), rounded down. */
export function minOutWithSlippage(quoteOut, slippagePct) {
    return Number(ufix(quoteOut * (1 - slippagePct / 100), 'down'));
}

/**
 * Impact policy: 'ok' | 'warn' (> warnPct, soft warning) | 'block' (> blockPct, the UI refuses).
 */
export function impactLevel(impactPct, warnPct, blockPct) {
    if (!isFinite(impactPct) || impactPct > (blockPct || 10)) return 'block';
    if (impactPct > (warnPct || 2)) return 'warn';
    return 'ok';
}

/**
 * "Pay with FLOW" plan for an exact-output buy: given the router quote (FLOW in for the ĐUST price), the account's
 * spendable FLOW (availableBalance) and the slippage, returns {maxIn, enough, impact, level}. maxIn is capped at the
 * spendable FLOW minus a fee margin, but never below the quote (then enough = false).
 */
export function payPlan({quoteIn, priceOut, reserveIn, reserveOut, available, slippagePct, warnPct, blockPct, feeMargin}) {
    const margin = feeMargin === undefined ? 0.001 : feeMargin;
    const spendable = Math.max(0, (Number(available) || 0) - margin);
    const need = maxInWithSlippage(quoteIn, slippagePct); // what the swap may take (shown when the wallet is short)
    let maxIn = need;
    if (maxIn > spendable) {
        maxIn = Number(ufix(spendable, 'down'));
    }
    const enough = spendable >= quoteIn && maxIn >= quoteIn;
    const impact = priceImpact(quoteIn, priceOut, reserveIn, reserveOut);
    return {maxIn, need, enough, impact, level: impactLevel(impact, warnPct, blockPct)};
}
