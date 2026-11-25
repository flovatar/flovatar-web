// Unit test for resources/js/app/dust-swap-math.js (node --test). Vectors are REAL router quotes:
// testnet own Increment copy (pair 0x76d585e079b73059) and the mainnet pair 0xa06c38beec9cf0e8 (fork run 2026-11-25).
import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {fileURLToPath} from 'node:url';
import {dirname, join} from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const src = readFileSync(join(here, '../../resources/js/app/dust-swap-math.js'), 'utf8');
const m = await import('data:text/javascript;base64,' + Buffer.from(src).toString('base64'));

const close = (a, b, eps, msg) => assert.ok(Math.abs(a - b) <= eps, `${msg}: ${a} vs ${b}`);

test('ufix formats UFix64 with 8 decimals and rounds safely', () => {
    assert.equal(m.ufix(1), '1.00000000');
    assert.equal(m.ufix(25), '25.00000000');
    assert.equal(m.ufix(0.1 + 0.2), '0.30000000');
    assert.equal(m.ufix(0.123456789), '0.12345678');
    assert.equal(m.ufix(0.123456781, 'up'), '0.12345679');
    assert.equal(m.ufix(0.000000001, 'up'), '0.00000001');
    assert.equal(m.ufix(0), '0.00000000');
    assert.throws(() => m.ufix(-1));
    assert.throws(() => m.ufix(NaN));
});

test('amountIn matches SwapRouter.getAmountsIn (testnet pool, Đ25 listing)', () => {
    close(m.amountIn(25, 20, 6020.28, 30), 0.0836499, 2e-8, 'FLOW in for Đ25');
});

test('amountOut matches SwapRouter.getAmountsOut (testnet + mainnet)', () => {
    close(m.amountOut(1, 20.0836499, 5995.28, 30), 283.54411217, 1e-6, 'testnet 1 FLOW -> ĐUST');
    close(m.amountOut(283.54411217, 5711.73588783, 21.0836499, 30), 0.99429153, 1e-7, 'testnet ĐUST -> FLOW');
    close(m.amountOut(10, 5213.65243518, 1569358.25328317, 30), 2995.33551273, 1e-5, 'mainnet 10 FLOW -> ĐUST');
    close(m.amountIn(35, 1568362.91777044, 5217.0310765, 30), 10624.76757404, 1e-4, 'mainnet ĐUST for 35 FLOW');
});

test('impossible quotes fail closed', () => {
    assert.equal(m.amountIn(20, 6000, 20, 30), Infinity);
    assert.equal(m.amountOut(0, 1, 1, 30), 0);
    assert.equal(m.priceImpact(0, 1, 1, 1), Infinity);
    assert.equal(m.impactLevel(Infinity, 2, 10), 'block');
});

test('price impact includes the 0.3% fee and grows with size', () => {
    // tiny trade ≈ fee only
    const small = m.priceImpact(m.amountIn(1, 5213.65, 1569358.25, 30), 1, 5213.65, 1569358.25);
    close(small, 0.3, 0.01, 'tiny trade impact');
    // KB table: buy Đ10,000 on the mainnet pool ≈ +0.94% (cost/spot), i.e. ≈ 0.93% here (output-side definition)
    const qIn = m.amountIn(10000, 5213.65243518, 1569358.25328317, 30);
    close(qIn, 33.5, 0.05, 'Đ10k cost');
    const imp = m.priceImpact(qIn, 10000, 5213.65243518, 1569358.25328317);
    assert.ok(imp > 0.9 && imp < 1.0, 'Đ10k impact ' + imp);
    // Đ100k ≈ 6.6-7.1% → warn; Đ1M → block
    assert.equal(m.impactLevel(m.priceImpact(m.amountIn(100000, 5213.65, 1569358.25, 30), 100000, 5213.65, 1569358.25), 2, 10), 'warn');
    assert.equal(m.impactLevel(m.priceImpact(m.amountIn(1000000, 5213.65, 1569358.25, 30), 1000000, 5213.65, 1569358.25), 2, 10), 'block');
    assert.equal(m.impactLevel(1.5, 2, 10), 'ok');
});

test('slippage bounds: max-in rounds up, min-out rounds down, clamp to 3%', () => {
    assert.equal(m.maxInWithSlippage(0.0836499, 1), 0.08448640);
    assert.equal(m.minOutWithSlippage(283.54411217, 1), 280.70867104);
    assert.equal(m.clampSlippage(5, 3), 3);
    assert.equal(m.clampSlippage(0, 3), 1);
    assert.equal(m.clampSlippage('abc', 3), 1);
    assert.equal(m.clampSlippage(0.01, 3), 0.1);
    assert.equal(m.clampSlippage(2.5, 3), 2.5);
});

test('dustPerFlow is the chip rate (FLOW per ĐUST inverted), never USD', () => {
    close(m.dustPerFlow(1569358.25328317, 5213.65243518), 301.01, 0.01, 'mainnet rate');
    assert.equal(m.dustPerFlow(0, 10), 0);
});

test('payPlan spends only availableBalance (passkey storage reserve) and caps max-in', () => {
    const base = {quoteIn: 0.0836499, priceOut: 25, reserveIn: 20, reserveOut: 6020.28, slippagePct: 1, warnPct: 2, blockPct: 10};
    const rich = m.payPlan({...base, available: 20});
    assert.equal(rich.enough, true);
    assert.equal(rich.maxIn, 0.0844864);
    assert.equal(rich.level, 'ok');
    // passkey account: 0.06 FLOW total but availableBalance ~0 → not enough
    const passkey = m.payPlan({...base, available: 0.0001});
    assert.equal(passkey.enough, false);
    // just enough for the quote but not for the full slippage → capped max-in, still enough
    const tight = m.payPlan({...base, available: 0.0854});
    assert.equal(tight.enough, true);
    assert.ok(tight.maxIn < 0.0844864 && tight.maxIn >= 0.0836499, 'capped ' + tight.maxIn);
});
